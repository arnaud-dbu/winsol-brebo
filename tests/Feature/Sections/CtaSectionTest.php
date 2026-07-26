<?php

namespace Tests\Feature\Sections;

class CtaSectionTest extends SectionTestCase
{
    public function test_renders_full_bleed_panel_with_inverse_header(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', [
            'overline' => 'Over ons',
            'title' => 'Lokale verkooppunten, eigen vakmensen',
        ]);

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('section-header--inverse', $html);
        $this->assertStringContainsString('overline--inverse', $html);
        $this->assertStringNotContainsString('class="container"', $html);
    }
}
