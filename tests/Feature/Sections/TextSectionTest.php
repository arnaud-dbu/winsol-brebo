<?php

namespace Tests\Feature\Sections;

class TextSectionTest extends SectionTestCase
{
    public function test_renders_title_and_body_in_two_columns(): void
    {
        $html = $this->render('{{ partial src="sections/text" }}', [
            'title' => 'Van de draaibare lamellen tot de avondsfeer',
            'text' => '<p>De oplossing werd een aangebouwde Pergola SO!</p>',
        ]);

        $this->assertStringContainsString('data-section="text"', $html);
        $this->assertStringContainsString('Van de draaibare lamellen', $html);
        $this->assertStringContainsString('aangebouwde Pergola SO!', $html);
        $this->assertStringContainsString('section-x-gap', $html);
    }
}
