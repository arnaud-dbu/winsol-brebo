<?php

namespace Tests\Feature\Sections;

class RecaptchaOnFormsTest extends SectionTestCase
{
    /**
     * De partial bestond wel, maar werd nergens ingeladen. Op een omgeving met
     * sleutels ziet App\Listeners\VerifyRecaptcha dan een inzending zonder
     * token en weigert die stil: geen opslag, geen mail, geen bevestigings-
     * scherm. Precies wat er op productie gebeurde.
     */
    public function test_every_form_carries_the_token_field_when_recaptcha_is_configured(): void
    {
        config(['services.recaptcha.site_key' => 'sleutel']);

        foreach (['brochureForm', 'reparationForm', 'offerteForm'] as $formulier) {
            $html = $this->render('{{ partial:'.$formulier.' }}');

            $this->assertStringContainsString(
                'name="g-recaptcha-response"',
                $html,
                "{$formulier} mist het reCAPTCHA-tokenveld."
            );
        }
    }

    /**
     * En andersom: zonder sleutel mag het script er niet staan. Het onderschept
     * de submit en wacht op een token van Google; bestaat `grecaptcha` niet,
     * dan blijft het formulier na `preventDefault()` definitief hangen.
     */
    public function test_it_stays_out_of_the_way_without_a_key(): void
    {
        config(['services.recaptcha.site_key' => null]);

        foreach (['brochureForm', 'reparationForm', 'offerteForm'] as $formulier) {
            $html = $this->render('{{ partial:'.$formulier.' }}');

            $this->assertStringNotContainsString('name="g-recaptcha-response"', $html);
            $this->assertStringNotContainsString('enterprise.js', $html);
        }
    }
}
