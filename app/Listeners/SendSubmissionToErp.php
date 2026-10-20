<?php

namespace App\Listeners;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Forms\Submission;
use Statamic\Events\SubmissionCreated;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Field;

/**
 * Stuurt een offerte of herstelling ondertekend naar de Berichtenbak van de
 * ERP, volgens het contract van WIN-1479. Aanvaardt de ERP haar, dan slaat
 * App\Jobs\SendFormEmail de mail naar offertes@ over; anders gaat die mail
 * zoals voorheen, zodat geen aanvraag verloren gaat.
 *
 * Op SubmissionCreated en niet op FormSubmitted: de volgorde van ontdekte
 * listeners ligt niet vast, en SubmissionCreated valt pas wanneer RejectSpam,
 * VerifyRecaptcha en RecordSubmissionLanguage allemaal door zijn. Zo bereikt
 * spam de ERP nooit en draagt de inzending al haar taal.
 */
class SendSubmissionToErp
{
    public const DELIVERED = 'delivered_to_erp';

    private const FORMS = ['offerte', 'herstelling'];

    /**
     * Akkoordvinkjes, en de taal die als eigen sleutel meegaat. De ERP kent
     * ze niet als veld en weigert dan het hele lichaam.
     */
    private const OMITTED_FIELDS = ['gdpr', 'rates_agreed', 'warranty_terms', 'language'];

    private const TIMEOUT_SECONDS = 5;

    /**
     * De ERP haalt de bijlagen op terwijl de post loopt; het uur is marge,
     * geen bewaartermijn.
     */
    private const LINK_LIFETIME_MINUTES = 60;

    public function handle(SubmissionCreated $event): void
    {
        $submission = $event->submission;
        $form = $submission->form()->handle();

        if (! in_array($form, self::FORMS, true)) {
            return;
        }

        $url = config('services.erp.form_url');
        $secret = config('services.erp.form_secret');

        if (blank($url) || blank($secret)) {
            Log::warning('ERP niet geconfigureerd, aanvraag gaat als mail.', ['formulier' => $form]);

            return;
        }

        try {
            $body = (string) json_encode($this->body($submission), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['X-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret)])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            Log::error('ERP niet bereikt, aanvraag gaat als mail.', ['formulier' => $form, 'fout' => $e->getMessage()]);

            return;
        }

        if (! $response->successful()) {
            Log::error('ERP weigerde de aanvraag, die gaat als mail.', ['formulier' => $form, 'status' => $response->status()]);

            return;
        }

        $submission->setSupplement(self::DELIVERED, true);
    }

    /**
     * @return array{id: string, form: string, submitted_at: string, language: string, fields: array<string, mixed>, attachments: list<array{field: string, filename: string, mime: string, size: int, url: string}>}
     */
    private function body(Submission $submission): array
    {
        $uploadFields = $this->uploadFields($submission);

        return [
            'id' => (string) $submission->id(),
            'form' => $submission->form()->handle(),
            'submitted_at' => $submission->date()->toIso8601String(),
            'language' => $submission->get('language'),
            'fields' => $submission->data()->except([...self::OMITTED_FIELDS, ...$uploadFields->keys()])->all(),
            'attachments' => $this->attachments($submission, $uploadFields),
        ];
    }

    /**
     * @return Collection<string, Field>
     */
    private function uploadFields(Submission $submission): Collection
    {
        return $submission->form()->blueprint()->fields()->all()
            ->filter(fn (Field $field): bool => $field->type() === 'assets');
    }

    /**
     * @param  Collection<string, Field>  $uploadFields
     * @return list<array{field: string, filename: string, mime: string, size: int, url: string}>
     */
    private function attachments(Submission $submission, Collection $uploadFields): array
    {
        return $uploadFields
            ->flatMap(function (Field $field, string $handle) use ($submission): array {
                $container = AssetContainer::find($field->get('container'));

                return collect(Arr::wrap($submission->get($handle)))
                    ->map(fn (string $path): Asset => $container->asset($path) ?? throw new \RuntimeException("Bijlage {$path} niet gevonden."))
                    ->map(fn (Asset $asset): array => [
                        'field' => $handle,
                        'filename' => $asset->basename(),
                        'mime' => $asset->mimeType(),
                        'size' => $asset->size(),
                        'url' => $container->disk()->filesystem()->temporaryUrl($asset->path(), now()->addMinutes(self::LINK_LIFETIME_MINUTES)),
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }
}
