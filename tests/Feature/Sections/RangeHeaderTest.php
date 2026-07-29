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

    /**
     * Elke range-png is een vierkant canvas met het product gecentreerd en
     * een wisselende hoeveelheid transparante lucht eromheen. Een vierkante
     * box met `object-contain` is precies wat het optische midden van het
     * product op het midden van de box legt, ongeacht welke range er staat.
     *
     * `ratio` zou dat kapotmaken: die crop't via `crop_focal` en levert per
     * beeld een ander midden op. Vandaar dat beide kanten hier vastliggen.
     */
    public function test_the_media_is_a_square_contain_box(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => '/img/pergolas.png',
        ]);

        $this->assertMatchesRegularExpression(
            '/data-header-media[^>]*class="[^"]*aspect-square/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<img[^>]*class="[^"]*object-contain/',
            $html
        );

        // Geen crop: een `ratio` op {{ img }} zet `fit=crop_focal` in de
        // Glide-URL en zou het product aansnijden.
        $this->assertStringNotContainsString('crop_focal', $html);
    }

    /**
     * Het beeld steekt links voorbij de paginamarge het beeld uit. Dat kan
     * alleen als niets ertussen klipt — de sectie niet (dat pint de test
     * hierboven) en de container niet. Zonder deze assertie zou een
     * `overflow-hidden` op de container het beeld stilletjes bijsnijden op
     * exact de marge, wat er "bijna goed" uitziet.
     */
    public function test_the_media_bleeds_past_the_page_margin(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => '/img/pergolas.png',
        ]);

        $this->assertMatchesRegularExpression(
            '/data-header-media[^>]*class="[^"]*lg:-ml-/',
            $html
        );

        // Precies één klippende laag in deze header, en dat is die van het
        // watermerk. Een `overflow-hidden` erbij — op de sectie, de container
        // of de media zelf — snijdt het beeld af op exact de marge, wat er
        // "bijna goed" uitziet en dus makkelijk ongemerkt binnenglipt.
        $this->assertSame(1, substr_count($html, 'overflow-'));
    }

    /**
     * De nav zweeft over deze header, maar blijft zwart: de achtergrond is
     * licht. Dat is precies waarom `floating` en `inverse` twee vlaggen zijn
     * — zie ProductHeaderTest voor de kant waar ze allebei aanstaan.
     *
     * Het lichte vlak loopt daardoor door achter de nav, dus de header moet
     * die hoogte zelf overslaan. Beide kanten staan hier samen: verdwijnt de
     * ene, dan klopt de andere niet meer.
     */
    public function test_the_layout_floats_a_dark_navigation_over_this_header(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $this->assertMatchesRegularExpression(
            '/nav_floats\s*=\s*nav_over_photo\s*\|\|\s*template == \'ranges\/show\'/',
            $layout
        );

        // Wél zwevend, niet inverse: `inverse` hangt alleen aan de foto-header.
        $this->assertStringNotContainsString("nav_over_photo = template == 'ranges/show'", $layout);

        config(['app.debug' => false]);
        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
        ]);

        $this->assertStringContainsString('header-under-nav', $html);
    }

    /**
     * `--nav-height` stuurt zowel de nav-hoogte als de padding van deze
     * header. Zou de nav zijn hoogte weer uit padding halen, dan schuift de
     * H1 onder de zwevende nav zonder dat één test rood wordt.
     */
    public function test_the_nav_height_variable_drives_both_sides(): void
    {
        $nav = file_get_contents(resource_path('views/partials/navigation.antlers.html'));
        $this->assertStringContainsString('nav-bar', $nav);

        $navCss = file_get_contents(resource_path('css/components/navigation.css'));
        $this->assertMatchesRegularExpression('/@utility nav-bar \{\s*height: var\(--nav-height\);/', $navCss);

        $headerCss = file_get_contents(resource_path('css/components/header.css'));
        $this->assertMatchesRegularExpression(
            '/@utility header-under-nav \{\s*padding-top: calc\(var\(--nav-height\)/',
            $headerCss
        );

        $this->assertStringContainsString(
            "@import './components/navigation.css';",
            file_get_contents(resource_path('css/site.css'))
        );
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
