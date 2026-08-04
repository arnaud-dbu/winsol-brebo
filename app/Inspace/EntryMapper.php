<?php

namespace App\Inspace;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Fields\Field;

class EntryMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toApi(EntryContract $entry): array
    {
        $handle = $entry->collectionHandle();
        $editable = $this->isWritable($handle);

        $base = [
            'id' => $entry->id(),
            'collection' => $handle,
            'editable' => $editable,
            'status' => $entry->published() ? 'published' : 'draft',
            'url' => $entry->url(),
            'title' => $entry->value('title'),
            'updated_at' => $entry->lastModified()?->toIso8601String(),
        ];

        if (! $editable) {
            return $base + [
                'content' => null,
                'meta_title' => $entry->value('meta_title'),
                'meta_description' => $entry->value('meta_description'),
                'meta_image' => $this->firstOf($entry->value('meta_image')),
                'seo_noindex' => (bool) $entry->value('seo_noindex'),
            ];
        }

        $out = $base + ['external_id' => $entry->get('external_id')];

        foreach ($this->mapping($handle) as $apiName => $blueprintHandle) {
            $out[$apiName] = $this->read($entry, $handle, $apiName, $blueprintHandle);
        }

        return $out;
    }

    public function converterFor(string $collection): BlockConverter
    {
        return new BlockConverter($this->contentField($collection));
    }

    public function isWritable(string $collection): bool
    {
        return array_key_exists($collection, config('inspace.writable', []));
    }

    /**
     * @return array<string, string>
     */
    public function mapping(string $collection): array
    {
        return config("inspace.writable.{$collection}.fields", []);
    }

    /**
     * `array_search` teruggeven met `?:` behandelt een gevonden sleutel `"0"`
     * hetzelfde als "niet gevonden", omdat beide falsy zijn. Een expliciete
     * `=== false`-vergelijking houdt die twee uit elkaar.
     */
    public function contentApiName(string $collection): ?string
    {
        $contentField = config("inspace.writable.{$collection}.content_field");
        $apiName = array_search($contentField, $this->mapping($collection), true);

        return $apiName === false ? null : $apiName;
    }

    private function contentField(string $collection): Field
    {
        return Collection::findByHandle($collection)
            ->entryBlueprint()
            ->field((string) config("inspace.writable.{$collection}.content_field"));
    }

    private function field(string $collection, string $blueprintHandle): ?Field
    {
        return Collection::findByHandle($collection)
            ->entryBlueprint()
            ->field($blueprintHandle);
    }

    /**
     * Schakelt op het véldtype uit het blueprint, niet op de API-naam: die
     * laatste komt uit config en is voor een nieuwe schrijfbare collectie
     * niet gegarandeerd `theme`, `image` of `meta_image`. Een `terms`-veld
     * met `max_items: 1` en een `assets`-veld met `max_files: 1` leveren
     * allebei een lijst van één op, en delen daarom `firstOf()`.
     */
    private function read(EntryContract $entry, string $collection, string $apiName, string $blueprintHandle): mixed
    {
        if ($apiName === $this->contentApiName($collection)) {
            return $this->converterFor($collection)->toBlocks((array) $entry->get($blueprintHandle, []));
        }

        $raw = $entry->get($blueprintHandle);

        return match ($this->field($collection, $blueprintHandle)?->type()) {
            'slug' => $entry->slug(),
            'assets', 'terms' => $this->firstOf($raw),
            'toggle' => (bool) $raw,
            'date' => $entry->date()?->toDateString(),
            default => $raw,
        };
    }

    private function firstOf(mixed $value): ?string
    {
        $first = is_array($value) ? ($value[0] ?? null) : $value;

        return $first === null ? null : (string) $first;
    }
}
