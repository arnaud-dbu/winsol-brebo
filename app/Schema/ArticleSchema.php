<?php

namespace App\Schema;

use Statamic\Contracts\Assets\Asset as AssetContract;
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
            'image' => self::imageUrl($entry),
            'datePublished' => $entry->date()?->toIso8601String(),
            'dateModified' => $entry->lastModified()?->toIso8601String(),
            'publisher' => ['@id' => OrganizationSchema::id()],
            'mainEntityOfPage' => SiteUrl::absolute($uri),
        ];
    }

    /**
     * `image` komt uit de page-header-imagefieldset en is op de entry een pad
     * binnen het assetcontainer, geen URL. `augmentedValue()` lost het pad op
     * naar het Asset-object waar `absoluteUrl()` wél op werkt.
     */
    private static function imageUrl(EntryContract $entry): ?string
    {
        $asset = $entry->augmentedValue('image')->value();

        return $asset instanceof AssetContract ? $asset->absoluteUrl() : null;
    }
}
