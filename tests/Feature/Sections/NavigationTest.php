<?php

namespace Tests\Feature\Sections;

class NavigationTest extends SectionTestCase
{
    public function test_menu_is_driven_by_the_main_navigation_structure(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // These titles come from content/trees/navigation/main.yaml, not from the
        // template — proves the menu is not a hardcoded list of links.
        $this->assertStringContainsString('Over ons', $html);
        $this->assertStringContainsString('Projecten', $html);
        $this->assertStringContainsString('Contact', $html);
    }

    public function test_menu_does_not_hardcode_a_fake_aanbod_dropdown(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // The `main` structure currently has no nested items, so no dropdown
        // markup (and no "Aanbod" label, which isn't part of the structure)
        // should be faked into the output.
        $this->assertStringNotContainsString('Aanbod', $html);
        $this->assertStringNotContainsString(':aria-expanded="open.toString()"', $html);
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
}
