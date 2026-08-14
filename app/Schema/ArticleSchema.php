<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;

class ArticleSchema
{
    /**
     * @return array<string, mixed>|null
     */
    public static function node(?EntryContract $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        $title = trim((string) $entry->get('title'));

        if ($title === '') {
            return null;
        }

        $uri = (string) $entry->uri();

        return [
            '@type' => 'Article',
            '@id' => SiteUrl::absolute($uri).'#article',
            'headline' => $title,
            'datePublished' => $entry->date()?->toIso8601String(),
            'publisher' => ['@id' => OrganizationSchema::id()],
            'mainEntityOfPage' => SiteUrl::absolute($uri),
        ];
    }
}
