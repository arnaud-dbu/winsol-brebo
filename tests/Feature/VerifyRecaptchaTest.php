<?php

namespace Tests\Feature;

use App\Listeners\VerifyRecaptcha;
use Illuminate\Support\Facades\Http;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Form;
use Tests\TestCase;

/**
 * `false` uit deze listener betekent voor Statamic: stil weggooien. De
 * bezoeker ziet het bedankscherm, maar de aanvraag bestaat nergens. Deze
 * tests leggen vast wanneer dat mag gebeuren — alleen bij een oordeel van
 * Google — en wanneer niet.
 */
class VerifyRecaptchaTest extends TestCase
{
    private function submit(): mixed
    {
        $submission = Form::find('herstelling')->makeSubmission()->data(['name' => 'Jan']);

        return (new VerifyRecaptcha)->handle(new FormSubmitted($submission));
    }

    private function configure(): void
    {
        config([
            'services.recaptcha.site_key' => 'site',
            'services.recaptcha.api_key' => 'api',
            'services.recaptcha.project_id' => 'project',
            'services.recaptcha.threshold' => 0.5,
        ]);
    }

    /**
     * Zonder sleutels kan de pagina geen token maken. Zou de listener dan
     * weigeren, dan verdwijnt elke inzending op een omgeving waar reCAPTCHA
     * niet is ingesteld — precies wat er lokaal gebeurde.
     */
    public function test_it_lets_submissions_through_when_recaptcha_is_not_configured(): void
    {
        config([
            'services.recaptcha.site_key' => null,
            'services.recaptcha.api_key' => null,
            'services.recaptcha.project_id' => null,
        ]);

        Http::fake();

        $this->assertNotFalse($this->submit());
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_configured_submission_without_a_token(): void
    {
        $this->configure();

        $this->assertFalse($this->submit());
    }

    public function test_it_lets_a_human_score_through(): void
    {
        $this->configure();
        request()->merge(['g-recaptcha-response' => 'token']);

        Http::fake(['*' => Http::response([
            'tokenProperties' => ['valid' => true],
            'riskAnalysis' => ['score' => 0.9],
        ])]);

        $this->assertNotFalse($this->submit());
    }

    public function test_it_rejects_a_bot_score(): void
    {
        $this->configure();
        request()->merge(['g-recaptcha-response' => 'token']);

        Http::fake(['*' => Http::response([
            'tokenProperties' => ['valid' => true],
            'riskAnalysis' => ['score' => 0.1],
        ])]);

        $this->assertFalse($this->submit());
    }

    /**
     * Een storing bij Google mag geen leads kosten. De honeypot op het
     * formulier blijft in dat geval de tweede laag.
     */
    public function test_it_lets_submissions_through_when_google_is_unreachable(): void
    {
        $this->configure();
        request()->merge(['g-recaptcha-response' => 'token']);

        Http::fake(['*' => Http::response('', 503)]);

        $this->assertNotFalse($this->submit());
    }
}
