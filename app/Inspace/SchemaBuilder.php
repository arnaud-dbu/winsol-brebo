<?php

namespace App\Inspace;

use Statamic\Facades\Collection;
use Statamic\Fields\Field;

class SchemaBuilder
{
    /**
     * @return array{collections: array<string, array{writable: bool, route: ?string, fields: array<string, array<string, mixed>>}>}
     */
    public function build(): array
    {
        $collections = [];

        foreach (array_keys(config('inspace.writable', [])) as $handle) {
            $collection = Collection::findByHandle($handle);

            if ($collection === null) {
                continue;
            }

            $collections[$handle] = [
                'writable' => true,
                'route' => $collection->route($collection->sites()->first()),
                'fields' => $this->fields($handle),
            ];
        }

        return ['collections' => $collections];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fields(string $handle): array
    {
        $blueprint = Collection::findByHandle($handle)->entryBlueprint();
        $mapping = config("inspace.writable.{$handle}.fields", []);
        $requiredOnCreate = config("inspace.writable.{$handle}.required_on_create", []);
        $maxLengths = config("inspace.writable.{$handle}.max_lengths", []);
        $out = [];

        foreach ($mapping as $apiName => $blueprintHandle) {
            $field = $blueprint->field($blueprintHandle);

            if ($field === null) {
                continue;
            }

            $out[$apiName] = $this->describe(
                $field,
                in_array($apiName, $requiredOnCreate, true),
                $maxLengths[$apiName] ?? null
            );
        }

        $out['status'] = ['type' => 'enum', 'required' => false, 'values' => ['draft', 'published']];
        $out['external_id'] = array_filter([
            'type' => 'string',
            'required' => in_array('external_id', $requiredOnCreate, true),
            'max' => $maxLengths['external_id'] ?? null,
        ], fn ($v) => $v !== null);

        return $out;
    }

    /**
     * `$requiredOnCreate` komt uit `config('inspace.writable.<collectie>.required_on_create')`
     * — dezelfde lijst waar `PayloadValidator::rules()` uit leest — en is
     * bewust de enige bron voor `required` hier, niet een unie met
     * `$field->isRequired()`. Die twee liepen namelijk niet alleen uiteen in
     * de richting die deze fixronde aanleiding gaf (`image`/`content` zijn
     * blueprint-optioneel maar contract-verplicht): `date` is in het
     * blueprint wél verplicht (voor de CP-publiceerform) maar in het
     * contract bewust optioneel, want `EntryWriter::create()` valt terug op
     * `now()` als het ontbreekt. Een `||`-unie zou dat tweede geval stil
     * verkeerd melden. `required_on_create` is dus niet "extra bovenop het
     * blueprint" maar de volledige en enige waarheid over wat `POST /pages`
     * verplicht stelt.
     *
     * `$maxOverride` komt uit dezelfde config (`max_lengths`) en wint van het
     * `character_limit` op het blueprintveld: `title` heeft daar geen
     * `character_limit` maar wordt wel met `max:255` gevalideerd, en zonder
     * deze override zou `GET /schema` daar stil geen `max` tonen.
     *
     * @return array<string, mixed>
     */
    private function describe(Field $field, bool $requiredOnCreate, ?int $maxOverride = null): array
    {
        $required = $requiredOnCreate;

        return match ($field->type()) {
            'bard' => [
                'type' => 'blocks',
                'required' => $required,
                'writable_types' => ['text'],
                'opaque_types' => (new SetTypes)->of($field),
                'allowed_html' => (new HtmlWhitelist)->of($field),
            ],
            'assets' => ['type' => 'asset', 'required' => $required],
            'terms' => [
                'type' => 'enum',
                'required' => $required,
                'values' => (new TermValues)->of($field),
            ],
            'toggle' => ['type' => 'bool', 'required' => $required],
            'date' => ['type' => 'date', 'required' => $required],
            default => array_filter([
                'type' => 'string',
                'required' => $required,
                'max' => $maxOverride ?? $field->get('character_limit'),
            ], fn ($v) => $v !== null),
        };
    }
}
