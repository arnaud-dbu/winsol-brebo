<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class BrochureFormTest extends SectionTestCase
{
    public function test_renders_every_field_from_the_blueprint(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        foreach (['name', 'phone', 'email', 'street', 'number', 'postal_code', 'city'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }

        $this->assertStringContainsString('name="brochures[]"', $html);
    }

    public function test_the_brochure_pills_come_from_the_global(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $inDeGlobal = count(
            GlobalSet::findByHandle('brochure_library')
                ->in(Site::current()->handle())
                ->get('items'),
        );

        $this->assertSame($inDeGlobal, substr_count($html, 'offerte-pill"'));

        foreach (['Aluminium ramen en deuren', 'Rolluiken', 'Pergola SO!'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    /**
     * Zie OfferteFormTest::test_the_pills_are_a_list_inside_a_labelled_group().
     */
    public function test_the_pills_are_a_list_inside_a_labelled_group(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertMatchesRegularExpression(
            '/<div\s+class="offerte-products"\s+role="group"\s+aria-labelledby="brochure-form-brochures-field-label"/',
            $html,
        );
        $this->assertMatchesRegularExpression('/<ul class="offerte-pills">/', $html);
    }

    /**
     * Zie OfferteFormTest::test_the_street_field_is_a_plain_required_text_field().
     */
    public function test_the_street_field_is_a_plain_required_text_field(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertMatchesRegularExpression('/<input[^>]*type="text"[^>]*name="street"[^>]*required/s', $html);
        $this->assertStringNotContainsString('role="combobox"', $html);
        $this->assertStringNotContainsString('addressAutocomplete', $html);
    }

    /**
     * Zelfde eis als op winsol.eu: geen brochure zonder expliciet akkoord
     * met de gegevensverwerking, met de link naar het privacybeleid erbij.
     */
    public function test_carries_a_required_gdpr_checkbox_linking_the_privacy_policy(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertMatchesRegularExpression('/name="gdpr"[^>]*required/', $html);
        $this->assertStringContainsString('href="/privacy-policy"', $html);
        $this->assertStringContainsString('privacybeleid', $html);
    }

    /**
     * Op de brochureaanvraag is elk veld verplicht, dus er staat nergens een
     * markering: de site markeert alleen wat optioneel is.
     */
    public function test_no_field_is_marked_because_every_field_is_required(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertStringNotContainsString('(optioneel)', $html);
        $this->assertStringNotContainsString('(verplicht)', $html);
    }

    public function test_carries_a_honeypot(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertStringContainsString('name="honeypot"', $html);
    }

    /**
     * Zelfde afspraak als bij het offerteformulier: de bevestiging vervangt
     * het formulier, anders staat de bezoeker voor een leeg formulier met
     * een bevestiging erboven.
     */
    public function test_the_confirmation_replaces_the_form(): void
    {
        session(['form.brochure.success' => 'Bedankt voor je aanvraag.']);

        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertStringContainsString('Je brochures zijn onderweg', $html);
        $this->assertStringNotContainsString('name="brochures[]"', $html);
        $this->assertStringNotContainsString('name="honeypot"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }
}
