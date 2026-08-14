<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

class LocationsSchema
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function nodes(): array
    {
        return Entry::query()
            ->where('collection', 'locations')
            ->orderBy('order')
            ->get()
            ->map(fn (EntryContract $entry) => self::node($entry))
            ->filter()
            ->values()
            ->all();
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

        foreach (Entry::query()->where('collection', 'locations')->orderBy('order')->get() as $entry) {
            $city = trim((string) $entry->get('city'));

            if ($city !== '' && ! in_array($city, $cities, true)) {
                $cities[] = $city;
            }
        }

        return $cities;
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
