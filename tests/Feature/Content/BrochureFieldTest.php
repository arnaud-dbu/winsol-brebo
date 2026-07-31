<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Tests\TestCase;

class BrochureFieldTest extends TestCase
{
    public static function blueprintProvider(): array
    {
        return [
            'ranges' => ['collections.ranges.ranges'],
            'products' => ['collections.products.products'],
        ];
    }

    /**
     * @dataProvider blueprintProvider
     */
    public function test_it_has_a_single_pdf_brochure_field_in_the_brochures_folder(string $handle): void
    {
        $field = Blueprint::find($handle)->field('brochure');

        $this->assertNotNull($field, "Blueprint {$handle} heeft geen brochureveld");

        $config = $field->config();

        $this->assertSame('assets', $config['type']);
        $this->assertSame('assets', $config['container']);
        $this->assertSame('brochures', $config['folder']);
        $this->assertTrue($config['restrict']);
        $this->assertSame(1, $config['max_files']);
        $this->assertContains('mimes:pdf', $config['validate']);
    }
}
