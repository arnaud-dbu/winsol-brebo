<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;

/**
 * Product- en rangepagina's zijn maatwerk zonder prijs of SKU, dus Service
 * past en Product niet: zonder `offers` levert Product geen rich result en
 * wel een waarschuwing in Search Console. `areaServed` koppelt de pagina
 * bovendien aan het lokale bereik, en dat is waar deze site kan winnen.
 */
class ServiceSchema
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
            '@type' => 'Service',
            '@id' => SiteUrl::absolute($uri).'#service',
            'name' => $title,
            'serviceType' => 'Plaatsing van '.$title,
            'provider' => ['@id' => OrganizationSchema::id()],
            'areaServed' => LocationsSchema::cities(),
            'url' => SiteUrl::absolute($uri),
        ];
    }
}
