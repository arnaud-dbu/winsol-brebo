<?php

namespace Tests\Feature\Sections;

class CardTest extends SectionTestCase
{
    private array $context = [
        'title' => 'Sfeervolle ledverlichting',
        'text' => '<p>Dimbare spots in de lamellen.</p>',
        'features' => [['label' => 'Dimbaar via app']],
    ];

    public function test_renders_vertical_card_by_default(): void
    {
        $html = $this->render('{{ partial:card }}', $this->context);

        $this->assertStringContainsString('card--vertical', $html);
        $this->assertStringContainsString('Sfeervolle ledverlichting', $html);
        $this->assertStringContainsString('feature-list', $html);
    }

    public function test_renders_horizontal_card(): void
    {
        $html = $this->render('{{ partial:card layout="horizontal" }}', $this->context);

        $this->assertStringContainsString('card--horizontal', $html);
        $this->assertStringNotContainsString('card--vertical', $html);
    }

    public function test_omits_feature_list_when_absent(): void
    {
        $html = $this->render('{{ partial:card }}', ['title' => 'Alleen een titel']);

        $this->assertStringNotContainsString('feature-list', $html);
    }
}
