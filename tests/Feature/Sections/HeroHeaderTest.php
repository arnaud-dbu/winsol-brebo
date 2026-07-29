<?php

namespace Tests\Feature\Sections;

class HeroHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_image_wrapper(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'text' => 'Ramen, zonwering, rolluiken en meer.',
            'image' => '/img/hero.jpg',
        ]);

        $this->assertStringContainsString('data-header="hero"', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Winsol maakt je woning compleet', $html);
        $this->assertStringContainsString('Ramen, zonwering, rolluiken en meer.', $html);
        $this->assertStringContainsString('data-header-media', $html);
    }

    public function test_renders_the_button_from_a_link(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'link' => [
                ['type' => 'url', 'url' => 'winsol.eu', 'label' => 'Ontdek ons aanbod'],
            ],
        ]);

        $this->assertStringContainsString('btn--outline', $html);
        $this->assertStringContainsString('Ontdek ons aanbod', $html);
    }

    public function test_omits_the_button_entirely_without_a_link(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        // Geen lege knop-wrapper: de home-entry heeft vandaag geen `link`
        // (er is nog geen aanbod-overzichtspagina), dus dit is de tak die
        // in productie draait.
        $this->assertStringNotContainsString('btn--outline', $html);
        $this->assertStringNotContainsString('data-header-action', $html);
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);
    }

    public function test_renders_the_heading_in_flow_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        // Zonder beeld is er niets in de flow om de sectie hoogte te geven.
        // Als de kaart dan nog `absolute inset-0` was, zou de sectie 0px
        // hoog blijven en de H1 onzichtbaar zijn.
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Winsol maakt je woning compleet', $html);
        $this->assertStringNotContainsString('absolute inset-0', $html);
    }

    public function test_loops_the_value_proposition_items(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'value_proposition' => [
                'title' => 'Waarom Winsol Brebo',
                'items' => [
                    ['icon' => 'flag', 'title' => 'Belgisch merk', 'text' => '145 jaar vakmanschap.'],
                    ['icon' => 'ruler', 'title' => 'Maatwerk', 'text' => 'Alles op maat gemaakt.'],
                    ['icon' => 'headset', 'title' => 'Lokaal en bereikbaar', 'text' => 'Drie showrooms in de buurt.'],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-header="value-proposition"', $html);
        $this->assertStringContainsString('Waarom Winsol Brebo', $html);

        // Assert op de inhoud, niet alleen op het aantal <li>: een lus die
        // driemaal hetzelfde item rendert zou een telling overleven.
        $this->assertStringContainsString('Belgisch merk', $html);
        $this->assertStringContainsString('Maatwerk', $html);
        $this->assertStringContainsString('Lokaal en bereikbaar', $html);
        $this->assertStringContainsString('Drie showrooms in de buurt.', $html);
        $this->assertSame(3, substr_count($html, '<li'));

        // De iconen worden inline gerenderd door de `icon`-tag, dus er
        // staan drie <svg>'s in de strip.
        $this->assertSame(3, substr_count($html, '<svg'));
    }

    public function test_omits_the_whole_strip_without_a_value_proposition(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        $this->assertStringNotContainsString('data-header="value-proposition"', $html);
    }

    /**
     * De nav zweeft over deze hero én is wit. Het bovenverloop is precies wat
     * die witte nav leesbaar houdt, dus als de een verdwijnt moet de ander
     * mee — vandaar dat beide kanten hier in één test staan.
     */
    public function test_the_layout_floats_a_white_navigation_over_this_header(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $this->assertMatchesRegularExpression(
            '/nav_over_photo\s*=[^}]*template == \'home\'/',
            $layout
        );
        $this->assertStringContainsString(':floating="nav_floats"', $layout);
        $this->assertStringContainsString(':inverse="nav_over_photo"', $layout);

        config(['app.debug' => false]);
        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'image' => '/img/hero.jpg',
        ]);

        $this->assertStringContainsString('from-black/50', $html);

        // De kaart staat onderaan, maar moet de zwevende nav wel overslaan:
        // op een kort scherm schuift hij anders onder het logo.
        $this->assertStringContainsString('hero-inset', $html);
        $this->assertMatchesRegularExpression(
            '/@utility hero-inset \{\s*padding-top: calc\(var\(--nav-height\)/',
            file_get_contents(resource_path('css/components/header.css'))
        );
    }

    /**
     * De hero vult het venster met een plafond erop. Beide helften horen bij
     * elkaar: zonder plafond rekt hij op een lang scherm uit tot een leeg
     * vlak, zonder `svh` springt hij mee met de mobiele adresbalk.
     */
    public function test_the_hero_fills_the_viewport_up_to_a_ceiling(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'image' => '/img/hero.jpg',
        ]);

        $this->assertStringContainsString('hero-frame', $html);

        // Het beeld dekt dat vlak in plaats van de hoogte te bepalen. Dat komt
        // uit `fill` op de tag: de fixture-url hierboven is geen echt asset,
        // dus de klassen zelf staan niet in deze render.
        $this->assertStringContainsString(
            'fill="true"',
            file_get_contents(resource_path('views/partials/headers/hero.antlers.html'))
        );

        $this->assertMatchesRegularExpression(
            '/@utility hero-frame \{\s*min-height: min\(100svh, [^)]+\);/',
            file_get_contents(resource_path('css/components/header.css'))
        );
    }

    /**
     * Zonder beeld geeft niets in de flow de sectie hoogte; een venstervullend
     * vlak zou dan een lege hoogte reserveren rond een kaart die er los in
     * hangt. Vandaar dat `hero-frame` aan dezelfde vlag hangt als de kaart.
     */
    public function test_omits_the_viewport_height_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        $this->assertStringNotContainsString('hero-frame', $html);
    }

    /**
     * De 620px uit Figma is een plafond: tussen `sm` en ~700px is de container
     * zelf smaller, en een vaste breedte duwde de kaart daar rechts uit de
     * container.
     */
    public function test_the_card_never_grows_past_the_container(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'image' => '/img/hero.jpg',
        ]);

        $this->assertStringContainsString('sm:max-w-[620px]', $html);
        $this->assertStringNotContainsString('sm:w-[620px]', $html);
    }
}
