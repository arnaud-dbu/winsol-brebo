<?php

namespace Tests\Feature\Content;

use App\Services\RunningPromotion;
use Illuminate\Support\Carbon;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Winsol voert meerdere keren per jaar een actie. Er is geen aparte
 * `acties`-collectie: het nieuwsartikel ís de actie, en drie velden erop
 * bepalen wanneer de balk boven de navigatie verschijnt en weer verdwijnt.
 * Deze tests bewaken dat venster — het is de enige plek waar een vergeten
 * actie maandenlang op de site kan blijven staan.
 */
class PromotionTest extends TestCase
{
    use \Tests\Concerns\CreatesTemporaryContent;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Een verse instantie en niet `app()`: de service onthoudt zijn antwoord
     * per request, dus een gedeelde zou het resultaat van de vorige oproep
     * teruggeven.
     */
    private function lopendeActie(): ?string
    {
        return (new RunningPromotion)->find()?->slug();
    }

    /**
     * De publicatiedatum gaat via `date()` en niet in de data-array: de
     * collectie is gedateerd, dus Statamic leest hem uit de bestandsnaam en
     * niet uit een veld. In de array zou hij stil genegeerd worden — en dan
     * toetst de test hieronder over toekomstige artikels niets.
     */
    private function actie(string $slug, array $data, string $datum = '2027-01-01'): void
    {
        $this->temporaryEntry('articles', $slug, array_merge([
            'title' => 'Actie ' . $slug,
            'promo' => true,
        ], $data), $datum);
    }

    public function test_it_finds_the_action_that_runs_today(): void
    {
        Carbon::setTestNow('2027-03-10');
        $this->actie('lentedagen', ['promo_start' => '2027-03-01', 'promo_end' => '2027-03-31']);

        $this->assertSame('lentedagen', $this->lopendeActie());
    }

    public function test_an_action_that_has_not_started_stays_off_the_site(): void
    {
        // Het artikel mag al klaarstaan; de balk hoort pas op de startdag te
        // verschijnen.
        Carbon::setTestNow('2027-03-10');
        $this->actie('lentedagen', ['promo_start' => '2027-03-15', 'promo_end' => '2027-03-31']);

        $this->assertNull($this->lopendeActie());
    }

    public function test_the_last_day_of_the_action_still_counts(): void
    {
        // "Tot en met zondag 18 oktober" betekent die hele zondag.
        Carbon::setTestNow('2027-03-31 23:00');
        $this->actie('lentedagen', ['promo_start' => '2027-03-01', 'promo_end' => '2027-03-31']);

        $this->assertSame('lentedagen', $this->lopendeActie());
    }

    public function test_the_day_after_it_disappears_on_its_own(): void
    {
        Carbon::setTestNow('2027-04-01');
        $this->actie('lentedagen', ['promo_start' => '2027-03-01', 'promo_end' => '2027-03-31']);

        $this->assertNull($this->lopendeActie());
    }

    public function test_an_action_without_dates_runs_until_the_toggle_goes_off(): void
    {
        Carbon::setTestNow('2027-03-10');
        $this->actie('doorlopend', []);

        $this->assertSame('doorlopend', $this->lopendeActie());
    }

    public function test_a_news_article_without_the_toggle_is_not_an_action(): void
    {
        Carbon::setTestNow('2027-03-10');
        $this->temporaryEntry('articles', 'gewoon-nieuws', [
            'title' => 'Gewoon nieuws',
        ], '2027-01-01');

        $this->assertNull($this->lopendeActie());
    }

    public function test_a_draft_never_reaches_the_bar(): void
    {
        // De balk zou anders naar een pagina linken die een 404 geeft.
        Carbon::setTestNow('2027-03-10');
        $this->actie('concept', ['promo_start' => '2027-03-01', 'promo_end' => '2027-03-31']);
        Entry::query()->where('collection', 'articles')->where('slug', 'concept')->first()->published(false)->save();

        $this->assertNull($this->lopendeActie());
    }

    public function test_the_action_that_ends_first_wins(): void
    {
        // Loopt er per ongeluk meer dan één, dan is er maar één balk. De
        // dringendste hoort erin, en die maakt vanzelf plaats voor de andere.
        Carbon::setTestNow('2027-03-10');
        $this->actie('loopt-lang', ['promo_start' => '2027-03-01', 'promo_end' => '2027-06-30']);
        $this->actie('loopt-kort', ['promo_start' => '2027-03-01', 'promo_end' => '2027-03-14']);

        $this->assertSame('loopt-kort', $this->lopendeActie());
    }

    public function test_the_renovatiedagen_article_is_a_complete_landing_page(): void
    {
        // Het eerste actieartikel, en meteen de landingspagina voor Google
        // Ads. Zonder beeld, thema of periode is het geen van beide.
        $artikel = Entry::query()
            ->where('collection', 'articles')
            ->where('slug', 'renovatiedagen-2026')
            ->first();

        $this->assertNotNull($artikel, 'Het artikel over de Renovatiedagen ontbreekt');
        $this->assertTrue((bool) $artikel->get('promo'));
        $this->assertNotEmpty($artikel->get('promo_label'));
        $this->assertNotEmpty($artikel->get('image'));
        $this->assertNotEmpty($artikel->get('meta_description'));
        $this->assertSame('2026-09-07', $artikel->get('promo_start'));
        $this->assertSame('2026-10-18', $artikel->get('promo_end'));

        $html = $this->get($artikel->url())->assertOk()->getContent();

        $this->assertStringContainsString('data-section="promo-cta"', $html);
        $this->assertStringContainsString('/offerte', $html);
    }

    public function test_the_article_keeps_the_internal_dealer_conditions_out(): void
    {
        // De actievoorwaarden die Winsol doorstuurde bevatten ook marges,
        // eindejaarsbonussen en aankoopprijzen per artikelnummer. Die horen
        // niet op een publieke pagina.
        $bestand = file_get_contents(base_path('content/collections/articles/nl/2026-09-07.renovatiedagen-2026.md'));

        foreach (['Basiskorting', 'eindejaarsbonus', 'Home Partner', 'artikelnummer', 'omzetgroei'] as $intern) {
            $this->assertStringNotContainsStringIgnoringCase($intern, $bestand);
        }
    }
}
