<?php

namespace Tests\Feature\Sections;

class BrochureFormTest extends SectionTestCase
{
    public function test_renders_every_field_from_the_blueprint(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        foreach (['name', 'phone', 'email', 'address'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }

        $this->assertStringContainsString('name="brochures[]"', $html);
    }

    public function test_the_brochure_pills_come_from_the_global(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertSame(9, substr_count($html, 'offerte-pill"'));

        foreach (['Aluminium ramen en deuren', 'Rolluiken', 'Pergola SO!'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_the_address_field_is_the_autocomplete_combobox(): void
    {
        $html = $this->render('{{ partial:brochureForm }}');

        $this->assertStringContainsString('addressAutocomplete(', $html);
        $this->assertStringContainsString('role="combobox"', $html);
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
