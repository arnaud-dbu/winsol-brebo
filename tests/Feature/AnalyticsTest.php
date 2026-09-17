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

    /**
     * De kern van de fix van 17-09-2026. De banner draait op Alpine en dat komt
     * uit een `type="module"`-bundel, dus uitgesteld tot na het parsen. Een
     * inline body-script als `formSuccessEvent` is er dan allang geweest. Stond
     * de toestemming van een terugkerende bezoeker in de component, dan kwam
     * `consent_accepted` structureel ná `form_submit_success` en faalde elke
     * GTM-trigger met een voorwaarde op `consent_marketing`. Daarom hoort dit
     * script inline in de head, vóór Tag Manager.
     */
    public function test_the_returning_visitor_consent_runs_before_tag_manager(): void
    {
        $html = $this->homepage();

        $bootstrap = strpos($html, '__cookieConsentSignalled');
        $defaults = strpos($html, "gtag('consent', 'default'");
        $gtm = strpos($html, 'googletagmanager.com/gtm.js');

        $this->assertNotFalse($bootstrap, 'Het bootstrap-script voor een terugkerende bezoeker ontbreekt.');
        $this->assertLessThan($gtm, $bootstrap, 'De toestemming moet gesignaleerd zijn vóór Tag Manager laadt.');
        $this->assertGreaterThan($defaults, $bootstrap, 'De defaults moeten eerst staan, anders overschrijven ze de update.');
    }

    public function test_the_bootstrap_pushes_the_consent_categories(): void
    {
        $html = $this->homepage();

        $this->assertStringContainsString("event: 'consent_accepted'", $html);

        foreach (['consent_marketing', 'consent_analytics', 'consent_personalization'] as $key) {
            $this->assertStringContainsString($key, $html, "De categorie {$key} hoort mee in de push.");
        }
    }

    /**
     * De cookie wordt op twee plaatsen gelezen: inline in de head en in de
     * Alpine-component. Dat is bewuste duplicatie — de bundel is te laat voor
     * de head — maar de twee moeten wel dezelfde cookie en dezelfde
     * signaalmapping gebruiken, anders signaleert de een iets anders dan de
     * ander.
     */
    public function test_the_head_script_and_the_component_agree_on_the_mapping(): void
    {
        $partial = file_get_contents(resource_path('views/partials/analytics.antlers.html'));
        $module = file_get_contents(resource_path('js/components/cookie-consent.js'));

        $this->assertSame(
            $this->signalMap($module),
            $this->signalMap($partial),
            'De categorie-naar-signaal-mapping loopt uiteen tussen de partial en de Alpine-component.',
        );

        foreach (["'cookie_consent'", 'marketing', 'personalization', 'analytics'] as $shared) {
            $this->assertStringContainsString($shared, $partial);
            $this->assertStringContainsString($shared, $module);
        }
    }

    /**
     * Haalt per categorie de Consent Mode-signalen uit een bestand, ongeacht of
     * ze in `CONSENT_MODE_MAP` of in `SIGNALS` staan.
     *
     * @return array<string, list<string>>
     */
    private function signalMap(string $source): array
    {
        $map = [];

        foreach (['marketing', 'personalization', 'analytics'] as $category) {
            preg_match("~{$category}:\\s*\\[([^\\]]*)\\]~", $source, $matches);

            $signals = isset($matches[1])
                ? preg_split('~\s*,\s*~', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY)
                : [];

            $map[$category] = array_values(array_map(
                static fn (string $signal): string => trim($signal, " \t\n\r'\""),
                $signals,
            ));
        }

        return $map;
    }
}
