<?php

namespace Tests\Unit\Inspace;

use App\Inspace\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer(['p', 'br', 'h2', 'h3', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'img']);
    }

    public function test_allowed_markup_survives(): void
    {
        $html = '<h2>Kop</h2><p>Tekst met <strong>nadruk</strong> en <a href="/over-ons">link</a>.</p><ul><li>een</li></ul>';

        $sanitizer = $this->sanitizer();

        $this->assertSame($html, $sanitizer->clean($html));
        $this->assertSame([], $sanitizer->warnings());
    }

    public function test_a_disallowed_tag_is_unwrapped_and_reported(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean('<h1>Titel</h1><p>Blijft.</p>');

        $this->assertStringNotContainsString('<h1>', $out);
        $this->assertStringContainsString('Titel', $out, 'De tekst blijft, alleen de tag verdwijnt.');
        $this->assertStringContainsString('<p>Blijft.</p>', $out);
        $this->assertSame(['Tag <h1> is niet toegestaan en is verwijderd.'], $sanitizer->warnings());
    }

    public function test_each_disallowed_tag_is_reported_once(): void
    {
        $sanitizer = $this->sanitizer();

        $sanitizer->clean('<blockquote>een</blockquote><blockquote>twee</blockquote>');

        $this->assertSame(['Tag <blockquote> is niet toegestaan en is verwijderd.'], $sanitizer->warnings());
    }

    public function test_style_and_script_are_removed_with_their_content(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean('<p>Blijft.</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('alert', $out, 'Script-inhoud mag niet als tekst overblijven.');
        $this->assertStringContainsString('<p>Blijft.</p>', $out);
    }

    public function test_utf8_survives(): void
    {
        $sanitizer = $this->sanitizer();

        $this->assertStringContainsString('één café', $sanitizer->clean('<p>één café</p>'));
    }

    public function test_a_disallowed_tag_nested_inside_a_disallowed_tag_is_fully_unwrapped(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean('<div><blockquote>Genest.</blockquote></div><p>Blijft.</p>');

        $this->assertStringNotContainsString('<div>', $out);
        $this->assertStringNotContainsString('<blockquote>', $out);
        $this->assertStringContainsString('Genest.', $out, 'De tekst uit beide geneste lagen blijft staan.');
        $this->assertStringContainsString('<p>Blijft.</p>', $out);
        $this->assertSame(
            [
                'Tag <div> is niet toegestaan en is verwijderd.',
                'Tag <blockquote> is niet toegestaan en is verwijderd.',
            ],
            $sanitizer->warnings()
        );
    }

    public function test_a_disallowed_tag_containing_dropped_content_removes_the_content_too(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean('<div><script>alert(1)</script></div><p>Blijft.</p>');

        $this->assertStringNotContainsString('alert', $out);
        $this->assertStringContainsString('<p>Blijft.</p>', $out);
    }

    public function test_a_meta_tag_hidden_inside_the_input_is_stripped_and_reported(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean(
            '<p>Voor</p><p><meta http-equiv="refresh" content="0;url=http://evil.example">Na</p>'
        );

        $this->assertStringNotContainsString('<meta', $out, 'Een ingesloten meta-tag mag de whitelist niet omzeilen.');
        $this->assertStringContainsString('Voor', $out);
        $this->assertStringContainsString('Na', $out);
        $this->assertSame(['Tag <meta> is niet toegestaan en is verwijderd.'], $sanitizer->warnings());
    }
}
