<?php

namespace Tests\Unit\Inspace;

use App\Inspace\LinkResolver;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class LinkResolverTest extends TestCase
{
    use CreatesTemporaryContent;

    public function test_a_url_that_points_at_an_entry_becomes_a_statamic_reference(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-artikel', [
            'title' => 'Linktest',
            'themes' => ['realisaties'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic('<p><a href="'.$entry->url().'">lees dit</a></p>');

        $this->assertStringContainsString('href="statamic://entry::'.$entry->id().'"', $out);
    }

    public function test_an_absolute_url_is_matched_on_its_path(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-absoluut', [
            'title' => 'Absoluut',
            'themes' => ['realisaties'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic('<p><a href="'.$entry->absoluteUrl().'">x</a></p>');

        $this->assertStringContainsString('statamic://entry::'.$entry->id(), $out);
    }

    public function test_an_external_link_is_left_alone(): void
    {
        $html = '<p><a href="https://www.example.com/iets">extern</a></p>';

        $this->assertSame($html, (new LinkResolver)->toStatamic($html));
    }

    public function test_a_foreign_host_with_a_path_matching_a_real_entry_is_left_alone(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-vreemde-host', [
            'title' => 'Vreemde host',
            'themes' => ['realisaties'],
            'date' => '2026-08-04',
        ]);

        $html = '<p><a href="https://evil.example'.$entry->url().'">extern</a></p>';

        $this->assertSame(
            $html,
            (new LinkResolver)->toStatamic($html),
            'Het pad bestaat lokaal, maar de host is vreemd: de hostcontrole moet dit tegenhouden.'
        );
    }

    public function test_an_unknown_internal_path_is_left_alone(): void
    {
        $html = '<p><a href="/bestaat-niet-12345">x</a></p>';

        $this->assertSame($html, (new LinkResolver)->toStatamic($html));
    }

    public function test_a_single_quoted_href_is_resolved_too(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-enkel-quote', [
            'title' => 'Enkel citaat',
            'themes' => ['realisaties'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic("<p><a href='".$entry->url()."'>lees dit</a></p>");

        $this->assertStringContainsString('statamic://entry::'.$entry->id(), $out);
    }

    public function test_an_uppercase_anchor_tag_is_resolved_too(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-hoofdletters', [
            'title' => 'Hoofdletters',
            'themes' => ['realisaties'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic('<p><A HREF="'.$entry->url().'">lees dit</A></p>');

        $this->assertStringContainsString('statamic://entry::'.$entry->id(), $out);
    }

    public function test_a_href_with_other_attributes_keeps_them(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-attributen', [
            'title' => 'Attributen',
            'themes' => ['realisaties'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic(
            '<p><a class="link" href="'.$entry->url().'" target="_blank">lees dit</a></p>'
        );

        $this->assertStringContainsString('class="link"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('statamic://entry::'.$entry->id(), $out);
    }
}
