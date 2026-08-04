<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ArticleShowPageTest extends TestCase
{
    public function test_the_article_renders_with_its_header_and_chips(): void
    {
        $response = $this->get('/nieuws/showroom-aartselaar-is-opnieuw-open');

        $response->assertOk();
        $response->assertSee('data-header="article"', false);
        $response->assertSee('<span class="chip chip--dark">Showroom</span>', false);
        $response->assertSee('16 juli 2026', false);
    }

    public function test_the_body_is_centered_and_carries_the_prose_utility(): void
    {
        $html = $this->get('/nieuws/showroom-aartselaar-is-opnieuw-open')->getContent();

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
        $html = $this->get('/nieuws/showroom-aartselaar-is-opnieuw-open')->getContent();

        $this->assertStringContainsString('Twaalf opstellingen op ware grootte', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('<ul', $html);

        // Twee tekstknopen rond één videoknoop.
        $this->assertSame(2, substr_count($html, 'class="article-body"'));
    }

    public function test_a_video_node_renders_through_the_video_partial(): void
    {
        $html = $this->get('/nieuws/showroom-aartselaar-is-opnieuw-open')->getContent();

        $this->assertStringContainsString('<iframe', $html);

        // `embed_url` herschrijft YouTube-links naar het privacy-verbeterde
        // `youtube-nocookie.com`-domein (CoreModifiers::embedUrl), dus niet
        // naar het kale `youtube.com`.
        $this->assertStringContainsString('youtube-nocookie.com/embed/', $html);
    }

    public function test_an_inline_image_survives_inside_the_text_node(): void
    {
        $html = $this->get('/nieuws/achttien-ramen-en-een-pergola-in-een-werf')->getContent();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('Aangebouwde pergola met draaibare lamellen naast een hefschuifraam', $html);
    }

    public function test_the_page_carries_no_page_builder(): void
    {
        $html = $this->get('/nieuws/achttien-ramen-en-een-pergola-in-een-werf')->getContent();

        $this->assertStringNotContainsString('data-section="cta"', $html);
        $this->assertStringNotContainsString('data-section="text_image"', $html);
    }
}
