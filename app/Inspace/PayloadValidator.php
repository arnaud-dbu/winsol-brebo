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
        // `required_on_create` is de enige plek die bepaalt wat bij het
        // aanmaken verplicht is: een contractkeuze, geen bladwijzer naar het
        // CMS-blueprint. `SchemaBuilder::describe()` leest dezelfde lijst,
        // zodat GET /schema nooit iets anders beweert dan wat hier echt
        // wordt afgedwongen.
        $requiredOnCreate = config("inspace.writable.{$collection}.required_on_create", []);
        $required = fn (string $field): array => $creating && in_array($field, $requiredOnCreate, true)
            ? ['required']
            : ['sometimes'];

        return [
            'title' => [...$required('title'), 'string', 'max:'.$this->maxLength($collection, 'title', 255)],
            'theme' => [...$required('theme'), 'string'],
            'image' => [...$required('image'), 'string'],
            'content' => [...$required('content'), 'array'],
            'content.*.type' => ['required', 'string'],
            'intro' => ['sometimes', 'nullable', 'string'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:'.$this->maxLength($collection, 'slug', 200)],
            'date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,published'],
            'external_id' => ['sometimes', 'nullable', 'string', 'max:'.$this->maxLength($collection, 'external_id', 255)],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:'.$this->maxLength($collection, 'meta_title', 60)],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:'.$this->maxLength($collection, 'meta_description', 160)],
            'meta_image' => ['sometimes', 'nullable', 'string'],
            'seo_noindex' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Leest de tekenlimiet uit dezelfde canonieke `max_lengths`-config die
     * `SchemaBuilder` ook gebruikt. De `$default` is uitsluitend een
     * vangnet voor een collectie die de sleutel niet configureert, niet een
     * tweede bron van waarheid voor `articles`.
     */
    private function maxLength(string $collection, string $field, int $default): int
    {
        return (int) config("inspace.writable.{$collection}.max_lengths.{$field}", $default);
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
