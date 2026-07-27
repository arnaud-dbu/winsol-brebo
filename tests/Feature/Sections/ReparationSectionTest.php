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
    }

    public function test_renders_the_decorative_watermark_out_of_the_accessibility_tree(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        // Specifiek op de watermerk-wrapper: `aria-hidden="true"` alléén
        // checken is niet genoeg, want de fixture's `overline` laat
        // sectionHeader ook een overline__rule renderen die onvoorwaardelijk
        // `aria-hidden="true"` heeft. Koppel de assertie aan de `-z-10`-klasse
        // die alleen op de watermerk-wrapper staat.
        $this->assertStringContainsString(
            'class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true"',
            $html
        );
    }

    public function test_renders_without_an_image(): void
    {
        // De koffer is decoratief. Ontbreekt hij, dan mag de sectie niet breken
        // en mag er geen lege beeldkolom overblijven.
        $reparation = $this->reparation();
        unset($reparation['image']);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $reparation,
        ]);

        $this->assertStringContainsString('id="herstelling"', $html);
        $this->assertStringNotContainsString('reparation__media', $html);
    }

    public function test_renders_nothing_without_a_reparation_group(): void
    {
        $html = $this->render('{{ partial src="sections/reparation" }}');

        $this->assertStringNotContainsString('id="herstelling"', $html);
    }
}
