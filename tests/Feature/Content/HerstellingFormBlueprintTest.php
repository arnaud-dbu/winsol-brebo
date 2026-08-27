<?php

namespace Tests\Feature\Content;

use Statamic\Facades\AssetContainer;
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

    /**
     * Alles verplicht behalve de uploads (Quinten, 26-08). De uploads
     * blijven optioneel: niet iedereen heeft de factuur of een bruikbare
     * foto bij de hand, en een verplichte bijlage blokkeert de aanvraag.
     */
    public function test_every_field_but_the_uploads_is_required(): void
    {
        $fields = Form::find('herstelling')->blueprint()->fields()->all();

        foreach (['product', 'is_winsol', 'installed', 'facade', 'floor', 'dimensions', 'problem', 'branch', 'email', 'name', 'phone', 'address'] as $handle) {
            $this->assertTrue($fields->get($handle)->isRequired(), "{$handle} hoort verplicht te zijn.");
        }

        foreach (['photo', 'invoice'] as $handle) {
            $this->assertFalse($fields->get($handle)->isRequired(), "{$handle} hoort optioneel te zijn.");
        }
    }

    /**
     * Probleemfoto's en zeker facturen (naam, adres, aankoopbedrag) horen
     * niet op een raadbare publieke URL. Zie de gelijknamige test op het
     * offerteformulier voor waarom `private()` de eigenlijke garantie is.
     */
    public function test_uploads_land_in_the_private_container(): void
    {
        $fields = Form::find('herstelling')->blueprint()->fields()->all();

        foreach (['photo', 'invoice'] as $handle) {
            $this->assertSame('private', $fields->get($handle)->get('container'), "{$handle} hoort in de private-container.");
            $this->assertSame(1, $fields->get($handle)->get('max_files'));
        }

        $this->assertTrue(
            AssetContainer::find('private')->private(),
            'De private-container staat op een schijf met een `url`, dus de uploads zijn publiek bereikbaar.',
        );
    }

    /**
     * Iedereen kan anoniem naar dit formulier POSTen; zonder deze regels is
     * elk bestandstype van elke omvang welkom. `max_filesize` en niet `max`
     * — zie de toelichting op het offerteformulier.
     */
    public function test_the_uploads_are_limited_by_type_and_size(): void
    {
        $fields = Form::find('herstelling')->blueprint()->fields()->all();

        foreach (['photo', 'invoice'] as $handle) {
            $this->assertContains('mimes:jpg,jpeg,png,webp,heic,heif,hif,pdf', $fields->get($handle)->get('validate'));
            $this->assertContains('max_filesize:10240', $fields->get($handle)->get('validate'));
        }
    }
}
