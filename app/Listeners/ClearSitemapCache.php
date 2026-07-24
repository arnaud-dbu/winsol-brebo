<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\TermSaved;
use Statamic\Events\TermDeleted;

class ClearSitemapCache
{
    public function subscribe($events): array
    {
        return [
            EntrySaved::class   => 'handleEntry',
            EntryDeleted::class => 'handleEntry',
            TermSaved::class    => 'handleTerm',
            TermDeleted::class  => 'handleTerm',
        ];
    }

    /**
     * Clear the index + the specific collection sitemap.
     */
    public function handleEntry($event): void
    {
        Cache::forget('sitemap.index');

        if ($handle = $event->entry->collectionHandle()) {
            Cache::forget("sitemap.collection.{$handle}");
        }
    }

    /**
     * Clear the index + the taxonomy sitemap.
     */
    public function handleTerm(): void
    {
        Cache::forget('sitemap.index');
        Cache::forget('sitemap.taxonomies');
    }
}
