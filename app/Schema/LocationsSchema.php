<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class LocationsSchema
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function nodes(): array
    {
        // Eén LocalBusiness per vestiging (niet per taal), en OpeningHours
        // parseert de Nederlandse dagnamen — zie entries().
        return self::entries()
            ->map(fn (EntryContract $entry) => self::node($entry))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * De telefoonnummers van de vestigingen, ontdubbeld en in boomvolgorde.
     *
     * Twee showrooms delen dezelfde centrale, dus zonder ontdubbelen zou het
     * organisatieknooppunt hetzelfde nummer twee keer opgeven.
     *
     * @return list<string>
     */
    public static function phones(): array
    {
        $nummers = [];

        foreach (self::entries() as $entry) {
            $nummer = trim((string) $entry->get('phone'));

            if ($nummer !== '' && ! in_array($nummer, $nummers, true)) {
                $nummers[] = $nummer;
            }
        }

        return $nummers;
    }

    /**
     * De vestigingssteden, ontdubbeld, rechtstreeks uit de entries — niet uit
     * de schema.org-vorm van `nodes()`.
     *
     * @return list<string>
     */
    public static function cities(): array
    {
        $cities = [];

        foreach (self::entries() as $entry) {
            $city = trim((string) $entry->get('city'));

            if ($city !== '' && ! in_array($city, $cities, true)) {
                $cities[] = $city;
            }
        }

        return $cities;
    }

    /**
     * Bewust de defaultsite: een vestiging is er maar één, ongeacht de taal
     * waarin de bezoeker de site leest.
     *
     * @return \Illuminate\Support\Collection<int, EntryContract>
     */
    private static function entries()
    {
        return Entry::query()
            ->where('collection', 'locations')
            ->where('site', Site::default()->handle())
            ->orderBy('order')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function node(EntryContract $entry): ?array
    {
        $name = trim((string) $entry->get('name'));

        if ($name === '') {
            return null;
        }

        $street = trim((string) $entry->get('street'));
        $number = trim((string) $entry->get('number'));

        return [
            '@type' => 'LocalBusiness',
            '@id' => SiteUrl::absolute('/').'#'.$entry->slug(),
            'name' => $name,
            'parentOrganization' => ['@id' => OrganizationSchema::id()],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => trim($street.' '.$number),
                'postalCode' => trim((string) $entry->get('postal_code')),
                'addressLocality' => trim((string) $entry->get('city')),
                'addressCountry' => 'BE',
            ],
            'telephone' => trim((string) $entry->get('phone')),
            'geo' => self::geo($entry),
            'openingHoursSpecification' => OpeningHours::specifications(
                (array) ($entry->get('opening_hours') ?? [])
            ),
        ];
    }

    /**
     * Zonder geldige coördinaten geen geo-blok: liever niets dan null.
     *
     * @return array<string, mixed>
     */
    private static function geo(EntryContract $entry): array
    {
        $latitude = $entry->get('latitude');
        $longitude = $entry->get('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return [];
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }
}
