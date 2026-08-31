<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tag Manager en GA4 laden op elke pagina, maar mogen pas cookies zetten nadat
 * de bezoeker toestemt. Die volgorde is hier het punt: de Consent Mode
 * v2-defaults moeten vóór beide scripts staan, anders zetten ze hun cookies al
 * voor de banner iets kan zeggen — op een Belgische site is dat niet in orde.
 */
class AnalyticsTest extends TestCase
{
    private function homepage(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_the_consent_defaults_come_before_tag_manager_and_ga4(): void
    {
        $html = $this->homepage();

        $defaults = strpos($html, "gtag('consent', 'default'");
        $gtm = strpos($html, 'googletagmanager.com/gtm.js');
        $ga4 = strpos($html, 'googletagmanager.com/gtag/js');

        $this->assertNotFalse($defaults, 'De consent-defaults ontbreken.');
        $this->assertNotFalse($gtm, 'Tag Manager ontbreekt.');
        $this->assertNotFalse($ga4, 'De GA4-tag ontbreekt.');

        $this->assertLessThan($gtm, $defaults, 'De defaults moeten vóór Tag Manager staan.');
        $this->assertLessThan($ga4, $defaults, 'De defaults moeten vóór GA4 staan.');
    }

    public function test_every_optional_signal_starts_denied(): void
    {
        $html = $this->homepage();

        foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage', 'personalization_storage'] as $signal) {
            $this->assertMatchesRegularExpression(
                "~{$signal}:\\s*'denied'~",
                $html,
                "Signaal {$signal} hoort op denied te starten.",
            );
        }
    }

    /**
     * Zonder banner kan de bezoeker niets kiezen en blijft alles op denied
     * staan — dan meet je niets én vraag je niets. De partial stond een tijd
     * uitgecommentarieerd in de layout.
     */
    public function test_the_cookie_banner_is_rendered(): void
    {
        $this->assertStringContainsString('cookieConsent(', $this->homepage());
    }

    public function test_the_ids_are_the_configured_ones(): void
    {
        $html = $this->homepage();

        $this->assertStringContainsString(config('analytics.gtm_container_id'), $html);
        $this->assertStringContainsString(config('analytics.ga4_measurement_id'), $html);
    }

    /**
     * De noscript-variant hoort in de body en niet in de head; in de head
     * negeert de browser hem.
     */
    public function test_tag_manager_has_a_noscript_fallback_in_the_body(): void
    {
        $html = $this->homepage();

        $this->assertStringContainsString('googletagmanager.com/ns.html', $html);
        $this->assertGreaterThan(strpos($html, '<body'), strpos($html, 'ns.html'));
    }

    public function test_it_renders_nothing_when_analytics_is_disabled(): void
    {
        config(['analytics.enabled' => false]);

        $html = $this->homepage();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString("gtag('consent'", $html);
    }
}
