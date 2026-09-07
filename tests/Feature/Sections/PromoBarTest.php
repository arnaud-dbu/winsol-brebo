<?php

namespace Tests\Feature\Sections;

use App\Services\RunningPromotion;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Tests\Concerns\CreatesTemporaryContent;

class PromoBarTest extends SectionTestCase
{
    use CreatesTemporaryContent;

    /**
     * De balk vraagt niet zelf naar de lopende actie: de view composer in
     * AppServiceProvider zet `promo_url` en `promo_label` klaar. Die composer
     * wint van de data die `render()` meegeeft — hij draait erna — dus de weg
     * naar een voorspelbare balk loopt via de container.
     */
    private function metActie(?EntryContract $artikel): void
    {
        $this->swap(RunningPromotion::class, new class($artikel) extends RunningPromotion
        {
            public function __construct(private ?EntryContract $artikel) {}

            public function find(): ?EntryContract
            {
                return $this->artikel;
            }
        });
    }

    private function actieartikel(array $data = []): EntryContract
    {
        return $this->temporaryEntry('articles', 'actie-voor-de-balk', array_merge([
            'title' => 'Renovatiedagen 2026',
            'date' => '2026-01-01',
            'promo' => true,
        ], $data));
    }

    public function test_the_header_opens_without_a_bar_when_no_action_is_running(): void
    {
        // Het grootste deel van het jaar loopt er geen actie. De balk hoort
        // dan niet als lege strook te blijven staan.
        $this->metActie(null);

        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringNotContainsString('promo-bar', $html);
    }

    public function test_a_running_action_opens_the_header_with_a_bar_to_its_article(): void
    {
        $artikel = $this->actieartikel(['promo_label' => 'Renovatiedagen — extra voordeel tot 18 oktober']);

        $this->metActie($artikel);

        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('class="promo-bar"', $html);
        $this->assertStringContainsString('Renovatiedagen — extra voordeel tot 18 oktober', $html);
        $this->assertStringContainsString('href="' . $artikel->url() . '"', $html);

        // Boven de nav-balk en binnen dezelfde header: die header zweeft op de
        // home-, product- en rangepagina over een schermbrede foto, dus een
        // strook erbuiten zou daar onder de navigatie verdwijnen.
        $this->assertLessThan(
            strpos($html, 'nav-bar'),
            strpos($html, 'promo-bar'),
            'De actiebalk hoort boven de nav-balk te staan'
        );
    }

    public function test_the_whole_strip_is_the_link_and_the_dot_is_decoration(): void
    {
        $this->metActie($this->actieartikel(['promo_label' => 'Renovatiedagen']));

        $html = $this->render('{{ partial:navigation }}');

        // Een los knopje in de strook zou op een telefoon een doelwit van een
        // paar millimeter worden.
        $this->assertMatchesRegularExpression('/<a href="[^"]*" class="promo-bar">/', $html);

        // Het bolletje is vorm, geen inhoud: een schermlezer hoort alleen de
        // tekst van de actie.
        $this->assertStringContainsString('<span class="promo-bar__dot" aria-hidden="true"></span>', $html);
    }

    public function test_without_a_label_the_title_of_the_article_lands_in_the_bar(): void
    {
        // Zo staat er nooit een lege strook, ook niet wanneer wie de actie
        // aanmaakt het tekstveld overslaat.
        $this->metActie($this->actieartikel(['title' => 'Renovatiedagen 2026: van 10 september tot 18 oktober']));

        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('Renovatiedagen 2026: van 10 september tot 18 oktober', $html);
    }

    public function test_the_body_flags_the_action_so_everything_below_reserves_its_height(): void
    {
        // De balk staat in de header, maar de headers eronder en het mobiele
        // navigatiepaneel moeten zijn hoogte kennen zonder dat hij hun
        // voorouder is. Vandaar de schakelaar op de body.
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));
        $this->assertStringContainsString('<body data-promo="{{ promo_url ? \'true\' : \'false\' }}">', $layout);

        $promo = file_get_contents(resource_path('css/components/promo-bar.css'));
        $this->assertStringContainsString('--promo-height: 0rem;', $promo);
        $this->assertStringContainsString("body[data-promo='true']", $promo);

        // Zonder deze optelling schuift de titel van een zwevende header
        // achter de actiebalk.
        $header = file_get_contents(resource_path('css/components/header.css'));
        $this->assertStringContainsString('var(--nav-height) + var(--promo-height)', $header);
    }

    public function test_the_action_article_ends_with_a_route_to_the_quote_form(): void
    {
        // Advertentieverkeer landt middenin het artikel; de volgende stap mag
        // dan niet alleen achter de hamburger zitten.
        $html = $this->render('{{ partial:promoCta }}');

        $this->assertStringContainsString('/offerte', $html);
        $this->assertStringContainsString('btn btn--primary', $html);
        $this->assertStringContainsString('/contact', $html);

        // En alleen daar: een gewoon nieuwsartikel krijgt dit blok niet.
        $template = file_get_contents(resource_path('views/articles/show.antlers.html'));
        $this->assertMatchesRegularExpression('/\{\{ if promo \}\}\s*\{\{ partial:promoCta \}\}/', $template);
    }
}
