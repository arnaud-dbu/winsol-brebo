<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Http;
use Statamic\Events\FormSubmitted;

class VerifyRecaptcha
{
    public function handle(FormSubmitted $event)
    {
        $token = request()->input('g-recaptcha-response');

        if (! $token) {
            return false;
        }

        $projectId = config('services.recaptcha.project_id');
        $apiKey = config('services.recaptcha.api_key');

        $response = Http::post("https://recaptchaenterprise.googleapis.com/v1/projects/{$projectId}/assessments?key={$apiKey}", [
            'event' => [
                'token' => $token,
                'siteKey' => config('services.recaptcha.site_key'),
                'expectedAction' => 'submit',
            ],
        ]);

        $result = $response->json();

        $score = $result['riskAnalysis']['score'] ?? 0;
        $valid = $result['tokenProperties']['valid'] ?? false;

        if (! $valid || $score < config('services.recaptcha.threshold', 0.5)) {
            return false;
        }
    }
}
