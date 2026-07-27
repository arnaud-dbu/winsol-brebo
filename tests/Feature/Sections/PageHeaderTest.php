<?php

namespace Tests\Feature\Sections;

class PageHeaderTest extends SectionTestCase
{
    public function test_renders_title_and_intro(): void
    {
        $html = $this->render('{{ partial src="headers/default" }}', [
            'title' => 'Pagebuilder',
            'text' => 'Samen je huis klaarmaken voor de toekomst.',
        ]);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Pagebuilder', $html);
        $this->assertStringContainsString('Samen je huis klaarmaken', $html);
    }

    public function test_renders_a_divider_when_asked(): void
    {
        $html = $this->render('{{ partial src="headers/default" divider="true" }}', [
            'title' => 'Ons aanbod',
        ]);

        // Geankerd op de markup van de lijn zelf. `page-header__divider` stond
        // hier eerder, maar die klasse kwam in geen enkel CSS-bestand voor —
        // een haakje dat alleen bestond omdat een test ernaar keek.
        $this->assertStringContainsString('border-t border-black/10', $html);
        $this->assertStringContainsString('lg:block', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_renders_no_divider_by_default(): void
    {
        $html = $this->render('{{ partial src="headers/default" }}', [
            'title' => 'Ons aanbod',
        ]);

        $this->assertStringNotContainsString('border-t border-black/10', $html);
        // De twee assertions op exacte witruimte die hier stonden, testten de
        // opmaak van het bestand en niet het gedrag van de partial: ze vielen
        // om zodra iemand de template opnieuw inspringde. De structuur die er
        // wél toe doet, staat hieronder.
        $this->assertStringContainsString('<section class="section--default', $html);
        $this->assertStringContainsString('class="container">', $html);
        $this->assertStringContainsString('<h1>Ons aanbod</h1>', $html);
    }
}
