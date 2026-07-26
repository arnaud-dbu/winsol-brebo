<?php

namespace Tests\Feature\Sections;

class RangeHeaderTest extends SectionTestCase
{
    public function test_renders_title_and_short_description(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'short_description' => 'Geniet het hele jaar van uw terras.',
            'long_description' => 'Deze hoort in de sectie eronder, niet in de header.',
        ]);

        $this->assertStringContainsString('data-header="range"', $html);
        $this->assertStringContainsString('Terrasoverkapping', $html);
        $this->assertStringContainsString('Geniet het hele jaar van uw terras.', $html);
        $this->assertStringNotContainsString('Deze hoort in de sectie eronder', $html);

        // Pin de layering-workaround (zie header.css): `.header-title` en
        // `.header-intro` declareren hun eigen `font-size` omdat een
        // `text-*`-utility op een `h1`/`p` niets doet (ongelaagde CSS wint
        // altijd). Zonder deze assertie zou het vervangen van deze classes
        // door bv. `text-display` alle 17 bestaande tests groen laten, terwijl
        // de tekst stilletjes van 76px naar 61px zakt.
        $this->assertStringContainsString('<h1 class="header-title">Terrasoverkapping</h1>', $html);
        $this->assertStringContainsString('<p class="header-intro">Geniet het hele jaar van uw terras.</p>', $html);
    }

    public function test_renders_the_watermark_inside_the_clipping_layer(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => '/img/pergolas.png',
        ]);

        // De kern van dit component: het watermerk wordt geklipt en de
        // range-png niet. Als die twee ooit in dezelfde box belanden, klopt
        // één van beide niet meer — vandaar dat de volgorde en nesting hier
        // expliciet worden vastgelegd.
        $clip = strpos($html, 'data-header-watermark');
        $media = strpos($html, 'data-header-media');

        $this->assertNotFalse($clip);
        $this->assertNotFalse($media);
        $this->assertLessThan($media, $clip, 'Het watermerk staat vóór de png in de markup.');

        // Het watermerk zit in een klippende wrapper, de png staat erbuiten.
        $this->assertMatchesRegularExpression(
            '/data-header-watermark[^>]*class="[^"]*overflow-hidden/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-header-media[^>]*class="[^"]*overflow-hidden/',
            $html
        );

        // De negatieve assertie hierboven bewijst alleen dat de media-div
        // niet klipt. Ze bewijst niet dat de sectie zelf niet klipt: zet
        // `overflow-hidden` op de <section> en de png wordt alsnog geklipt —
        // precies de regressie die dit component moet voorkomen — terwijl
        // bovenstaande assertie groen zou blijven. Vandaar deze extra check
        // op de sectie's eigen class-lijst.
        $this->assertMatchesRegularExpression(
            '/<section[^>]*data-header="range"[^>]*>/',
            $html
        );
        preg_match('/<section[^>]*data-header="range"[^>]*>/', $html, $sectionTag);
        $this->assertDoesNotMatchRegularExpression('/overflow-/', $sectionTag[0]);
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);

        // Het watermerk hangt niet aan `image` en blijft de header dragen.
        $this->assertStringContainsString('data-header-watermark', $html);
    }

    public function test_ranges_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/ranges/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/ranges.yaml'));
        $this->assertStringContainsString('template: ranges/show', $yaml);

        $view = file_get_contents(resource_path('views/ranges/show.antlers.html'));
        $this->assertStringContainsString('headers/range', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
