<?php

namespace Tests\Feature\Content;

use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Een brochure die vanaf een productpagina voorgeselecteerd is, reist mee via
 * `?brochure=<pad>`. De taalknop linkte naar de kale permalink, waardoor wie
 * op het formulier van taal wisselde, zijn keuze kwijt was.
 */
class LanguageSwitchQueryTest extends TestCase
{
    private const ALU_NL = 'brochures/winsol_brochure_ramen-en-deuren-in-alu_nl.pdf';

    private const ALU_FR = 'brochures/winsol_brochure_ramen-en-deuren-in-alu_fr.pdf';

    /**
     * @return list<string>
     */
    private function frenchLanguageLinks(string $url): array
    {
        $response = $this->get($url);

        $response->assertOk();

        preg_match_all('#<a\s+href="([^"]*/fr/brochures[^"]*)"#', $response->content(), $matches);

        $this->assertNotEmpty($matches[1], 'De taalknop linkt niet naar de Franse brochurepagina.');

        return $matches[1];
    }

    public function test_the_language_switch_carries_the_chosen_brochure(): void
    {
        foreach ($this->frenchLanguageLinks('/brochures?brochure='.self::ALU_NL) as $href) {
            $this->assertStringEndsWith('/fr/brochures?brochure='.urlencode(self::ALU_NL), $href);
        }
    }

    public function test_the_french_page_behind_the_language_switch_has_the_brochure_checked(): void
    {
        $href = $this->frenchLanguageLinks('/brochures?brochure='.self::ALU_NL)[0];

        $response = $this->get($href);

        $response->assertOk();
        $this->assertSame(1, substr_count($response->content(), ' checked'));
        $this->assertMatchesRegularExpression('#value="'.preg_quote(self::ALU_FR, '#').'"\s+checked#', $response->content());
    }

    public function test_without_a_query_string_the_language_switch_links_to_the_bare_page(): void
    {
        foreach ($this->frenchLanguageLinks('/brochures') as $href) {
            $this->assertStringEndsWith('/fr/brochures', $href);
        }
    }

    public function test_parameters_we_do_not_know_stay_behind(): void
    {
        foreach ($this->frenchLanguageLinks('/brochures?utm_source=nieuwsbrief&brochure='.self::ALU_NL) as $href) {
            $this->assertStringEndsWith('/fr/brochures?brochure='.urlencode(self::ALU_NL), $href);
        }

        foreach ($this->frenchLanguageLinks('/brochures?utm_source=nieuwsbrief') as $href) {
            $this->assertStringEndsWith('/fr/brochures', $href);
        }
    }

    /**
     * De taalknop staat in de header en dus buiten {{ nocache }}. Bewaarde de
     * cache één versie per pad, dan kreeg elke volgende bezoeker van
     * /brochures de brochure van de eerste in zijn taalknop.
     *
     * `Event::forget` na het opwarmen: zie FormErrorsUnderStaticCacheTest.
     */
    public function test_the_static_cache_keeps_the_chosen_brochure_to_its_own_visitor(): void
    {
        config(['cache.stores.static_cache' => ['driver' => 'array']]);
        config(['statamic.static_caching.strategy' => 'half']);

        $this->get('/brochures?brochure='.self::ALU_NL)->assertOk();
        Event::forget(ResponsePrepared::class);

        foreach ($this->frenchLanguageLinks('/brochures') as $href) {
            $this->assertStringEndsWith('/fr/brochures', $href);
        }
    }
}
