<?php

namespace Tests\Feature;

use App\Services\LegacyRedirect;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Winsol Brebo verhuist van `winsoldilbeek.be` naar `winsol-brebo.be`. De oude
 * site telt 1674 adressen die jarenlang geïndexeerd, gedeeld en in e-mails
 * geplakt zijn; zonder omleiding landt elke bezoeker daarvan op niets.
 *
 * De tabel staat in `config/legacy_redirects.php`, de redenering bij de regels
 * die niet vanzelf spreken in `.scratch/redirects/mapping.md`.
 */
class LegacyRedirectTest extends TestCase
{
    private const OLD_HOST = 'https://www.winsoldilbeek.be';

    private const NEW_HOST = 'http://winsol-brebo.test';

    /**
     * `$this->get()` kan hier niet: `prepareUrlForRequest()` trimt de
     * afsluitende slash eraf, en elk adres van de oude site heeft er een.
     * Via de kernel loopt het verzoek door de echte middleware-stack, en kan
     * er ook een eigen host meegegeven worden.
     */
    private function request(string $url, string $method = 'GET'): TestResponse
    {
        return TestResponse::fromBaseResponse(
            $this->app->make(Kernel::class)->handle(Request::create($url, $method))
        );
    }

    /**
     * @return list<string>
     */
    private function destinations(): array
    {
        return array_values(array_unique(array_merge(
            array_values(config('legacy_redirects.paths')),
            array_values(config('legacy_redirects.brochures')),
        )));
    }

    /**
     * De gevaarlijkste fout van een migratie: een omleiding die werkt en op
     * een foutpagina uitkomt. Google verplaatst de zoekwaarde van het oude
     * adres dan naar een 404.
     */
    public function test_every_destination_in_the_table_serves_a_page(): void
    {
        foreach ($this->destinations() as $destination) {
            $this->assertSame(
                200,
                $this->request(self::NEW_HOST.$destination)->getStatusCode(),
                "De bestemming {$destination} geeft geen 200."
            );
        }
    }

    /**
     * Een zuivere gegevenscontrole over alle 1674 adressen uit de sitemap van
     * de oude site, zonder de pagina's te renderen. Toets 1 heeft de
     * bestemmingen al op een 200 gecontroleerd; hier gaat het erom dat elk oud
     * adres in die verzameling uitkomt en niet in het niets.
     */
    public function test_every_address_in_the_old_sitemap_resolves_to_a_verified_destination(): void
    {
        $destinations = $this->destinations();
        $sitemap = file(
            __DIR__.'/../fixtures/legacy-sitemap.txt',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        $this->assertCount(1674, $sitemap);

        foreach ($sitemap as $old) {
            $destination = LegacyRedirect::destinationFor(LegacyRedirect::normalise($old));

            $this->assertNotNull($destination, "Het oude adres {$old} lost nergens op.");
            $this->assertContains(
                $destination,
                $destinations,
                "Het oude adres {$old} lost op naar {$destination}, dat niet in de tabel staat."
            );
        }
    }

    /**
     * De tabel is een overname van het beslisdocument, geen eigen werk: de
     * mapping is daar afgewogen en de redenering staat erbij. Zonder deze
     * toets valt een regel geruisloos weg — de klim absorbeert hem, en de
     * bezoeker landt op de bovenliggende pagina terwijl de suite groen blijft.
     */
    public function test_the_table_reproduces_the_mapping_document(): void
    {
        $mapping = file_get_contents(__DIR__.'/../../.scratch/redirects/mapping.md');

        preg_match('/## De volledige tabel.*/s', $mapping, $section);
        preg_match_all('/^\| `(.+?)` \| `(.+?)` \|$/m', $section[0], $rows, PREG_SET_ORDER);

        $decided = [];

        foreach ($rows as [, $old, $new]) {
            $decided[$old === '(wortel)' ? '/' : $old] = $new;
        }

        $this->assertSame($decided, config('legacy_redirects.paths'));

        preg_match('/## Brochureadressen.*?```\n(.*?)```/s', $mapping, $block);

        // Het blok staat daar in twee kolommen; alleen de verzameling telt.
        $slugs = preg_split('/\s+/', trim($block[1]));
        sort($slugs);

        $this->assertSame($slugs, config('legacy_redirects.brochure_slugs'));
    }

    /**
     * Geen ketens en geen lussen: een bestemming die zelf weer omgeleid wordt
     * kost Google een extra sprong. Een regel die naar zichzelf wijst mag wel
     * — die bestaat om op de oude host van host te wisselen, en leidt op de
     * nieuwe host tot niets.
     */
    public function test_no_destination_is_redirected_again(): void
    {
        foreach ($this->destinations() as $destination) {
            $again = LegacyRedirect::destinationFor($destination, climb: false);

            $this->assertTrue(
                $again === null || $again === $destination,
                "De bestemming {$destination} wordt zelf omgeleid naar {$again}."
            );
        }
    }

    public function test_an_exact_rule_redirects_permanently_to_its_counterpart(): void
    {
        $this->request(self::OLD_HOST.'/nl/Contact/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/contact');
    }

    /**
     * Een gemeentepagina heeft geen eigen regel. Hij klimt naar de wortel van
     * zijn productgroep, en die staat gewoon als normale regel in de tabel.
     */
    public function test_a_municipality_page_lands_on_the_product_group_it_was_about(): void
    {
        $this->request(self::OLD_HOST.'/Winsol-rolluiken/Aartselaar/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/aanbod/rolluiken');
    }

    public function test_a_french_address_lands_on_the_french_site(): void
    {
        $this->request(self::OLD_HOST.'/fr/Notre-gamme/Volets-roulants/Volets-roulants-superposes/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/fr/gamme/volets-roulants/volets-en-applique');
    }

    /**
     * De sitemap van de oude site is aantoonbaar onvolledig, en er bleken
     * dubbelvormen te bestaan zoals `Pergola-SO-` naast `Pergola-SO`. De klim
     * vangt op wat niemand in de tabel gezet heeft.
     */
    public function test_an_unlisted_address_climbs_to_the_nearest_parent(): void
    {
        $this->request(self::OLD_HOST.'/fr/Notre-gamme/Couvertures-de-terrasse/Pergola-SO-/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/fr/gamme/couverture-de-terrasse');
    }

    public function test_the_climb_keeps_going_up_until_it_finds_a_rule(): void
    {
        $this->request(self::OLD_HOST.'/nl/Ons-aanbod/Rolluiken/Onbekend/Nog-dieper/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/aanbod/rolluiken');
    }

    /**
     * Massaal doorsturen naar de root leest Google als soft 404 en zet de
     * bezoeker op een pagina die zijn vraag niet raakt. Een adres dat nergens
     * op uitkomt houdt daarom zijn eigen pad, en landt op de nette 404 van de
     * nieuwe site in plaats van op de homepage.
     */
    public function test_an_address_with_no_parent_at_all_keeps_its_own_path(): void
    {
        $this->request(self::OLD_HOST.'/Zomaar-iets/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/zomaar-iets');

        $this->request(self::NEW_HOST.'/zomaar-iets')->assertStatus(404);
    }

    /**
     * `/nl` en `/fr` staan als gewone regel in de tabel, want dat waren de
     * homepages van de oude site. Een onbekend adres eronder mag daar niet
     * naartoe klimmen: alles naar de root sturen leest Google als soft 404.
     */
    public function test_the_climb_never_lands_on_a_homepage(): void
    {
        foreach (['/nl/onzin', '/fr/onzin', '/fr/onzin/dieper'] as $old) {
            $this->assertNull(
                LegacyRedirect::destinationFor($old),
                "Het adres {$old} klimt naar een homepage."
            );
        }

        $this->request(self::OLD_HOST.'/nl/Onzin/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/nl/onzin');
    }

    /**
     * Een exacte regel mag wél op de homepage uitkomen: wie `/nl/Home/`
     * opvraagt vroeg om de homepage.
     */
    public function test_an_exact_rule_may_point_at_the_homepage(): void
    {
        $this->request(self::OLD_HOST.'/nl/Home/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/');
    }

    /**
     * `isMethodCacheable()` laat HEAD bewust toe: Google en menig crawler
     * vragen een adres eerst met HEAD op.
     */
    public function test_a_head_request_redirects_just_like_a_get(): void
    {
        $this->request(self::OLD_HOST.'/nl/Contact/', 'HEAD')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/contact');
    }

    /**
     * De 404 van de nieuwe site, niet de kale foutpagina van Laravel: de
     * bezoeker moet zien waar hij is en verder kunnen.
     */
    public function test_that_404_is_the_page_of_the_new_site(): void
    {
        $this->request(self::NEW_HOST.'/zomaar-iets')
            ->assertStatus(404)
            ->assertSee('</html>', false);
    }

    public function test_a_brochure_download_lands_on_the_brochure_page(): void
    {
        $this->request(self::OLD_HOST.'/nl/Ons-aanbod/Rolluiken/Inbouwrolluiken/Download-brochure-Rolluiken-NL/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/brochures');
    }

    public function test_a_french_brochure_download_lands_on_the_french_brochure_page(): void
    {
        $this->request(self::OLD_HOST.'/fr/Notre-gamme/Portes-en-acier/Download-brochure-steel-design/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/fr/brochures');
    }

    /**
     * Elk adres van de oude site eindigt op een slash. Zou
     * `RedirectTrailingSlash` eerst aan de beurt zijn, dan werd het twee
     * sprongen in plaats van één.
     */
    public function test_an_address_with_and_without_a_trailing_slash_takes_the_same_single_hop(): void
    {
        $this->request(self::OLD_HOST.'/nl/Over-ons/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/over-ons');

        $this->request(self::OLD_HOST.'/nl/Over-ons')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/over-ons');
    }

    /**
     * De oude site stuurt nu apex naar `www` naar `http`: drie sprongen met
     * een degradatie erin.
     */
    public function test_the_apex_of_the_old_domain_goes_straight_to_https_on_the_new_site(): void
    {
        $this->request('https://winsoldilbeek.be/')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/');
    }

    /**
     * `getQueryString()` sorteert parameters alfabetisch en voegt `=` toe aan
     * een waardeloze sleutel; dat verandert een gedeelde campagnelink.
     */
    public function test_the_query_string_survives_with_its_original_order_and_shape(): void
    {
        $this->request(self::OLD_HOST.'/nl/Contact/?utm_source=nieuwsbrief&b=2&a=1')
            ->assertStatus(301)
            ->assertRedirect('https://winsol-brebo.be/contact?utm_source=nieuwsbrief&b=2&a=1');
    }

    /**
     * `NoIndexHeader` en `SecurityHeaders` staan vóór deze omleiding. Stonden
     * ze erna, dan liepen ze nooit: de omleiding retourneert een 301 zonder
     * `$next` aan te roepen, en droeg dan zelf geen enkele header.
     */
    public function test_the_redirect_still_carries_the_security_headers(): void
    {
        $this->request(self::OLD_HOST.'/nl/Contact/')
            ->assertStatus(301)
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    /**
     * Een 301 op een POST laat de browser opnieuw versturen zonder body,
     * waarmee een formulier stilzwijgend leegloopt.
     */
    public function test_a_post_is_left_alone(): void
    {
        $this->assertNotSame(
            301,
            $this->request(self::OLD_HOST.'/nl/Contact/', 'POST')->getStatusCode(),
            'Een POST hoort de applicatie te bereiken, niet omgeleid te worden.'
        );
    }

    /**
     * Een oud adres dat iemand op de nieuwe site plakt komt ook aan; alleen
     * het pad wordt dan omgezet.
     */
    public function test_an_old_address_pasted_on_the_new_site_is_redirected_too(): void
    {
        $this->request(self::NEW_HOST.'/nl/Contact/')
            ->assertStatus(301)
            ->assertRedirect(self::NEW_HOST.'/contact');
    }

    /**
     * Een regel die naar zichzelf wijst bestaat om op de oude host van host te
     * wisselen. Op de nieuwe host zou hij een lus opleveren.
     */
    public function test_nothing_redirects_to_itself_on_the_new_host(): void
    {
        $this->request(self::NEW_HOST.'/')->assertOk();
        $this->request(self::NEW_HOST.'/fr')->assertOk();
        $this->request(self::NEW_HOST.'/fr/contact')->assertOk();
    }

    /**
     * Zou de klim ook op de nieuwe host gelden, dan leidde elk onbekend pad
     * naar een bovenliggende pagina in plaats van een 404 te geven.
     */
    public function test_nothing_climbs_on_the_new_host(): void
    {
        $this->request(self::NEW_HOST.'/aanbod/rolluiken/bestaat-niet')->assertStatus(404);
    }

    /**
     * In de opzet van Forge serveert nginx het controlebestand van Let's
     * Encrypt via `try_files` voordat PHP aan bod komt, dus meestal komt dit
     * pad hier niet eens langs. Valt dat door, dan hoort er een eerlijk
     * antwoord te staan; waarom, staat bij de guard in `RedirectLegacyUrls`.
     */
    public function test_the_reserved_well_known_namespace_is_never_redirected(): void
    {
        $this->request(self::OLD_HOST.'/.well-known/acme-challenge/tok')->assertStatus(404);

        // De kale namespace ook: `normalise()` haalt de afsluitende slash
        // eraf, dus zonder die vorm zou juist die weer omgeleid worden. Met
        // slash blijft `RedirectTrailingSlash` erna aan zet, en die houdt het
        // op dezelfde host.
        $this->request(self::OLD_HOST.'/.well-known')->assertStatus(404);

        // De namespace gaat in zijn geheel mee, niet alleen `acme-challenge`:
        // `security.txt` is van deze applicatie en werd op de oude host mee
        // omgeleid.
        $this->request(self::OLD_HOST.'/.well-known/security.txt')->assertOk();
    }
}
