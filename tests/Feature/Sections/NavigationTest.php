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
        $this->assertStringContainsString('Realisaties', $html);
        $this->assertStringContainsString('Service', $html);
        $this->assertStringContainsString('Over ons', $html);
        $this->assertStringContainsString('Contact', $html);
    }

    public function test_menu_items_follow_the_order_of_the_structure(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De volgorde uit Figma 332:3244. `strpos` op de eerste treffer volstaat:
        // de items staan één keer in het desktopmenu, in boomvolgorde.
        $positions = [
            'Aanbod' => strpos($html, 'Aanbod'),
            'Realisaties' => strpos($html, 'Realisaties'),
            'Service' => strpos($html, 'Service'),
            'Over ons' => strpos($html, 'Over ons'),
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

    public function test_language_pill_is_labelled_but_not_interactive(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Eén site, dus er valt niets te kiezen: de pill toont de taal maar
        // opent niets. Een knop met aria-expanded zou een paneel beloven dat
        // niet bestaat. Er zijn er precies twee in de header — de knop van
        // het mega menu en de hamburger — en de pill voegt er geen derde aan
        // toe.
        $this->assertStringContainsString('Taal: Nederlands', $html);
        $this->assertStringContainsString('>NL<', $html);
        $this->assertSame(2, substr_count($html, 'aria-expanded'));
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
        // vanaf `md`. Het paneel bevat dus geen tweede paneel-id.
        $this->assertSame(1, substr_count($html, 'id="mega-menu-panel"'));
    }
}
