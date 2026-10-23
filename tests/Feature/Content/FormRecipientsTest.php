<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Form;
use Tests\TestCase;

/**
 * Waar de formulieren toekomen.
 *
 * Afgesproken op de weekly van 07-09-2026: elke aanvraag van de site komt op
 * offertes@winsolspl.be binnen, en dat is de laatste stap vóór de livegang.
 * Een testadres dat hier ooit blijft staan, leidt echte leads weg zonder dat
 * iemand het merkt — vandaar deze bewaking op de vier formulieren.
 */
class FormRecipientsTest extends TestCase
{
    private const ONTVANGER = 'offertes@winsolspl.be';

    public function test_every_form_notifies_winsol(): void
    {
        $formulieren = ['offerte', 'contact', 'herstelling', 'brochure'];

        foreach ($formulieren as $handle) {
            $form = Form::find($handle);

            $this->assertNotNull($form, "Formulier {$handle} bestaat niet");

            $ontvangers = collect($form->email())
                ->pluck('to')
                // De brochuremail en de bevestiging vertrekken naar de
                // aanvrager zelf; die tellen hier niet mee.
                ->reject(fn ($to) => $to === '{{ email }}')
                ->values();

            $this->assertNotEmpty($ontvangers, "Formulier {$handle} verwittigt niemand");

            foreach ($ontvangers as $to) {
                $this->assertSame(self::ONTVANGER, $to, "Formulier {$handle} stuurt naar {$to}");
            }
        }
    }

    public function test_the_brochure_still_reaches_the_visitor_who_asked_for_it(): void
    {
        $to = collect(Form::find('brochure')->email())->pluck('to');

        $this->assertContains('{{ email }}', $to);
        $this->assertContains(self::ONTVANGER, $to);
    }

    public function test_the_reply_goes_back_to_the_visitor(): void
    {
        // Zonder reply-to beantwoordt Winsol zijn eigen adres.
        foreach (['offerte', 'contact', 'herstelling'] as $handle) {
            foreach (collect(Form::find($handle)->email())->where('to', self::ONTVANGER) as $mail) {
                $this->assertSame('{{ email }}', $mail['reply_to'] ?? null, "Formulier {$handle} mist reply_to");
            }
        }
    }

    public function test_a_reply_to_the_confirmation_reaches_winsol(): void
    {
        foreach (['offerte', 'contact', 'herstelling'] as $handle) {
            $confirmation = collect(Form::find($handle)->email())->firstWhere('to', '{{ email }}');

            $this->assertNotNull($confirmation, "Formulier {$handle} stuurt de klant geen bevestiging");
            $this->assertSame(self::ONTVANGER, $confirmation['reply_to'] ?? null, "De bevestiging van {$handle} mist reply_to");
        }
    }
}
