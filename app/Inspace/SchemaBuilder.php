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
        $out = [];

        foreach ($mapping as $apiName => $blueprintHandle) {
            $field = $blueprint->field($blueprintHandle);

            if ($field === null) {
                continue;
            }

            $out[$apiName] = $this->describe($field);
        }

        $out['status'] = ['type' => 'enum', 'required' => false, 'values' => ['draft', 'published']];
        $out['external_id'] = ['type' => 'string', 'required' => false];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Field $field): array
    {
        $required = $field->isRequired();

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
                'max' => $field->get('character_limit'),
            ], fn ($v) => $v !== null),
        };
    }
}
