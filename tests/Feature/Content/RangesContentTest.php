<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class RangesContentTest extends TestCase
{
    public function test_every_range_exists_with_its_image(): void
    {
        $slugs = [
            'terrasoverkapping', 'ramen-en-deuren', 'rolluiken', 'zonwering', 'garagepoorten',
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
            'terrasoverkapping' => 'Rondom je woning',
            'ramen-en-deuren' => 'Voor je woning',
            'rolluiken' => 'Rondom je woning',
            'zonwering' => 'Rondom je woning',
            'garagepoorten' => 'Rondom je woning',
            'velux' => 'Voor je woning',
            'airco' => 'Voor je woning',
            'somfy-smart-home' => 'Slim & comfort',
            'stalen-binnendeuren' => 'Voor je woning',
        ];

        foreach ($expectedCategoryTitles as $slug => $expectedTitle) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Range {$slug} ontbreekt");

            $terms = $entry->augmentedValue('range_categories')->value()->get();

            $this->assertNotEmpty($terms, "Range {$slug} heeft geen resolvebare range_categories-term");

            $term = $terms->first();

            $this->assertNotNull($term, "range_categories van {$slug} resolveert niet naar een Term-object");
            $this->assertNotEmpty($term->value('title'), "range_categories-term van {$slug} heeft geen titel");
            $this->assertSame($expectedTitle, $term->value('title'), "Range {$slug} is niet gekoppeld aan de verwachte categorie");
        }
    }

    public function test_range_titles_match_the_design(): void
    {
        $expectedTitles = [
            'terrasoverkapping' => "Terrasoverkapping",
            'velux' => 'VELUX dakramen',
        ];

        foreach ($expectedTitles as $slug => $title) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Range {$slug} ontbreekt");
            $this->assertSame($title, $entry->get('title'), "Titel van {$slug} wijkt af van het ontwerp");
        }
    }
}
