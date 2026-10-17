<?php

namespace App\Services\Leadbot;

use Statamic\Facades\Entry;

/**
 * Wat de widget in de browser nodig heeft, als JSON in de pagina. Voor elke
 * bezoeker gelijk, zodat het in de statische cache mag: alles wat per
 * bezoeker verschilt (token, laadtijd) haalt de widget zelf op.
 */
class WidgetConfig
{
    public function __construct(private Knowledge $knowledge, private FormSchema $forms) {}

    public function json(string $locale): string
    {
        $locale = in_array($locale, ['nl', 'fr', 'en'], true) ? $locale : 'nl';

        return json_encode([
            'locale' => $locale,
            'strings' => trans('leadbot', [], $locale),
            'ranges' => $this->knowledge->ranges($locale),
            'products' => $this->knowledge->products($locale),
            'showrooms' => collect($this->knowledge->showrooms())
                ->map(fn ($showroom) => collect($showroom)->except('hours')->all())
                ->all(),
            'forms' => collect($this->forms->all($locale))
                ->map(fn ($form, $handle) => [
                    ...$form,
                    'icon' => config("leadbot.forms.{$handle}.icon"),
                    'action' => route('statamic.forms.submit', $handle, absolute: false),
                ])
                ->all(),
            'postcodeRules' => config('leadbot.postcode_showroom'),
            'privacyUrl' => $this->privacyUrl($locale),
            'whatsapp' => preg_replace('/\D/', '', (string) config('leadbot.whatsapp')) ?: null,
            'mapsKey' => config('services.google.maps_key') ?: null,
            'endpoints' => [
                'session' => route('leadbot.session', absolute: false),
                'ask' => route('leadbot.ask', absolute: false),
                'stream' => route('leadbot.stream', absolute: false),
                'extract' => route('leadbot.extract', absolute: false),
                'helpdesk' => route('leadbot.helpdesk', ['locale' => $locale], absolute: false),
                'feedback' => route('leadbot.feedback', absolute: false),
            ],
            'usesModel' => filled(config('leadbot.anthropic.key')),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function privacyUrl(string $locale): ?string
    {
        $page = Entry::query()->where('collection', 'legal')->where('site', 'nl')->where('slug', 'privacy-policy')->first();

        return ($page?->in($locale) ?? $page)?->url();
    }
}
