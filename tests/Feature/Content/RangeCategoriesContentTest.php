<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Tests\TestCase;

class RangeCategoriesContentTest extends TestCase
{
    public function test_the_three_categories_exist_in_the_designed_order(): void
    {
        $expected = [
            'voor-je-woning' => ['Voor je woning', 1],
            'rondom-je-woning' => ['Rondom je woning', 2],
            'slim-en-comfort' => ['Slim & comfort', 3],
        ];

        foreach ($expected as $slug => [$title, $order]) {
            $term = Term::query()->where('taxonomy', 'range_categories')->where('slug', $slug)->get()->first(fn ($t) => $t->locale() === 'nl');

            $this->assertNotNull($term, "Range-categorie {$slug} ontbreekt");
            $this->assertSame($title, $term->value('title'), "Titel van {$slug} klopt niet");
            $this->assertSame($order, (int) $term->value('order'), "Volgorde van {$slug} klopt niet");
        }

        $all = Term::query()->where('taxonomy', 'range_categories')->get()->filter(fn ($t) => $t->locale() === 'nl')->values();

        $this->assertCount(3, $all, 'Er horen precies drie range-categorieën te zijn');
    }

    public function test_every_range_sits_in_its_designed_category(): void
    {
        $expected = [
            'ramen-en-deuren' => 'voor-je-woning',
            'stalen-binnendeuren' => 'voor-je-woning',
            'velux' => 'voor-je-woning',
            'airco' => 'voor-je-woning',
            'rolluiken' => 'rondom-je-woning',
            'zonwering' => 'rondom-je-woning',
            'terrasoverkapping' => 'rondom-je-woning',
            'garagepoorten' => 'rondom-je-woning',
            'somfy-smart-home' => 'slim-en-comfort',
        ];

        foreach ($expected as $rangeSlug => $categorySlug) {
            $entry = Entry::query()->where('collection', 'ranges')->where('site', 'nl')->where('slug', $rangeSlug)->first();

            $this->assertNotNull($entry, "Range {$rangeSlug} ontbreekt");

            $terms = $entry->augmentedValue('range_categories')->value()->get();

            $this->assertCount(1, $terms, "Range {$rangeSlug} hoort in precies één categorie te zitten");
            $this->assertSame($categorySlug, $terms->first()->slug(), "Range {$rangeSlug} zit in de verkeerde categorie");
        }
    }
}
