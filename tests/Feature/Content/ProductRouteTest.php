<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ProductRouteTest extends TestCase
{
    public function test_every_product_points_at_exactly_one_range(): void
    {
        $products = Entry::query()->where('collection', 'products')->get();

        $this->assertGreaterThan(0, $products->count(), 'Er zijn geen producten om te controleren');

        foreach ($products as $product) {
            $range = $product->get('range');

            $this->assertNotEmpty($range, "Product {$product->slug()} heeft geen range");
            $this->assertCount(1, (array) $range, "Product {$product->slug()} wijst naar meer dan een range");
        }
    }

    public function test_the_computed_range_slug_resolves_to_the_range_its_slug(): void
    {
        $product = Entry::query()->where('collection', 'products')->where('slug', 'pergola-so')->first();

        $this->assertNotNull($product);
        $this->assertSame('pergolas', $product->augmentedValue('range_slug')->value());
    }

    public function test_the_url_nests_the_product_under_its_range(): void
    {
        $product = Entry::query()->where('collection', 'products')->where('slug', 'pergola-so')->first();

        $this->assertSame('/aanbod/pergolas/pergola-so', $product->url());
    }
}
