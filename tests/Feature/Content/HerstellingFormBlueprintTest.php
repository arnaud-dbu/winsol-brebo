<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Form;
use Tests\TestCase;

class HerstellingFormBlueprintTest extends TestCase
{
    /**
     * Jimmy (werkoverleg 21/24-08): zonder adres is een lead maar half —
     * elke aanvraag die binnenkomt draagt normaal naam, adres en telefoon.
     * Het adres is daarom op elk formulier verplicht.
     */
    public function test_the_address_is_a_required_text_field(): void
    {
        $fields = Form::find('herstelling')->blueprint()->fields()->all();

        $this->assertSame('text', $fields->get('address')->type());
        $this->assertTrue($fields->get('address')->isRequired());
    }
}
