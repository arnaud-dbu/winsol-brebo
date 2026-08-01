<?php

namespace Tests\Feature\Sections;

class ReparationSectionTest extends SectionTestCase
{
    private function reparation(): array
    {
        return [
            'overline' => 'Herstelling',
            'title' => 'Iets stuk of werkt iets niet meer?',
            'text' => 'Voor bestaande klanten met een probleem.',
            'image' => 'quicklinks/herstelling.png',
        ];
    }

    public function test_carries_the_anchor_the_nav_links_to(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        // Het contract met sectionNav: die rendert href="#herstelling".
        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        $this->assertStringContainsString('id="herstelling"', $html);
        $this->assertStringContainsString('data-section="reparation"', $html);
    }

    public function test_renders_the_header_and_the_form(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        $this->assertStringContainsString('Iets stuk of werkt iets niet meer?', $html);
        $this->assertStringContainsString('Herstelling', $html);
        $this->assertStringContainsString('class="form-section"', $html);
        // De negatieve marge is de enige reden dat het beeldblok een eigen
        // wrapper heeft; zie het commentaar bij `lg:-ml-24` hierboven en de
        // afwezigheidscontrole in test_renders_without_an_image.
        $this->assertStringContainsString('lg:-ml-24', $html);
    }

    public function test_renders_the_decorative_watermark_out_of_the_accessibility_tree(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        // Op de wrapper en niet op de svg: in Figma is het watermerk (`361:4101`)
        // een kind van het hele herstellingsframe, niet van de koffer. De
        // wrapper draagt daarom zowel de aria-hidden als de plaatsing.
        //
        // `aria-hidden="true"` alléén checken is niet genoeg, want de fixture's
        // `overline` laat sectionHeader ook een overline__rule renderen die
        // onvoorwaardelijk `aria-hidden="true"` heeft. Koppel de assertie aan de
        // `-z-10`-klasse die alleen op het watermerk staat, via een regex in
        // plaats van de volledige `class="…"`-string: die laatste zou al rood
        // slaan zodra Prettier de Tailwind-klassen herordent, en dekte niet de
        // vraag of die klasse ook daadwerkelijk iets doet (zie de volgende test).
        $this->assertMatchesRegularExpression(
            '/<div[^>]*\bclass="[^"]*-z-10[^"]*"[^>]*\baria-hidden="true"/',
            $html
        );
    }

    public function test_the_section_creates_a_stacking_context_so_the_watermark_is_not_covered(): void
    {
        // `position: relative` met `z-index: auto` creëert géén stacking
        // context, en `overflow-hidden` ook niet. Zonder `isolate` ontsnapt de
        // `-z-10` watermerk-wrapper naar de dichtstbijzijnde voorouder-
        // stacking-context en tekent hij vóór de dekkende `bg-light` van de
        // sectie zelf — het watermerk is dan onzichtbaar, ook al staat de
        // vorige test nog steeds groen. Zie finding 1 in de branch-review.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        $this->assertMatchesRegularExpression(
            '/<section[^>]*\bid="herstelling"[^>]*\bclass="[^"]*\bisolate\b/',
            $html
        );
    }

    public function test_renders_without_an_image(): void
    {
        // De koffer is decoratief. Ontbreekt hij, dan mag de sectie niet breken
        // en mag er geen lege beeldkolom overblijven. `lg:-ml-24` is de echte
        // opmaak die het beeldblok nodig heeft (zie het commentaar bij de
        // negatieve marge in reparation.antlers.html); zonder beeld hoort die
        // klasse dus ook niet te verschijnen.
        $reparation = $this->reparation();
        unset($reparation['image']);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $reparation,
        ]);

        $this->assertStringContainsString('id="herstelling"', $html);
        $this->assertStringNotContainsString('lg:-ml-24', $html);

        // Het watermerk hangt aan de sectie en niet aan het beeld: in Figma
        // spant het over het hele herstellingsframe, achter tekst én formulier.
        // Valt de koffer weg, dan blijft de vorm dus staan.
        $this->assertMatchesRegularExpression(
            '/<div[^>]*\bclass="[^"]*-z-10[^"]*"[^>]*\baria-hidden="true"/',
            $html
        );
    }

    public function test_renders_nothing_without_a_reparation_group(): void
    {
        $html = $this->render('{{ partial src="sections/reparation" }}');

        $this->assertStringNotContainsString('id="herstelling"', $html);
    }
}
