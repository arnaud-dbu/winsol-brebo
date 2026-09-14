<?php

namespace App\Fieldtypes\Concerns;

use Illuminate\Support\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

/**
 * De ranges achter `range_select` en `range_checkboxes`.
 *
 * De opties komen uit de taal van de bezoeker, de allowlist uit alle talen.
 * Dat onderscheid is de hele reden dat deze trait bestaat.
 *
 * Een formulier post naar `/!/forms/<handle>`, een route zonder taalprefix,
 * dus tijdens het verwerken staat `Site::current()` op de standaardsite. De
 * keuzelijst is dan wél in het Frans of Engels opgebouwd. Sinds de ranges per
 * taal een eigen slug dragen (commit bd44d80, 09-09-2026) botsten die twee:
 * `protection-solaire` werd afgetoetst tegen een lijst met `zonwering` en
 * afgekeurd. Zeven van de acht productgroepen faalden daardoor op /fr en /en;
 * alleen `somfy-smart-home` kwam door, omdat die slug in elke taal gelijk is.
 *
 * `App\Fieldtypes\BrochureCheckboxes` liep hier eerder tegenaan en lost het op
 * dezelfde manier op.
 */
trait RangeOptions
{
    /**
     * De ranges in de taal van de bezoeker, voor de keuzelijst zelf.
     *
     * Zonder sitefilter komen alle taalversies terug en wint bij het
     * ontdubbelen op slug de laatste site — Engelse labels op elke site.
     * `value('order')` in plaats van orderBy: localisaties erven order van hun
     * origin en dragen het veld dus niet zelf.
     *
     * @return Collection<int, \Statamic\Contracts\Entries\Entry>
     */
    private function ranges()
    {
        return $this->gepubliceerdeRanges(Site::current()->handle())
            ->sortBy(fn ($entry) => $entry->value('order'))
            ->values();
    }

    /**
     * Elke slug die een bezoeker in eender welke taal voorgeschoteld kan
     * krijgen. Uitsluitend voor de validatie; voor de keuzelijst zou dit de
     * drie talen door elkaar tonen.
     *
     * @return Collection<int, string>
     */
    private function slugsInAlleTalen()
    {
        return collect(Site::all())
            ->flatMap(fn ($site) => $this->gepubliceerdeRanges($site->handle())->map->slug())
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, \Statamic\Contracts\Entries\Entry>
     */
    private function gepubliceerdeRanges(string $site)
    {
        return Entry::query()
            ->where('collection', 'ranges')
            ->where('site', $site)
            ->whereStatus('published')
            ->get();
    }
}
