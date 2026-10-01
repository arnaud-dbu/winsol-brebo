<?php

namespace Tests\Unit\Inspace;

use App\Inspace\HtmlFragment;
use PHPUnit\Framework\TestCase;

/**
 * Bewust zonder de Laravel-TestCase: het gedrag hier hangt af van de
 * libxml-versie, en die van de productieserver (2.9.14) wijkt af van de
 * lokale. Zonder app-boot draait deze klasse ook in een kale container:
 *
 *   docker run --rm -v "$PWD":/app:ro -w /app php:8.4-cli-bookworm \
 *     php vendor/bin/phpunit --no-configuration --do-not-cache-result \
 *     --bootstrap vendor/autoload.php tests/Unit/Inspace/HtmlFragmentTest.php
 */
class HtmlFragmentTest extends TestCase
{
    private function roundTrip(string $html): string
    {
        return HtmlFragment::render(HtmlFragment::parse($html));
    }

    /**
     * De regressie van 2026-09-29: onder libxml 2.9 bleef hier een lege
     * string over, en gaf elke POST /pages met content een 500.
     */
    public function test_a_single_paragraph_survives(): void
    {
        $this->assertSame('<p>Body.</p>', $this->roundTrip('<p>Body.</p>'));
    }

    public function test_several_top_level_blocks_survive_in_order(): void
    {
        $html = '<h2>Kop</h2><p>Tekst met <strong>nadruk</strong>.</p><ul><li>een</li></ul>';

        $this->assertSame($html, $this->roundTrip($html));
    }

    public function test_utf8_survives(): void
    {
        $html = '<p>één café – “crème” €</p>';

        $this->assertSame($html, $this->roundTrip($html));
    }

    public function test_the_wrapper_never_leaks_into_the_output(): void
    {
        $out = $this->roundTrip('<p>Body.</p>');

        $this->assertStringNotContainsString('<meta', $out);
        $this->assertStringNotContainsString('<body', $out);
        $this->assertStringNotContainsString('<html', $out);
    }

    public function test_a_stray_closing_body_or_html_tag_does_not_drop_what_follows(): void
    {
        $this->assertSame('<p>Voor</p><p>Na</p>', $this->roundTrip('<p>Voor</p></body></html><p>Na</p>'));
        $this->assertSame('<p>Voor</p><p>Na</p>', $this->roundTrip('<p>Voor</p></BODY ><p>Na</p>'));
    }

    public function test_a_full_document_is_reduced_to_its_body(): void
    {
        $this->assertSame('<p>Body.</p>', $this->roundTrip('<html><body><p>Body.</p></body></html>'));
    }
}
