<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ArticleShowPageTest extends TestCase
{
    public function test_the_article_renders_with_its_header_and_chips(): void
    {
        $response = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw');

        $response->assertOk();
        $response->assertSee('data-header="article"', false);
        $response->assertSee('<span class="chip chip--dark">Zonwering</span>', false);
        $response->assertSee('28 juli 2026', false);
    }

    public function test_the_body_is_centered_and_carries_the_prose_utility(): void
    {
        $html = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw')->getContent();

        $this->assertStringContainsString('container-md', $html);
        $this->assertStringContainsString('class="article-body"', $html);

        // `.rich-text` is de minimale variant voor kaarten en sectiekoppen.
        // De artikeltekst hoort hem niet te gebruiken.
        $start = strpos($html, 'container-md');
        $end = strpos($html, '</section>', $start);
        $body = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('class="rich-text"', $body);
    }

    public function test_the_redactor_loops_instead_of_rendering_one_blob(): void
    {
        // Dit is precies wat in de boilerplate stilzwijgend kapot was: het
        // template loopte op type-namen terwijl de fieldset geen sets had, dus
        // Bard leverde één HTML-string en de lus gaf niets terug.
        $html = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw')->getContent();

        $this->assertStringContainsString('Buiten tegenhouden, niet binnen', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('<ul', $html);

        // Twee tekstknopen rond één videoknoop.
        $this->assertSame(2, substr_count($html, 'class="article-body"'));
    }

    public function test_a_video_node_renders_through_the_video_partial(): void
    {
        $html = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw')->getContent();

        $this->assertStringContainsString('<iframe', $html);

        // `embed_url` herschrijft YouTube-links naar het privacy-verbeterde
        // `youtube-nocookie.com`-domein (CoreModifiers::embedUrl), dus niet
        // naar het kale `youtube.com`.
        $this->assertStringContainsString('youtube-nocookie.com/embed/', $html);
    }

    public function test_an_inline_image_survives_inside_the_text_node(): void
    {
        $html = $this->get('/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is')->getContent();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('Pergola met draaibare lamellen boven een terras', $html);
    }

    public function test_the_page_carries_no_page_builder(): void
    {
        $html = $this->get('/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is')->getContent();

        $this->assertStringNotContainsString('data-section="cta"', $html);
        $this->assertStringNotContainsString('data-section="text_image"', $html);
    }
}
