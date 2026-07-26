<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class CatalogContentTest extends TestCase
{
    public function test_six_products_exist_with_an_image(): void
    {
        $products = Entry::query()->where('collection', 'products')->get();

        $this->assertCount(6, $products);

        foreach ($products as $product) {
            $this->assertNotEmpty($product->get('image'), "Product {$product->slug()} heeft geen beeld");
        }
    }

    public function test_six_projects_exist_and_reference_a_product(): void
    {
        $projects = Entry::query()->where('collection', 'projects')->get();

        $this->assertCount(6, $projects);

        foreach ($projects as $project) {
            $this->assertNotEmpty($project->get('product'), "Project {$project->slug()} verwijst niet naar een product");
            $this->assertNotEmpty($project->get('image'));

            $relatedProduct = $project->augmentedValue('product')->value();
            $this->assertNotNull($relatedProduct, "Project {$project->slug()} se product-relatie augmenteert niet naar een entry");
            $this->assertNotEmpty($relatedProduct->get('title'), "Project {$project->slug()} se gerelateerde product heeft geen title");
        }
    }
}
