<?php

namespace Tests\Feature\Sections;

class MegaMenuTest extends SectionTestCase
{
    public function test_categories_appear_in_the_order_of_their_order_field(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De drie termen uit content/taxonomies/range_categories/, gesorteerd op
        // hun `order`-veld (1, 2, 3). `&` komt door `| entities` als `&amp;`.
        $voor = strpos($html, 'Voor je woning');
        $rondom = strpos($html, 'Rondom je woning');
        $slim = strpos($html, 'Slim &amp; comfort');

        $this->assertNotFalse($voor);
        $this->assertNotFalse($rondom);
        $this->assertNotFalse($slim);
        $this->assertLessThan($rondom, $voor);
        $this->assertLessThan($slim, $rondom);
    }

    public function test_every_range_appears_with_its_short_description(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Titel plus omschrijving, zodat een kolom die alleen titels rendert
        // hier alsnog op valt. De omschrijvingen staan in
        // content/collections/ranges/*.md.
        $this->assertStringContainsString('Ramen en deuren', $html);
        $this->assertStringContainsString('Ramen en deuren in aluminium of PVC, op maat gemaakt voor een strakke afwerking en goede isolatie.', $html);
        $this->assertStringContainsString('Rolluiken', $html);
        $this->assertStringContainsString('Rolluiken voor ramen en deuren die inbraakwerend zijn en helpen tegen warmte, licht en geluid.', $html);
        $this->assertStringContainsString('Somfy Smart Home', $html);
        $this->assertStringContainsString('Somfy-sturing waarmee rolluiken, zonwering en verlichting samenwerken via één app of afstandsbediening.', $html);

        // Alle negen ranges, niet slechts de drie hierboven met naam genoemde.
        // Het mobiele paneel toont geen ranges, dus negen is exact.
        $this->assertSame(9, substr_count($html, 'href="/aanbod/'));
    }

    public function test_ranges_within_a_category_follow_their_order_field(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // "Voor je woning": order 1, 2, 3, 4.
        $ramen = strpos($html, 'Ramen en deuren');
        $stalen = strpos($html, 'Stalen binnendeuren');
        $velux = strpos($html, 'VELUX dakramen');
        $airco = strpos($html, 'Airco');

        $this->assertLessThan($stalen, $ramen);
        $this->assertLessThan($velux, $stalen);
        $this->assertLessThan($airco, $velux);
    }

    public function test_each_range_links_to_its_own_page(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De route van de ranges-collectie is /aanbod/{slug}.
        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
        $this->assertStringContainsString('href="/aanbod/velux"', $html);
        $this->assertStringContainsString('href="/aanbod/somfy-smart-home"', $html);
    }

    public function test_panel_carries_a_link_to_the_full_range_overview(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('Niet zeker welke oplossing past?', $html);
        $this->assertStringContainsString('Volledig aanbod', $html);
        $this->assertStringContainsString('href="/aanbod"', $html);

        // Het blok hangt onder de laatste categoriekolom via `is_last_category`.
        // Telt, want een verschoven scope zou het nul of drie keer opleveren en
        // een `contains`-assertie blijft in beide gevallen groen.
        $this->assertSame(1, substr_count($html, 'Niet zeker welke oplossing past?'));
        $this->assertSame(1, substr_count($html, 'Volledig aanbod'));
    }

    public function test_only_the_flagged_item_opens_a_panel(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Vijf items, precies één knop die een paneel bestuurt. Dit is het
        // bewijs dat het paneel uit de `mega_menu`-vlag komt en niet uit
        // markup die op elk item is geplakt.
        $this->assertSame(1, substr_count($html, 'aria-controls="mega-menu-panel"'));
        $this->assertSame(1, substr_count($html, 'id="mega-menu-panel"'));
    }

    public function test_toggle_reports_its_state_to_assistive_technology(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString(':aria-expanded="open.toString()"', $html);
    }
}
