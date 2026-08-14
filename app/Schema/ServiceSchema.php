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
            'areaServed' => self::areaServed(),
            'url' => SiteUrl::absolute($uri),
        ];
    }

    /**
     * De gemeentes komen uit dezelfde bron als de LocalBusiness-nodes, zodat
     * er maar één plek is waar het werkgebied vandaan komt.
     *
     * @return list<string>
     */
    private static function areaServed(): array
    {
        $cities = [];

        foreach (LocationsSchema::nodes() as $node) {
            $city = $node['address']['addressLocality'] ?? '';

            if ($city !== '' && ! in_array($city, $cities, true)) {
                $cities[] = $city;
            }
        }

        return $cities;
    }
}
