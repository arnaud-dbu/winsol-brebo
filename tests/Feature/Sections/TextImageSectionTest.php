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

    public function test_renders_the_anchor_as_a_section_id(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" anchor="advies" }}', [
            'title' => 'Advies op maat',
        ]);

        $this->assertStringContainsString('id="advies"', $html);
    }

    public function test_omits_the_id_attribute_without_an_anchor(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Advies op maat']);

        $this->assertStringNotContainsString('id="', $html);
    }

    public function test_puts_the_text_column_last_by_default(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Advies op maat']);

        $this->assertStringContainsString('order-last', $html);
    }

    public function test_text_first_moves_the_text_column_ahead_of_the_image(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" text_first="true" }}', [
            'title' => 'Vakkundige installatie',
        ]);

        $this->assertStringNotContainsString('order-last', $html);
    }

    public function test_background_still_wins_over_text_first(): void
    {
        // `background` zette de tekstkolom al vooraan, mét lichte kaart. Dat gedrag
        // mag niet veranderen nu `text_first` hetzelfde effect langs een andere weg
        // bereikt: de acht bestaande aanroepers geven `text_first` nooit mee.
        $html = $this->render('{{ partial src="sections/textImage" text_first="true" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('bg-light card-padding', $html);
        $this->assertStringNotContainsString('order-last', $html);
    }
}
