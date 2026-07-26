<?php

namespace Tests\Feature\Sections;

class TextImageSectionTest extends SectionTestCase
{
    public function test_renders_header_and_features(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Pergola SO!',
            'text' => '<p>Met draaibare lamellen.</p>',
            'features' => [['label' => 'Bediening via app']],
        ]);

        $this->assertStringContainsString('data-section="text_image"', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('Bediening via app', $html);
    }

    public function test_adds_background_modifier_when_toggled(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('text-image--background', $html);
    }

    public function test_omits_background_modifier_by_default(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Pergola SO!']);

        $this->assertStringNotContainsString('text-image--background', $html);
    }
}
