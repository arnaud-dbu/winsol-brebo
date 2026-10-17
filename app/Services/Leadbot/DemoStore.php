<?php

namespace App\Services\Leadbot;

use Illuminate\Support\Str;
use Statamic\Contracts\Forms\Submission;
use Symfony\Component\Yaml\Yaml;

/**
 * De "ERP" van de demo: één JSON-bestand met klanten, aanvragen, open vragen
 * en tellers. In de echte versie is dit de intake van module 01 in de ERP.
 * De vorm volgt daarom het ERP-model: een klant als container, met een
 * Request eronder dat de salesstatussen draagt.
 */
class DemoStore
{
    public const STATUSES = [
        'nieuw' => 'Nieuw',
        'gebeld_1' => 'Eerste keer gebeld',
        'afspraak' => 'Afspraak gemaakt',
        'offerte_maken' => 'Offerte te maken',
        'offerte_verstuurd' => 'Offerte verstuurd',
    ];

    public function __construct(private ?string $path = null)
    {
        $this->path ??= storage_path('app/leadbot/state.json');
    }

    /**
     * @return array{customers: array<int, array<string, mixed>>, requests: array<int, array<string, mixed>>, questions: array<int, array<string, mixed>>, stats: array<string, int>, reset_at: string}
     */
    public function state(): array
    {
        return $this->mutate(fn (array $state) => $state);
    }

    public function reset(): void
    {
        $this->mutate(fn () => $this->seed(), fresh: true);
    }

    /**
     * Een opgeslagen inzending als Request onder een klant, met
     * duplicaatcontrole op e-mail en telefoon zoals module 01 dat zal doen.
     *
     * @param  array<string, string>  $readable  label => waarde, zie SubmissionReader
     * @return array<string, mixed> de aangemaakte aanvraag
     */
    public function addSubmission(Submission $submission, array $readable): array
    {
        $created = [];
        $data = $submission->data()->all();
        $form = $submission->form()->handle();

        $this->mutate(function (array $state) use ($submission, $data, $form, $readable, &$created) {
            $customer = $this->matchCustomer($state['customers'], (string) ($data['email'] ?? ''), (string) ($data['phone'] ?? ''));
            $existing = $customer !== null;

            if ($customer === null) {
                $customer = [
                    'id' => 'cus-'.(1000 + count($state['customers']) + 1),
                    'name' => (string) ($data['name'] ?? 'Onbekend'),
                    'email' => (string) ($data['email'] ?? ''),
                    'phone' => (string) ($data['phone'] ?? ''),
                    'address' => (string) ($data['address'] ?? ''),
                    'history' => [],
                ];
                $state['customers'][] = $customer;
            }

            $viaAssistant = ($data['via'] ?? null) === 'Website-assistent';
            $products = $readable['Voor welke producten?'] ?? $readable['Over welk product gaat het?'] ?? '';

            $created = [
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id(),
                'number' => $this->nextNumber($state['requests']),
                'created_at' => now()->toIso8601String(),
                'status' => 'nieuw',
                'form' => $form,
                'type' => match ($form) {
                    'herstelling' => 'herstelling',
                    'offerte' => 'offerte',
                    default => 'info',
                },
                'customer_id' => $customer['id'],
                'existing_customer' => $existing,
                'showroom' => (string) ($data['location'] ?? $data['branch'] ?? ''),
                'products' => array_values(array_filter(array_map('trim', explode(',', $products)))),
                'source' => $viaAssistant ? 'Website-assistent' : 'Website-formulier',
                'assigned_to' => null,
                'summary' => Str::limit((string) ($data['assistant_summary'] ?? $data['project'] ?? $data['problem'] ?? $data['message'] ?? ''), 400),
                'ai' => $viaAssistant ? [
                    'summary' => (string) ($data['assistant_summary'] ?? ''),
                    'urgency' => (string) ($data['assistant_urgency'] ?? 'normaal'),
                    'signals' => (array) ($data['assistant_signals'] ?? []),
                ] : null,
                'fields' => $readable,
                'transcript' => $this->parseTranscript((string) ($data['assistant_transcript'] ?? '')),
                'tracking' => (array) ($data['assistant_tracking'] ?? []),
                'locale' => (string) ($data['language'] ?? 'nl'),
                'page' => (string) ($data['assistant_page'] ?? ''),
                'live' => true,
            ];

            $state['requests'][] = $created;

            if ($viaAssistant) {
                $state['stats']['leads']++;
            }

            return $state;
        });

        return $created;
    }

    public function recordQuestion(string $question, string $locale, bool $answered): void
    {
        $this->mutate(function (array $state) use ($question, $locale, $answered) {
            $state['stats']['questions']++;

            if ($answered) {
                $state['stats']['answered']++;

                return $state;
            }

            $state['questions'][] = [
                'id' => (string) Str::uuid(),
                'question' => Str::limit($question, 500),
                'locale' => $locale,
                'asked_at' => now()->toIso8601String(),
                'answer' => null,
                'live' => true,
            ];

            return $state;
        });
    }

    public function recordFeedback(bool $helpful): void
    {
        $key = $helpful ? 'helpful' : 'unhelpful';

        $this->mutate(function (array $state) use ($key) {
            $state['stats'][$key] = ($state['stats'][$key] ?? 0) + 1;

            return $state;
        });
    }

    /**
     * Markeert een open vraag als beantwoord. Het antwoord zelf gaat als
     * FAQ-item naar Statamic, zie DemoController::answer().
     *
     * @return array<string, mixed>|null de vraag
     */
    public function markAnswered(string $id, string $answer): ?array
    {
        $found = null;

        $this->mutate(function (array $state) use ($id, $answer, &$found) {
            foreach ($state['questions'] as &$question) {
                if ($question['id'] === $id) {
                    $question['answer'] = $answer;
                    $found = $question;
                }
            }

            return $state;
        });

        return $found;
    }

    public function setStatus(string $id, string $status): bool
    {
        if (! array_key_exists($status, self::STATUSES)) {
            return false;
        }

        $found = false;

        $this->mutate(function (array $state) use ($id, $status, &$found) {
            foreach ($state['requests'] as &$request) {
                if ($request['id'] === $id) {
                    $request['status'] = $status;
                    $found = true;
                }
            }

            return $state;
        });

        return $found;
    }

    /**
     * Een bestaande klant herkennen op e-mail of telefoon, zoals de
     * duplicaatcontrole in module 01. Telefoonnummers worden vergeleken op
     * hun laatste negen cijfers, zodat 0476... en +32 476... samenvallen.
     *
     * @param  array<int, array<string, mixed>>  $customers
     * @return array<string, mixed>|null
     */
    private function matchCustomer(array $customers, string $email, string $phone): ?array
    {
        $email = Str::lower(trim($email));
        $phone = substr(preg_replace('/\D/', '', $phone), -9);

        foreach ($customers as $customer) {
            if ($email !== '' && Str::lower($customer['email']) === $email) {
                return $customer;
            }

            if (strlen($phone) === 9 && substr(preg_replace('/\D/', '', (string) $customer['phone']), -9) === $phone) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{role: string, text: string}>
     */
    private function parseTranscript(string $transcript): array
    {
        if (trim($transcript) === '') {
            return [];
        }

        return collect(preg_split('/\n(?=(?:Klant|Assistent): )/', trim($transcript)))
            ->map(fn ($line) => [
                'role' => str_starts_with($line, 'Klant: ') ? 'user' : 'bot',
                'text' => (string) Str::after($line, ': '),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $requests
     */
    private function nextNumber(array $requests): string
    {
        $highest = collect($requests)
            ->map(fn ($request) => (int) Str::afterLast($request['number'], '-'))
            ->max() ?? 0;

        return sprintf('WB-%s-%04d', now()->year, $highest + 1);
    }

    /**
     * Leest, wijzigt en bewaart de toestand onder een exclusieve lock, zodat
     * twee gelijktijdige gesprekken elkaars aanvraag niet overschrijven.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function mutate(callable $change, bool $fresh = false): array
    {
        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0775, true);
        }

        $handle = fopen($this->path, 'c+');
        flock($handle, LOCK_EX);

        try {
            $raw = stream_get_contents($handle);
            $state = (! $fresh && $raw) ? json_decode($raw, true) : null;
            $state = is_array($state) && isset($state['stats']) ? $state : $this->seed();

            $state = $change($state);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($handle);

            return $state;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seed(): array
    {
        $demo = Yaml::parseFile(resource_path('leadbot/demo.yaml'));
        $customers = collect($demo['customers'])->keyBy('id');

        $requests = collect($demo['requests'])->map(function (array $request) use (&$customers) {
            $customerId = $request['customer_id'] ?? null;

            if ($customerId === null) {
                $customerId = 'cus-'.Str::lower(Str::random(6));
                $customers[$customerId] = [
                    'id' => $customerId,
                    'name' => $request['customer_name'],
                    'email' => Str::slug($request['customer_name'], '.').'@example.be',
                    'phone' => '',
                    'address' => '',
                    'history' => [],
                ];
            }

            return [
                'id' => (string) Str::uuid(),
                'submission_id' => null,
                'number' => $request['number'],
                'created_at' => now()->subDays($request['days_ago'])->subHours(random_int(1, 8))->toIso8601String(),
                'status' => $request['status'],
                'form' => $request['type'],
                'type' => $request['type'],
                'customer_id' => $customerId,
                'existing_customer' => ! empty($customers[$customerId]['history']),
                'showroom' => $request['showroom'],
                'products' => $request['products'],
                'source' => $request['source'],
                'assigned_to' => $request['assigned_to'],
                'summary' => $request['summary'],
                'ai' => null,
                'fields' => [],
                'transcript' => [],
                'tracking' => [],
                'locale' => 'nl',
                'page' => '',
                'live' => false,
            ];
        })->all();

        $questions = collect($demo['questions'])->map(fn (array $question) => [
            'id' => (string) Str::uuid(),
            'question' => $question['question'],
            'locale' => $question['locale'],
            'asked_at' => now()->subDays($question['days_ago'])->toIso8601String(),
            'answer' => null,
            'live' => false,
        ])->all();

        return [
            'customers' => $customers->values()->all(),
            'requests' => $requests,
            'questions' => $questions,
            'stats' => ['questions' => 0, 'answered' => 0, 'leads' => 0, 'helpful' => 0, 'unhelpful' => 0],
            'reset_at' => now()->toIso8601String(),
        ];
    }
}
