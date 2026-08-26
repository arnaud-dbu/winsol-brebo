<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Form;
use Tests\TestCase;

class BrochureFormBlueprintTest extends TestCase
{
    public function test_every_field_carries_its_agreed_type(): void
    {
        $fields = Form::find('brochure')->blueprint()->fields()->all();

        $this->assertSame('brochure_checkboxes', $fields->get('brochures')->type());

        foreach (['name', 'phone', 'email', 'address'] as $handle) {
            $this->assertSame('text', $fields->get($handle)->type());
        }
    }

    /**
     * Gated download (Jimmy, werkoverleg 21/24-08): met alleen een
     * e-mailadres kan je geen offerte maken. Elke aanvraag levert daarom een
     * volledige lead — naam, adres en telefoon — of geen brochure.
     */
    public function test_every_field_is_required(): void
    {
        $fields = Form::find('brochure')->blueprint()->fields()->all();

        foreach (['brochures', 'name', 'phone', 'email', 'address'] as $handle) {
            $this->assertTrue($fields->get($handle)->isRequired(), "{$handle} hoort verplicht te zijn.");
        }
    }

    /**
     * Twee mails per aanvraag: de brochures naar de aanvrager en de lead
     * naar de mailbox. Valt de eerste weg, dan is de belofte "meteen in je
     * mailbox" stuk; valt de tweede weg, dan verdwijnen leads geruisloos.
     */
    public function test_the_form_mails_the_brochures_and_a_lead_notification(): void
    {
        $emails = collect(Form::find('brochure')->email());

        $delivery = $emails->firstWhere('id', 'brochure-delivery');
        $this->assertSame('{{ email }}', $delivery['to']);
        $this->assertSame('emails/brochure', $delivery['html']);
        $this->assertSame('emails/brochure_text', $delivery['text']);

        $notification = $emails->firstWhere('id', 'brochure-notification');
        $this->assertSame('hello@stuw.agency', $notification['to']);
        $this->assertSame('{{ email }}', $notification['reply_to']);
    }
}
