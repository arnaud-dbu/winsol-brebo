<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Events\FormSubmitted;

/**
 * Spamfilter op de formulieren, via reCAPTCHA Enterprise.
 *
 * `false` teruggeven laat Statamic de inzending stil weggooien: de bezoeker
 * krijgt het bedankscherm, maar er wordt niets opgeslagen en niets verstuurd.
 * Dat is precies wat je wil tegenover een bot en precies wat je niet wil
 * tegenover een klant, want er blijft nergens een spoor van achter. Vandaar
 * dat elke weigering hier gelogd wordt en dat er alleen geweigerd wordt bij
 * een oordeel van Google zelf — niet bij een ontbrekende sleutel of een
 * onbereikbare dienst. Een gemiste herstelaanvraag kost Winsol een klant; een
 * doorgelaten bot kost hun een mail, en de honeypot op het formulier vangt de
 * eenvoudige gevallen sowieso af.
 */
class VerifyRecaptcha
{
    public function handle(FormSubmitted $event)
    {
        $form = $event->submission->form()->handle();

        if (! $this->configured()) {
            Log::warning('reCAPTCHA niet geconfigureerd, inzending zonder controle doorgelaten.', ['form' => $form]);

            return;
        }

        if (! $token = request()->input('g-recaptcha-response')) {
            Log::warning('Inzending zonder reCAPTCHA-token geweigerd.', ['form' => $form]);

            return false;
        }

        try {
            $response = Http::timeout(5)->post(
                'https://recaptchaenterprise.googleapis.com/v1/projects/'
                    .config('services.recaptcha.project_id').'/assessments'
                    .'?key='.config('services.recaptcha.api_key'),
                [
                    'event' => [
                        'token' => $token,
                        'siteKey' => config('services.recaptcha.site_key'),
                        'expectedAction' => 'submit',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA onbereikbaar, inzending doorgelaten.', ['form' => $form, 'fout' => $e->getMessage()]);

            return;
        }

        if ($response->failed()) {
            Log::error('reCAPTCHA gaf een fout, inzending doorgelaten.', ['form' => $form, 'status' => $response->status()]);

            return;
        }

        $result = $response->json();
        $score = $result['riskAnalysis']['score'] ?? 0;
        $valid = $result['tokenProperties']['valid'] ?? false;

        if (! $valid || $score < config('services.recaptcha.threshold', 0.5)) {
            Log::warning('Inzending geweigerd door reCAPTCHA.', ['form' => $form, 'score' => $score, 'geldig' => $valid]);

            return false;
        }
    }

    private function configured(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.api_key'))
            && filled(config('services.recaptcha.project_id'));
    }
}
