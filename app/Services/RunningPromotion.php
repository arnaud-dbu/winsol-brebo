<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

/**
 * Zoekt het nieuwsartikel dat op dit moment als actie loopt.
 *
 * Winsol voert meerdere keren per jaar een actie, en elke actie krijgt een
 * artikel — dat is meteen de landingspagina voor Google Ads. De balk boven de
 * navigatie leidt ernaartoe. Er is bewust geen aparte `acties`-collectie: dan
 * zou dezelfde actie op twee plaatsen onderhouden moeten worden, en zou het
 * artikel kunnen bestaan zonder balk of de balk zonder artikel. Nu is het
 * artikel de actie, en zetten drie velden op datzelfde artikel de balk aan.
 */
class RunningPromotion
{
    private bool $gezocht = false;

    private ?EntryContract $actie = null;

    /**
     * Het artikel dat vandaag als actie loopt, of null.
     *
     * Eén keer per request, want elke pagina vraagt het op via de view
     * composer en het antwoord kan binnen een request niet veranderen.
     */
    public function find(): ?EntryContract
    {
        if ($this->gezocht) {
            return $this->actie;
        }

        $this->gezocht = true;
        $vandaag = Carbon::today();

        // `whereStatus` en niet `where('published', true)`: die eerste weegt
        // ook het datumgedrag van de collectie mee. Vandaag staat dat op
        // `future: public` en is er geen verschil, maar zou een artikel met
        // een datum in de toekomst ooit verborgen worden, dan zou de balk
        // zonder deze filter naar een 404 linken.
        $this->actie = Entry::query()
            ->where('collection', 'articles')
            ->where('site', Site::current()->handle())
            ->whereStatus('published')
            ->where('promo', true)
            ->get()
            ->filter(fn (EntryContract $artikel) => $this->loopt($artikel, $vandaag))
            // Loopt er per ongeluk meer dan één, dan wint de actie die het
            // eerst afloopt: die is het dringendst en verdwijnt vanzelf,
            // waarna de volgende de balk overneemt. Een actie zonder einddatum
            // sluit de rij, anders zou die de rest permanent verdringen.
            ->sortBy(fn (EntryContract $artikel) => $this->datum($artikel, 'promo_end')?->timestamp ?? PHP_INT_MAX)
            ->first();

        return $this->actie;
    }

    private function loopt(EntryContract $artikel, CarbonInterface $vandaag): bool
    {
        $start = $this->datum($artikel, 'promo_start');
        $einde = $this->datum($artikel, 'promo_end');

        // Tot en met de einddatum: een actie die op 18 oktober afloopt, hoort
        // die hele zondag nog zichtbaar te zijn.
        return ! ($start && $vandaag->lt($start))
            && ! ($einde && $vandaag->gt($einde));
    }

    private function datum(EntryContract $artikel, string $veld): ?CarbonInterface
    {
        // `value()` en niet `get()`: het datumveld levert daar een Carbon, en
        // een handmatig aangemaakte entry in een test een gewone string.
        $waarde = $artikel->value($veld);

        if ($waarde instanceof CarbonInterface) {
            return $waarde->copy()->startOfDay();
        }

        return $waarde ? Carbon::parse($waarde)->startOfDay() : null;
    }
}
