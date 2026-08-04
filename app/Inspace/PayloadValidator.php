<?php

namespace App\Inspace;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Collection;

class PayloadValidator
{
    /** Velden die geen blueprint-handle hebben maar wel toegestaan zijn. */
    private const EXTRA = ['status', 'external_id', 'site'];

    public function __construct(private readonly EntryMapper $mapper) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function validate(string $collection, array $payload, bool $creating): void
    {
        $mapping = $this->mapper->mapping($collection);
        $known = array_merge(array_keys($mapping), self::EXTRA);

        $unknown = array_diff(array_keys($payload), $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages(
                array_fill_keys(
                    array_values($unknown),
                    'Onbekend veld. Geldige velden: '.implode(', ', $known).'.'
                )
            );
        }

        Validator::make($payload, $this->rules($collection, $creating))->validate();

        $this->validateTheme($collection, $payload);
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(string $collection, bool $creating): array
    {
        $required = $creating ? ['required'] : ['sometimes'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'theme' => [...$required, 'string'],
            'image' => [...$required, 'string'],
            'content' => [...$required, 'array'],
            'content.*.type' => ['required', 'string'],
            'intro' => ['sometimes', 'nullable', 'string'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200'],
            'date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,published'],
            'external_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'meta_image' => ['sometimes', 'nullable', 'string'],
            'seo_noindex' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Apart van de rules-array omdat de geldige waarden live uit de taxonomie
     * komen en in de foutmelding moeten staan: `create: false` betekent dat
     * Nova er geen mag bijmaken, dus zonder die lijst zit het vast.
     *
     * @param  array<string, mixed>  $payload
     */
    private function validateTheme(string $collection, array $payload): void
    {
        if (! array_key_exists('theme', $payload)) {
            return;
        }

        $handle = $this->mapper->mapping($collection)['theme'] ?? null;

        if ($handle === null) {
            return;
        }

        $field = Collection::findByHandle($collection)->entryBlueprint()->field($handle);
        $valid = (new TermValues)->of($field);

        sort($valid);

        if (! in_array($payload['theme'], $valid, true)) {
            throw ValidationException::withMessages([
                'theme' => 'Onbekend thema. Geldige waarden: '.implode(', ', $valid).'.',
            ]);
        }
    }
}
