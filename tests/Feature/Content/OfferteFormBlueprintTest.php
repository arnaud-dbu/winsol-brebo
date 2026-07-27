<?php

namespace Tests\Feature\Content;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Form;
use Statamic\Fieldtypes\Assets\MaxRule;
use Statamic\Fieldtypes\Assets\MimesRule;
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
     *
     * De handle alleen is geen garantie: een container die op een schijf mét
     * `url` staat is publiek, hoe hij ook heet. `private()` is de eigenschap
     * die de belofte in deze testnaam waarmaakt — vandaar de tweede assertie.
     */
    public function test_uploads_land_in_the_private_container(): void
    {
        $attachment = Form::find('offerte')->blueprint()->fields()->all()->get('attachment');

        $this->assertSame('private', $attachment->get('container'));
        $this->assertSame(1, $attachment->get('max_files'));
        $this->assertTrue(
            AssetContainer::find('private')->private(),
            'De private-container staat op een schijf met een `url`, dus de uploads zijn publiek bereikbaar.',
        );
    }

    /**
     * Iedereen kan anoniem naar dit formulier POSTen. De assets-fieldtype
     * levert zelf alleen `array` en `max:1`, en die `max` telt bestanden.
     * Zonder de twee regels hieronder is elk bestandstype van elke omvang
     * welkom.
     *
     * De regel heet `max_filesize` en niet `max`: Statamic zet `max_filesize`
     * om naar `Assets\MaxRule`, die het bestand zélf opmeet (in kB). Een kale
     * `max:10240` blijft een Laravel-arrayregel en zou dus "hoogstens 10240
     * bijlagen" betekenen — geverifieerd: een pdf van 20 MB kwam er
     * ongehinderd door.
     */
    public function test_the_upload_is_limited_by_type_and_size(): void
    {
        $attachment = Form::find('offerte')->blueprint()->fields()->all()->get('attachment');

        $this->assertContains('mimes:jpg,jpeg,png,webp,heic,pdf', $attachment->get('validate'));
        $this->assertContains('max_filesize:10240', $attachment->get('validate'));

        $rules = $attachment->rules()['attachment'];

        $this->assertCount(1, array_filter($rules, fn ($rule) => $rule instanceof MimesRule));
        $this->assertCount(1, array_filter($rules, fn ($rule) => $rule instanceof MaxRule));
    }
}
