<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Form;
use Tests\TestCase;

class OfferteFormBlueprintTest extends TestCase
{
    public function test_every_field_carries_its_agreed_type(): void
    {
        $fields = Form::find('offerte')->blueprint()->fields()->all();

        $this->assertSame('range_checkboxes', $fields->get('products')->type());
        $this->assertSame('location_select', $fields->get('location')->type());
        $this->assertSame('textarea', $fields->get('project')->type());
        $this->assertSame('assets', $fields->get('attachment')->type());
    }

    /**
     * Naam, e-mail en minstens een product zijn verplicht; de andere vijf
     * niet. Verhardt de drempel per ongeluk, dan kost dat conversie.
     */
    public function test_exactly_three_fields_are_required(): void
    {
        $fields = Form::find('offerte')->blueprint()->fields()->all();

        foreach (['products', 'name', 'email'] as $handle) {
            $this->assertTrue($fields->get($handle)->isRequired(), "{$handle} hoort verplicht te zijn.");
        }

        foreach (['location', 'phone', 'postal_code', 'project', 'attachment'] as $handle) {
            $this->assertFalse($fields->get($handle)->isRequired(), "{$handle} hoort optioneel te zijn.");
        }
    }

    /**
     * Klantfoto's en bouwplannen horen niet op een raadbare publieke URL.
     * Verschuift dit naar `assets`, dan is elke upload publiek leesbaar.
     */
    public function test_uploads_land_in_the_private_container(): void
    {
        $attachment = Form::find('offerte')->blueprint()->fields()->all()->get('attachment');

        $this->assertSame('private', $attachment->get('container'));
        $this->assertSame(1, $attachment->get('max_files'));
    }
}
