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
}
