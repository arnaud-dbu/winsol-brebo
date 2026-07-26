<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class RangesContentTest extends TestCase
{
    public function test_every_range_exists_with_its_image(): void
    {
        $slugs = [
            'pergolas', 'ramen-en-deuren', 'rolluiken', 'zonwering', 'garagepoorten',
            'velux', 'airco', 'somfy-smart-home', 'stalen-binnendeuren',
        ];

        foreach ($slugs as $slug) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Range {$slug} ontbreekt");
            $this->assertSame("ranges/{$slug}.png", $entry->get('image'));
            $this->assertNotEmpty($entry->get('short_description'));
        }
    }
}
