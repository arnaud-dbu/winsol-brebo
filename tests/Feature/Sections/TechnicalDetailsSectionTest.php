<?php

namespace Tests\Feature\Sections;

class TechnicalDetailsSectionTest extends SectionTestCase
{
    public function test_renders_one_row_per_specification(): void
    {
        $html = $this->render('{{ partial src="sections/technicalDetails" }}', [
            'title' => 'Technische specificaties',
            'technical_details' => [
                ['key' => 'Max. breedte per module', 'value' => 'tot 6,0 m'],
                ['key' => 'Lamellen openingsgraad', 'value' => '0 tot 145 graden'],
            ],
        ]);

        $this->assertStringContainsString('data-section="technical_details"', $html);
        $this->assertSame(2, substr_count($html, 'specs__row'));
        $this->assertStringContainsString('Max. breedte per module', $html);
        $this->assertStringContainsString('0 tot 145 graden', $html);
    }

    public function test_renders_header_without_rows(): void
    {
        $html = $this->render('{{ partial src="sections/technicalDetails" }}', [
            'title' => 'Technische specificaties',
        ]);

        $this->assertStringContainsString('Technische specificaties', $html);
        $this->assertStringNotContainsString('specs__row', $html);
    }
}
