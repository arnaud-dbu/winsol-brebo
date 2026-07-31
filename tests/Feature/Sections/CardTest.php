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
    public function test_stacks_the_card_while_its_container_is_narrow(): void
    {
        $html = $this->render('{{ partial:card }}', $this->context);

        $this->assertStringContainsString('@container', $html);
        $this->assertStringContainsString('flex-col', $html);
        $this->assertStringContainsString('Sfeervolle ledverlichting', $html);
        $this->assertStringContainsString('feature-list', $html);
    }

    /**
     * Omklappen is twee dingen tegelijk, op hetzelfde containerbreekpunt: de rij
     * wordt een rij, én de beeldkolom krijgt een eigen breedte. Zonder dat
     * tweede deel staat het beeld op volle breedte naast de tekst en is de
     * horizontale kaart alsnog stuk.
     */
    public function test_turns_horizontal_from_the_lg_container_width(): void
    {
        $html = $this->render('{{ partial:card }}', $this->context);

        $this->assertStringContainsString('@lg:flex-row', $html);
        $this->assertStringContainsString('@lg:w-1/3', $html);
        $this->assertStringContainsString('@lg:shrink-0', $html);
    }

    public function test_omits_feature_list_when_absent(): void
    {
        $html = $this->render('{{ partial:card }}', ['title' => 'Alleen een titel']);

        $this->assertStringNotContainsString('feature-list', $html);
    }
}
