<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

trait CreatesTemporaryContent
{
    /** @var list<string> */
    private array $temporaryEntryIds = [];

    /**
     * Isoleert de bestanden van de assets-container. Haal de container daarna
     * met `find()` op en nooit met `make()->save()` — dat schrijft
     * `content/assets/{handle}.yaml` terug naar de werkkopie.
     */
    protected function fakeAssetDisk(): void
    {
        Storage::fake('r2');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function temporaryEntry(string $collection, string $slug, array $data): EntryContract
    {
        $entry = Entry::make()
            ->collection($collection)
            ->locale('nl')
            ->slug($slug)
            ->data($data);

        $entry->save();

        $this->temporaryEntryIds[] = $entry->id();

        return $entry;
    }

    protected function deleteTemporaryEntries(): void
    {
        foreach ($this->temporaryEntryIds as $id) {
            Entry::find($id)?->delete();
        }

        $this->temporaryEntryIds = [];
    }
}
