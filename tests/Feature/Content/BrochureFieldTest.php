<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Tests\TestCase;

class BrochureFieldTest extends TestCase
{
    public function test_it_has_a_single_pdf_brochure_field_in_the_brochures_folder(): void
    {
        $field = Blueprint::find('collections.products.products')->field('brochure');

        $this->assertNotNull($field, 'Blueprint collections.products.products heeft geen brochureveld');

        $config = $field->config();

        $this->assertSame('assets', $config['type']);
        $this->assertSame('assets', $config['container']);
        $this->assertSame('brochures', $config['folder']);
        $this->assertTrue($config['restrict']);
        $this->assertSame(1, $config['max_files']);
        $this->assertContains('mimes:pdf', $config['validate']);
    }

    /**
     * De brochure hangt alleen aan producten. Op een range had het veld geen
     * enkele consument meer sinds de quicklinks van `ranges/show` af zijn,
     * terwijl de instructietekst wél een knop beloofde — een redacteur zou daar
     * een pdf uploaden die nergens opduikt. Een terugval van het product naar
     * de range is expliciet afgewezen, dus het veld hoort hier weg te blijven.
     */
    public function test_a_range_has_no_brochure_field(): void
    {
        $this->assertNull(
            Blueprint::find('collections.ranges.ranges')->field('brochure'),
            'De ranges-blueprint draagt weer een brochureveld dat niets rendert'
        );
    }
}
