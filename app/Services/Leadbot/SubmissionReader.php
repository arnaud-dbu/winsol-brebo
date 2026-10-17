<?php

namespace App\Services\Leadbot;

use Statamic\Contracts\Forms\Submission;

/**
 * Een inzending als leesbare lijst "label => waarde", in het Nederlands,
 * zoals wie de aanvraag opvolgt ze leest. Keuzes worden hun label, een
 * toggle wordt "Ja", bestanden worden hun naam.
 */
class SubmissionReader
{
    public function __construct(private FormSchema $forms) {}

    /**
     * @return array<string, string>
     */
    public function read(Submission $submission): array
    {
        $handle = $submission->form()->handle();
        $schema = $this->forms->form($handle, 'nl');
        $data = $submission->data()->all();
        $readable = [];

        // Een Franse of Engelse klant kiest uit opties in zijn taal (bv. de
        // Franse brochure-pdf); de Nederlandse labels winnen waar ze bestaan.
        $labelsInAllLanguages = collect(['en', 'fr', 'nl'])
            ->flatMap(fn ($locale) => collect($this->forms->form($handle, $locale)['steps'] ?? [])->flatten(1))
            ->groupBy('handle')
            ->map(fn ($fields) => $fields->flatMap(fn ($field) => collect($field['options'] ?? [])->pluck('label', 'value')));

        foreach (collect($schema['steps'] ?? [])->flatten(1) as $field) {
            $value = $data[$field['handle']] ?? null;

            if ($value === null || $value === '' || $value === [] || $value === false) {
                continue;
            }

            $labels = $labelsInAllLanguages[$field['handle']] ?? collect();

            $readable[$field['label']] = match ($field['kind']) {
                'choice' => (string) ($labels[$value] ?? $value),
                'multi' => collect((array) $value)->map(fn ($item) => $labels[$item] ?? $item)->implode(', '),
                'consent', 'yesno' => $value === 'nee' ? 'Nee' : 'Ja',
                'files' => collect((array) $value)->map(fn ($path) => basename((string) $path))->implode(', '),
                default => is_array($value) ? implode(', ', $value) : (string) $value,
            };
        }

        return $readable;
    }
}
