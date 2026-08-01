<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class CatalogContentTest extends TestCase
{
    public function test_every_product_exists_with_an_image(): void
    {
        $products = Entry::query()->where('collection', 'products')->get();

        $this->assertCount(6, $products);

        foreach ($products as $product) {
            $this->assertNotEmpty($product->get('image'), "Product {$product->slug()} heeft geen beeld");
        }
    }

    public function test_six_projects_exist_and_reference_a_range(): void
    {
        $projects = Entry::query()->where('collection', 'projects')->get();

        $this->assertCount(6, $projects);

        foreach ($projects as $project) {
            $this->assertNotEmpty($project->get('image'), "Project {$project->slug()} heeft geen beeld");
            $this->assertNotEmpty($project->get('range'), "Project {$project->slug()} verwijst niet naar een range");

            $relatedRange = $project->augmentedValue('range')->value();

            $this->assertNotNull($relatedRange, "Project {$project->slug()} zijn range-relatie augmenteert niet naar een entry");
            $this->assertSame('ranges', $relatedRange->collectionHandle(), "Project {$project->slug()} verwijst niet naar de ranges-collectie");
            $this->assertNotEmpty($relatedRange->get('title'), "De range van project {$project->slug()} heeft geen titel");
        }
    }
}
