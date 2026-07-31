<?php

namespace Tests\Feature\Sections;

class CardTest extends SectionTestCase
{
    private array $context = [
        'title' => 'Sfeervolle ledverlichting',
        'text' => '<p>Dimbare spots in de lamellen.</p>',
        'features' => [['label' => 'Dimbaar via app']],
    ];

    /**
     * De kaart kent geen `layout`-argument meer. Sinds 95da753 leest hij zijn
     * eigen breedte via `@container`: smal blijft hij gestapeld, vanaf de
     * `@lg`-containerbreedte staan beeld en tekst naast elkaar. De klassen
     * `card--vertical` en `card--horizontal` zijn daarbij uit card.css
     * verdwenen; de richting hangt nu aan de container, niet aan een vlag.
     */
    public function test_renders_vertical_card_by_default(): void
    {
        $html = $this->render('{{ partial:card }}', $this->context);

        $this->assertStringContainsString('@container', $html);
        $this->assertStringContainsString('flex-col', $html);
        $this->assertStringContainsString('Sfeervolle ledverlichting', $html);
        $this->assertStringContainsString('feature-list', $html);
    }

    public function test_renders_horizontal_card(): void
    {
        $html = $this->render('{{ partial:card }}', $this->context);

        $this->assertStringContainsString('@lg:flex-row', $html);
        $this->assertStringNotContainsString('card--', $html);
    }

    public function test_omits_feature_list_when_absent(): void
    {
        $html = $this->render('{{ partial:card }}', ['title' => 'Alleen een titel']);

        $this->assertStringNotContainsString('feature-list', $html);
    }
}
