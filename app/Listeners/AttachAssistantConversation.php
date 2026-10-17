<?php

namespace App\Listeners;

use App\Services\Leadbot\Assistant;
use App\Services\Leadbot\SubmissionReader;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Statamic\Events\FormSubmitted;

/**
 * Een inzending die via de website-assistent binnenkwam, draagt het gesprek
 * met zich mee: de assistent post naar hetzelfde formulier als de pagina en
 * stuurt in `_leadbot` het gesprek, de pagina en de herkomst mee.
 *
 * Hier komt dat in de inzending zelf terecht, samen met een samenvatting.
 * Zo staat het in de CP bij de aanvraag én in de melding aan Winsol (zie
 * emails/notification), zonder dat de blueprint van het formulier een veld
 * extra krijgt dat dan ook op de pagina zou verschijnen.
 *
 * Weigert nooit iets: dat blijft het werk van RejectSpam en VerifyRecaptcha.
 */
class AttachAssistantConversation
{
    private const TRACKING_KEYS = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'landing', 'referrer'];

    public function __construct(private Assistant $assistant, private SubmissionReader $reader) {}

    public function handle(FormSubmitted $event): void
    {
        if (! config('leadbot.enabled')) {
            return;
        }

        $payload = json_decode((string) request()->input('_leadbot'), true);

        if (! is_array($payload)) {
            return;
        }

        $transcript = collect($payload['transcript'] ?? [])
            ->filter(fn ($turn) => is_array($turn) && in_array($turn['role'] ?? null, ['user', 'bot'], true) && is_string($turn['text'] ?? null))
            ->map(fn ($turn) => ['role' => $turn['role'], 'text' => Str::limit(trim($turn['text']), 1500)])
            ->take(-60)
            ->values()
            ->all();

        $submission = $event->submission;
        $locale = in_array($payload['locale'] ?? null, ['nl', 'fr', 'en'], true) ? $payload['locale'] : 'nl';
        $summary = $this->assistant->summarize($submission->form()->handle(), $this->reader->read($submission), $transcript, $locale);

        $submission->set('via', 'Website-assistent');
        $submission->set('assistant_summary', $summary['summary']);
        $submission->set('assistant_urgency', $summary['urgency']);
        $submission->set('assistant_signals', $summary['signals']);
        $submission->set('assistant_transcript', collect($transcript)
            ->map(fn ($turn) => ($turn['role'] === 'user' ? 'Klant' : 'Assistent').': '.$turn['text'])
            ->implode("\n"));
        $submission->set('assistant_page', Str::limit((string) ($payload['page'] ?? ''), 300, ''));
        $submission->set('assistant_tracking', collect(Arr::only((array) ($payload['tracking'] ?? []), self::TRACKING_KEYS))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn ($value) => Str::limit($value, 200, ''))
            ->all());
    }
}
