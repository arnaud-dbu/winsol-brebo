<?php

namespace Tests\Feature\Sections;

class CardTest extends SectionTestCase
{
    /**
     * De beeldkolom rendert alleen mét beeld, dus de fixture draagt er een.
     * Een absolute URL volstaat: `App\Tags\Img` valt daarvoor terug op zijn
     * passthrough-tak, zodat deze test over de opmaak kan gaan en niet over
     * assetresolutie.
     */
    private array $context = [
        'title' => 'Sfeervolle ledverlichting',
        'text' => '<p>Dimbare spots in de lamellen.</p>',
        'features' => [['label' => 'Dimbaar via app']],
        'image' => 'https://example.com/pergola.jpg',
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

    /**
     * Zonder beeld hoort er geen beeldkolom te staan. Deed hij dat wel, dan
     * bleef er een lege doos van `aspect-3/2` over — zichtbaar als een gat
     * boven de tekst, precies wat op de Pergola SO!-pagina opviel.
     */
    public function test_omits_the_media_column_without_an_image(): void
    {
        $html = $this->render('{{ partial:card }}', [
            'title' => 'Alleen tekst',
            'text' => '<p>Geen foto bij deze kaart.</p>',
        ]);

        $this->assertStringNotContainsString('aspect-3/2', $html);
        $this->assertStringNotContainsString('@lg:w-1/3', $html);
        $this->assertStringContainsString('Alleen tekst', $html);
    }

    /**
     * De overline hoort uit de kaart zelf te komen. De card-set in
     * `page_builder.yaml` kent dat veld niet, dus alles wat hier verschijnt is
     * doorgevallen uit de omringende scope — en dat was de overline van de
     * sectie, in elke kaart opnieuw.
     */
    public function test_the_section_overline_does_not_leak_into_the_card(): void
    {
        $html = $this->render(
            '{{ overline = "Dakvarianten" }}{{ partial:card overline="" }}',
            ['title' => 'SO! Classic'],
        );

        $this->assertStringNotContainsString('Dakvarianten', $html);
        $this->assertStringContainsString('SO! Classic', $html);
    }
}
