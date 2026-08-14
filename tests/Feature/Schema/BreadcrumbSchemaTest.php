<?php

namespace Tests\Feature\Schema;

use App\Schema\BreadcrumbSchema;
use Tests\TestCase;

class BreadcrumbSchemaTest extends TestCase
{
    public function test_the_homepage_has_no_breadcrumb(): void
    {
        $this->assertNull(BreadcrumbSchema::node('/', 'Home'));
    }

    public function test_a_product_gets_four_levels(): void
    {
        $node = BreadcrumbSchema::node('/aanbod/rolluiken/inbouwrolluiken', 'Inbouwrolluiken');

        $this->assertSame('BreadcrumbList', $node['@type']);
        $this->assertCount(4, $node['itemListElement']);

        $this->assertSame('Home', $node['itemListElement'][0]['name']);
        $this->assertSame(1, $node['itemListElement'][0]['position']);
        $this->assertSame('Inbouwrolluiken', $node['itemListElement'][3]['name']);
        $this->assertSame(4, $node['itemListElement'][3]['position']);
    }

    public function test_intermediate_levels_use_the_real_entry_title(): void
    {
        $node = BreadcrumbSchema::node('/aanbod/rolluiken/inbouwrolluiken', 'Inbouwrolluiken');

        // Echte titel van de entry op /aanbod is 'Ons aanbod', niet 'Aanbod'.
        $this->assertSame('Ons aanbod', $node['itemListElement'][1]['name']);
    }

    public function test_items_are_absolute_urls(): void
    {
        $node = BreadcrumbSchema::node('/aanbod/rolluiken', 'Rolluiken');

        foreach ($node['itemListElement'] as $item) {
            $this->assertStringStartsWith('http', $item['item']);
        }
    }

    public function test_a_missing_intermediate_entry_falls_back_to_the_slug(): void
    {
        $node = BreadcrumbSchema::node('/bestaat-niet/dieper', 'Dieper');

        $this->assertSame('Bestaat niet', $node['itemListElement'][1]['name']);
    }
}
