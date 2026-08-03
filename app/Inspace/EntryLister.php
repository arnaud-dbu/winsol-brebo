<?php

namespace App\Inspace;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryLister
{
    private const MAX_PER_PAGE = 200;

    /**
     * @param  array{collection?: ?string, editable?: ?bool, status?: ?string, page?: ?int, per_page?: ?int}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function list(array $filters): array
    {
        $perPage = min((int) ($filters['per_page'] ?? 50), self::MAX_PER_PAGE);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $entries = collect($this->handles($filters['collection'] ?? null))
            ->flatMap(fn (string $handle) => Entry::query()->where('collection', $handle)->get()->all())
            ->filter(fn (EntryContract $entry) => $this->visible($entry, $filters))
            ->values();

        $rows = $entries
            ->slice(($page - 1) * $perPage, $perPage)
            ->map($this->row(...))
            ->values()
            ->all();

        return [
            'data' => $rows,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $entries->count()],
        ];
    }

    /**
     * @return list<string>
     */
    private function handles(?string $requested): array
    {
        $readable = config('inspace.readable') ?? Collection::handles()->all();
        $readable = array_values($readable);

        if ($requested === null) {
            return $readable;
        }

        return in_array($requested, $readable, true) ? [$requested] : [];
    }

    /**
     * Drafts van schrijfbare collecties blijven zichtbaar: Nova moet een
     * artikel dat het zelf als draft aanmaakte kunnen terugvinden. Drafts van
     * de rest zijn intern werk van de klant.
     *
     * @param  array<string, mixed>  $filters
     */
    private function visible(EntryContract $entry, array $filters): bool
    {
        $editable = $this->editable($entry);

        if (! $editable && ! $entry->published()) {
            return false;
        }

        if (isset($filters['editable']) && $filters['editable'] !== null && $editable !== (bool) $filters['editable']) {
            return false;
        }

        $status = $filters['status'] ?? null;

        return $status === null || $this->status($entry) === $status;
    }

    private function editable(EntryContract $entry): bool
    {
        return array_key_exists($entry->collectionHandle(), config('inspace.writable', []));
    }

    private function status(EntryContract $entry): string
    {
        return $entry->published() ? 'published' : 'draft';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EntryContract $entry): array
    {
        return [
            'id' => $entry->id(),
            'collection' => $entry->collectionHandle(),
            'title' => $entry->value('title'),
            'url' => $entry->url(),
            'status' => $this->status($entry),
            'updated_at' => $entry->lastModified()?->toIso8601String(),
            'editable' => $this->editable($entry),
        ];
    }
}
