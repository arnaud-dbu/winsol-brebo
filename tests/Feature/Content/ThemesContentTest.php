<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Term;
use Tests\TestCase;

class ThemesContentTest extends TestCase
{
    public function test_the_four_themes_exist_with_a_title(): void
    {
        $themes = Term::query()->where('taxonomy', 'themes')->get();

        $this->assertCount(4, $themes);

        foreach ($themes as $theme) {
            $this->assertNotEmpty($theme->get('title'), "Thema {$theme->slug()} heeft geen titel");
        }
    }

    public function test_the_slugs_are_the_ones_the_filter_and_the_articles_refer_to(): void
    {
        $slugs = Term::query()->where('taxonomy', 'themes')->get()
            ->map->slug()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['energie-en-comfort', 'ramen-en-deuren', 'terrasoverkapping', 'zonwering'],
            $slugs
        );
    }

    public function test_the_themes_carry_no_order_field(): void
    {
        // Anders dan `range_categories` heeft dit filter geen ontworpen
        // volgorde: het sorteert alfabetisch. Een `order`-veld zou suggereren
        // dat er wél een bedoelde volgorde is.
        $blueprint = file_get_contents(resource_path('blueprints/taxonomies/themes/themes.yaml'));

        $this->assertStringNotContainsString('order', $blueprint);
    }
}
