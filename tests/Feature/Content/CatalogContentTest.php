<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class CatalogContentTest extends TestCase
{
    public function test_every_product_exists_with_an_image(): void
    {
        $products = Entry::query()->where('collection', 'products')->where('site', 'nl')->get();

        $this->assertCount(29, $products);

        foreach ($products as $product) {
            $this->assertNotEmpty($product->get('image'), "Product {$product->slug()} heeft geen beeld");
        }
    }

    public function test_the_projects_collection_is_fully_gone(): void
    {
        // De realisaties zijn vervangen door /nieuws. Deze assertie vangt een
        // half opgeruimde staat: een achtergebleven collectie zou stilletjes
        // blijven routeren op /realisaties/{slug}.
        $this->assertFileDoesNotExist(base_path('content/collections/projects.yaml'));
        $this->assertDirectoryDoesNotExist(base_path('content/collections/projects'));
        $this->assertFileDoesNotExist(app_path('Tags/ProjectRanges.php'));
        $this->assertFileDoesNotExist(resource_path('views/partials/projectCard.antlers.html'));
        $this->assertFileDoesNotExist(resource_path('views/partials/headers/project.antlers.html'));
        $this->assertFileDoesNotExist(resource_path('css/components/project-card.css'));

        $this->assertSame(0, Entry::query()->where('collection', 'projects')->count());
    }
}
