<?php

namespace Tests\Feature\Sections;

class ServiceNavTest extends SectionTestCase
{
    private function services(): array
    {
        return [
            ['overline' => 'Advies', 'title' => 'Advies op maat'],
            ['overline' => 'Installatie', 'title' => 'Vakkundige installatie'],
            ['overline' => 'Onderhoud', 'title' => 'Onderhoud en nazicht'],
            ['overline' => 'Garantie', 'title' => 'Garantie en nazorg'],
        ];
    }

    public function test_renders_one_link_per_service_in_order(): void
    {
        $html = $this->render('{{ partial:sectionNav }}', ['services' => $this->services()]);

        $this->assertStringContainsString('href="#advies"', $html);
        $this->assertStringContainsString('href="#installatie"', $html);
        $this->assertStringContainsString('href="#onderhoud"', $html);
        $this->assertStringContainsString('href="#garantie"', $html);

        $this->assertLessThan(
            strpos($html, 'href="#garantie"'),
            strpos($html, 'href="#advies"'),
            'De volgorde van de pills moet die van de services volgen.'
        );
    }

    public function test_the_anchor_matches_the_slug_of_the_overline(): void
    {
        // Dit is het contract met textImage: het template geeft daar
        // `overline | slugify` als anker mee. Wijkt de slugificatie hier af,
        // dan wijst de pill naar een sectie die niet bestaat.
        $html = $this->render('{{ partial:sectionNav }}', [
            'services' => [['overline' => 'Advies op Maat']],
        ]);

        $this->assertStringContainsString('href="#advies-op-maat"', $html);
    }

    public function test_skips_a_service_without_an_overline(): void
    {
        // Zonder overline is er geen anker om naartoe te springen. De guard is
        // load-bearing: zonder hem rendert er een pill met href="#".
        $html = $this->render('{{ partial:sectionNav }}', [
            'services' => [
                ['overline' => 'Advies'],
                ['title' => 'Sectie zonder overline'],
            ],
        ]);

        // De pills zijn sinds a257ed5 gewone `btn--outline`-knoppen: die utility
        // kreeg in 95da753 `border-black/20`, precies de lichte rand waarvoor
        // section-nav.css eerder een eigen klasse aanhield.
        // Prettier sorteert de utilities: btn voor btn--outline.
        $this->assertSame(1, substr_count($html, 'class="btn btn--outline"'));
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_links_to_the_reparation_section(): void
    {
        $html = $this->render('{{ partial:sectionNav }}', ['services' => $this->services()]);

        $this->assertStringContainsString('href="#herstelling"', $html);
        $this->assertStringContainsString('Herstelling melden', $html);
        // De donkere meldknop is nu `btn--secondary` (bg-black text-white).
        $this->assertStringContainsString('btn btn--secondary', $html);
    }

    public function test_is_hidden_below_the_lg_breakpoint(): void
    {
        $html = $this->render('{{ partial:sectionNav }}', ['services' => $this->services()]);

        $this->assertStringContainsString('hidden lg:block', $html);
    }

    public function test_renders_nothing_without_services(): void
    {
        $html = $this->render('{{ partial:sectionNav }}');

        $this->assertStringNotContainsString('<nav', $html);
    }
}
