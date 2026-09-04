<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Elk formulier meldt een geslaagde verzending aan Tag Manager. Niels bouwt
 * daar zijn conversiemeting op, dus een formulier dat het event niet pusht
 * telt stil niet mee — vandaar dat dit hier vastligt en niet alleen in de
 * partials.
 */
class FormTrackingTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function formulieren(): array
    {
        return [
            'offerte' => ['offerteForm', 'offerte'],
            'brochure' => ['brochureForm', 'brochure'],
            'herstelling' => ['reparationForm', 'herstelling'],
        ];
    }

    #[DataProvider('formulieren')]
    public function test_every_form_pushes_its_success_event(string $partial, string $formId): void
    {
        $markup = file_get_contents(resource_path("views/partials/{$partial}.antlers.html"));

        $this->assertStringContainsString(
            'partial:formSuccessEvent form="'.$formId.'"',
            $markup,
            "Formulier {$formId} pusht geen form_submit_success.",
        );

        // Binnen het succesblok en niet erbuiten: anders vuurt het event ook
        // bij het openen van een leeg formulier en telt elke paginaweergave
        // als conversie.
        $succes = strpos($markup, '{{ if success }}');
        $event = strpos($markup, 'partial:formSuccessEvent');
        $this->assertNotFalse($succes);
        $this->assertGreaterThan($succes, $event, 'Het event staat buiten het succesblok.');
    }

    public function test_the_event_partial_pushes_the_agreed_shape(): void
    {
        $partial = file_get_contents(resource_path('views/partials/formSuccessEvent.antlers.html'));

        $this->assertStringContainsString("event: 'form_submit_success'", $partial);
        $this->assertStringContainsString("form_id: '{{ form }}'", $partial);

        // Zonder deze regel gooit de pagina een fout wanneer analytics
        // uitstaat en dataLayer dus nooit is aangemaakt.
        $this->assertStringContainsString('window.dataLayer = window.dataLayer || []', $partial);
    }
}
