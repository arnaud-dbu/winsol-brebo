<?php

namespace Tests\Feature\Sections;

class NavigationTest extends SectionTestCase
{
    public function test_menu_is_driven_by_the_main_navigation_structure(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Deze titels komen uit content/trees/navigation/main.yaml, niet uit de
        // template — bewijst dat het menu geen hardcoded lijst links is.
        $this->assertStringContainsString('Aanbod', $html);
        $this->assertStringContainsString('Nieuws', $html);
        $this->assertStringContainsString('Service', $html);
        $this->assertStringContainsString('Over ons', $html);
        $this->assertStringContainsString('Contact', $html);
    }

    public function test_menu_items_follow_the_order_of_the_structure(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De boomvolgorde uit content/trees/navigation/main.yaml — die wijkt
        // sinds de "Update UI"-commits af van Figma 332:3244 (Over ons staat
        // vroeger); Realisaties kwam er op 26-08 bij, vóór Nieuws (feedback
        // Jimmy). `strpos` op de eerste treffer volstaat: de items staan één
        // keer in het desktopmenu, in boomvolgorde.
        $positions = [
            'Aanbod' => strpos($html, 'Aanbod'),
            'Over ons' => strpos($html, 'Over ons'),
            'Service' => strpos($html, 'Service'),
            'Realisaties' => strpos($html, 'Realisaties'),
            'Nieuws' => strpos($html, 'Nieuws'),
            'Contact' => strpos($html, 'Contact'),
        ];

        foreach ($positions as $title => $position) {
            $this->assertNotFalse($position, "'{$title}' staat niet in het menu.");
        }

        $sorted = $positions;
        asort($sorted);

        $this->assertSame(array_keys($positions), array_keys($sorted));
    }

    public function test_desktop_nav_landmark_uses_the_lang_file_label(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('aria-label="Hoofdnavigatie"', $html);
    }

    public function test_mobile_toggle_carries_the_open_label_from_the_lang_file(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('aria-label="Menu openen"', $html);
        $this->assertStringContainsString('data-label-open="Menu openen"', $html);
        $this->assertStringContainsString('data-label-close="Menu sluiten"', $html);
    }

    public function test_mobile_panel_has_accessible_name_from_lang_file(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertMatchesRegularExpression(
            '/role="dialog"[^>]*aria-label="Hoofdnavigatie"/',
            $html,
        );
    }

    public function test_header_carries_a_quote_button_from_the_lang_file(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('Gratis offerte', $html);
        $this->assertStringContainsString('href="/offerte"', $html);
    }

    /**
     * Drie sites sinds 26-08: de pill is een echte taalwissel. De knop
     * belooft met aria-expanded een paneel, en dat paneel linkt naar de
     * vertaling van de huidige pagina in de twee andere talen.
     */
    public function test_language_pill_opens_the_other_languages(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Taal: Nederlands', $html);
        $this->assertStringContainsString(':aria-expanded="open"', $html);
        $this->assertMatchesRegularExpression('~<a[^>]+href="[^"]*/fr"[^>]*>\s*FR~s', $html);
        $this->assertMatchesRegularExpression('~<a[^>]+href="[^"]*/en"[^>]*>\s*EN~s', $html);
    }

    public function test_mobile_panel_repeats_the_quote_button_and_language_pill(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Twee keer: één keer in de desktopheader, één keer in het mobiele
        // paneel. Figma tekent geen open-state voor mobiel; dit is ingevuld.
        $this->assertSame(2, substr_count($html, 'href="/offerte"'));
        $this->assertSame(2, substr_count($html, 'Taal: Nederlands'));
    }

    public function test_mobile_panel_links_straight_to_the_range_overview(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Op mobiel is Aanbod een gewone link; het mega menu rendert alleen
        // vanaf `lg`. Twee treffers: één uit het mobiele navigatie-item, één
        // uit het CTA-blok van het mega menu. Het desktop-item is een
        // `<button>` zonder href, dus die telt niet mee.
        $this->assertSame(2, substr_count($html, 'href="/aanbod"'));
    }

    public function test_the_mobile_quote_button_uses_an_existing_button_variant(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // `btn--accent` bestond niet in button.css: de knop viel terug op de
        // kale `btn` en stond zonder vlak in het paneel. Een klasse die nergens
        // wordt gedefinieerd faalt geruisloos, vandaar de expliciete claim.
        $this->assertStringContainsString('btn btn--primary', $html);
        $this->assertStringNotContainsString('btn--accent', $html);
    }

    public function test_the_mobile_panel_pill_stays_light_on_a_dark_page(): void
    {
        // Het paneel is zwart, ook op een pagina waar de nav zelf zwart is. Zou
        // de pill de scope van de partial erven, dan werd hij zwart-op-zwart —
        // precies wat er gebeurde toen `inverse` niet expliciet werd meegegeven.
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('nav-link--dark', $html);
        $this->assertStringContainsString('border-white/40 text-white', $html);
    }

    public function test_the_hamburger_switches_colour_with_the_inverse_flag(): void
    {
        // `.hamburger` staat ongelaagd in hamburger.css en verslaat daarmee elke
        // Tailwind-utility uit `@layer utilities`. De arbitraire property die
        // hier stond stond dus wél in de HTML maar deed niets: zwarte streepjes
        // over een donkere foto. Alleen een klasse uit hetzelfde bestand wint.
        $donker = $this->render('{{ partial:navigation }}');
        $licht = $this->render('{{ partial:navigation inverse="true" }}');

        $this->assertStringNotContainsString('hamburger--light', $donker);
        $this->assertStringContainsString('hamburger--light', $licht);
        $this->assertStringNotContainsString('--hamburger-color', $licht);
    }

    public function test_the_links_switch_hover_variant_with_the_inverse_flag(): void
    {
        // De inverse-tak is op de helft van de pagina's onzichtbaar: alleen de
        // product-header laat de nav wit over een foto zweven. Een fout daarin
        // valt op een gewone pagina nooit op.
        $donker = $this->render('{{ partial:navigation }}');
        $licht = $this->render('{{ partial:navigation inverse="true" }}');

        $this->assertStringContainsString('nav-link--dark', $donker);
        $this->assertStringNotContainsString('nav-link--light', $donker);

        $this->assertStringContainsString('nav-link--light', $licht);
        $this->assertStringNotContainsString('nav-link--dark', $licht);
    }
}
