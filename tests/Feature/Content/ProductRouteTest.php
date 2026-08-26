<?php

namespace Tests\Feature\Content;

use App\Providers\AppServiceProvider;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ProductRouteTest extends TestCase
{
    public function test_every_product_points_at_exactly_one_range(): void
    {
        $products = Entry::query()->where('collection', 'products')->where('site', 'nl')->get();

        $this->assertGreaterThan(0, $products->count(), 'Er zijn geen producten om te controleren');

        foreach ($products as $product) {
            $range = $product->get('range');

            $this->assertNotEmpty($range, "Product {$product->slug()} heeft geen range");
            $this->assertCount(1, (array) $range, "Product {$product->slug()} wijst naar meer dan een range");
        }
    }

    /**
     * Een gevuld `range`-veld is niet genoeg: wijst het id naar een verwijderde
     * of hernoemde range, dan valt het middelste routesegment weg en botst het
     * product met de rangeroute. Deze test toetst daarom de uitkomst en niet
     * enkel de invoer.
     */
    public function test_every_product_resolves_its_range_to_an_existing_slug(): void
    {
        $products = Entry::query()->where('collection', 'products')->where('site', 'nl')->get();

        $this->assertGreaterThan(0, $products->count(), 'Er zijn geen producten om te controleren');

        foreach ($products as $product) {
            $slug = $product->augmentedValue('range_slug')->value();

            $this->assertNotNull($slug, "Product {$product->slug()} levert geen range_slug op");
            $this->assertNotSame(
                AppServiceProvider::UNRESOLVED_RANGE_SLUG,
                $slug,
                "Product {$product->slug()} wijst naar een range die niet meer bestaat"
            );
            $this->assertNotNull(
                Entry::query()->where('collection', 'ranges')->where('site', 'nl')->where('slug', $slug)->first(),
                "Range {$slug} van product {$product->slug()} bestaat niet"
            );
        }
    }

    public function test_a_product_whose_range_no_longer_exists_falls_back_to_a_non_colliding_slug(): void
    {
        $product = Entry::make()
            ->collection('products')
            ->locale('nl')
            ->slug('verweesd-product')
            ->data(['title' => 'Verweesd product', 'range' => ['bestaat-niet']]);

        $this->assertSame(
            AppServiceProvider::UNRESOLVED_RANGE_SLUG,
            $product->augmentedValue('range_slug')->value()
        );
    }

    public function test_the_computed_range_slug_resolves_to_the_range_its_slug(): void
    {
        $product = Entry::query()->where('collection', 'products')->where('site', 'nl')->where('slug', 'pergola-so')->first();

        $this->assertNotNull($product);
        $this->assertSame('terrasoverkapping', $product->augmentedValue('range_slug')->value());
    }

    public function test_the_url_nests_the_product_under_its_range(): void
    {
        $product = Entry::query()->where('collection', 'products')->where('site', 'nl')->where('slug', 'pergola-so')->first();

        $this->assertSame('/aanbod/terrasoverkapping/pergola-so', $product->url());
    }
}
