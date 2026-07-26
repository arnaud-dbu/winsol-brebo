<?php

namespace Tests\Feature\Sections;

class FooterTest extends SectionTestCase
{
    /**
     * B1 deliberately nulls the starter kit's demo Contact details in
     * content/globals/default/globals.yaml (fake agency phone/email), so
     * the Contact `footer__column` is expected to be hidden by its `{{ if }}`
     * guard. This test therefore only covers the two columns that are
     * genuinely populated by real site content (ranges + main nav), and the
     * legal links in the colophon — never the contact data that was removed.
     */
    public function test_renders_populated_link_columns_and_a_colophon(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertSame(2, substr_count($html, 'footer__column'));
        $this->assertStringContainsString('footer__colophon', $html);
        $this->assertStringContainsString('BY BREBO', $html);

        // The `ranges` collection loop (9 seeded ranges under /aanbod/*).
        $this->assertSame(9, substr_count($html, 'href="/aanbod/'));

        // The `nav:main` loop (Over ons, Projecten, Contact) actually renders.
        $this->assertSame(3, substr_count($html, 'href="/over-ons"')
            + substr_count($html, 'href="/cases"')
            + substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Projecten', $html);

        // The legal collection (3 seeded entries) renders in the colophon.
        $this->assertSame(3, substr_count($html, 'href="/cookie-policy"')
            + substr_count($html, 'href="/privacy-policy"')
            + substr_count($html, 'href="/toegankelijkheidsverklaring"'));
    }
}
