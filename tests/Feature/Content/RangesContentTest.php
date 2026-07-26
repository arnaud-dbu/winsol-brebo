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

    public function test_every_range_category_relation_resolves_to_a_real_term(): void
    {
        $expectedCategoryTitles = [
            'pergolas' => 'Buitenzonwering',
            'ramen-en-deuren' => 'Schrijnwerk',
            'rolluiken' => 'Buitenzonwering',
            'zonwering' => 'Buitenzonwering',
            'garagepoorten' => 'Schrijnwerk',
            'velux' => 'Schrijnwerk',
            'airco' => 'Comfort en techniek',
            'somfy-smart-home' => 'Comfort en techniek',
            'stalen-binnendeuren' => 'Schrijnwerk',
        ];

        foreach ($expectedCategoryTitles as $slug => $expectedTitle) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Range {$slug} ontbreekt");

            $terms = $entry->augmentedValue('range_category')->value()->get();

            $this->assertNotEmpty($terms, "Range {$slug} heeft geen resolvebare range_category-term");

            $term = $terms->first();

            $this->assertNotNull($term, "range_category van {$slug} resolveert niet naar een Term-object");
            $this->assertNotEmpty($term->value('title'), "range_category-term van {$slug} heeft geen titel");
            $this->assertSame($expectedTitle, $term->value('title'), "Range {$slug} is niet gekoppeld aan de verwachte categorie");
        }
    }
}
