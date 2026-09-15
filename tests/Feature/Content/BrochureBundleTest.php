<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Site;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Jimmy vroeg op 10-09-2026 om bij elke pergolabrochure de nieuwe
 * zonneschermen mee te sturen. Dat gebeurt niet met een extra vinkje in het
 * formulier maar met `extra_files` op het item in de bibliotheek: de bezoeker
 * kan zo niets verkeerd doen, hij krijgt het er gewoon bij.
 *
 * Ontdubbelen hoort daarbij. Twee regels in de lijst mogen naar dezelfde pdf
 * wijzen — Rolluiken en Verticale zonwering doen dat sinds diezelfde
 * correctie — en een extra kan samenvallen met iets dat de bezoeker zelf al
 * aanvinkte.
 */
class BrochureBundleTest extends TestCase
{
    private const PERGOLA_SO = 'brochures/winsol-brochure_so-classic-climate_2025_nl.pdf';

    private const SO_CRYSTAL = 'brochures/winsol_brochure_so-crystal_nl.pdf';

    private const ZONNESCHERMEN = 'brochures/winsol_brochure_luifels_nl.pdf';

    private const ZONWERING = 'brochures/winsol_brochure_verticale-zonwering_nl.pdf';

    /**
     * @param  list<string>  $keuze
     * @return list<string>
     */
    private function bestanden(array $keuze, string $site = 'nl'): array
    {
        Site::setCurrent($site);

        $veld = new Field('brochures', ['type' => 'brochure_checkboxes']);

        return array_column($veld->fieldtype()->augment($keuze), 'value');
    }

    public function test_a_pergola_brochure_brings_the_awnings_brochure_along(): void
    {
        $this->assertSame(
            [self::PERGOLA_SO, self::ZONNESCHERMEN],
            $this->bestanden([self::PERGOLA_SO]),
        );
    }

    public function test_every_pergola_in_the_library_carries_that_extra(): void
    {
        foreach ([self::PERGOLA_SO, self::SO_CRYSTAL] as $pergola) {
            $this->assertContains(
                self::ZONNESCHERMEN,
                $this->bestanden([$pergola]),
                "De zonneschermen ontbreken bij {$pergola}.",
            );
        }
    }

    public function test_the_awnings_brochure_is_not_sent_twice(): void
    {
        $bestanden = $this->bestanden([self::PERGOLA_SO, self::ZONNESCHERMEN]);

        $this->assertSame([self::PERGOLA_SO, self::ZONNESCHERMEN], $bestanden);
        $this->assertSame(array_unique($bestanden), $bestanden);
    }

    public function test_two_pergolas_still_yield_one_awnings_brochure(): void
    {
        $bestanden = $this->bestanden([self::PERGOLA_SO, self::SO_CRYSTAL]);

        $this->assertSame(1, count(array_keys($bestanden, self::ZONNESCHERMEN)));
    }

    /**
     * Sinds Jimmy's correctie wijzen Rolluiken en Verticale zonwering naar
     * dezelfde pdf. Wie beide aanvinkt hoort er één te krijgen.
     */
    public function test_two_labels_on_the_same_pdf_yield_one_download(): void
    {
        $this->assertSame(
            [self::ZONWERING],
            $this->bestanden([self::ZONWERING, self::ZONWERING]),
        );
    }

    public function test_the_bundle_follows_the_language_of_the_visitor(): void
    {
        $bestanden = $this->bestanden(
            ['brochures/winsol-brochure_so-classic-climate_2025_fr.pdf'],
            'fr',
        );

        $this->assertSame([
            'brochures/winsol-brochure_so-classic-climate_2025_fr.pdf',
            'brochures/winsol_brochure_luifels_fr.pdf',
        ], $bestanden);
    }

    /**
     * De mail bouwt zijn downloadlink op `url`; blijft die leeg, dan staat er
     * een knop zonder bestemming in de inbox.
     */
    public function test_every_delivered_brochure_has_a_download_link(): void
    {
        Site::setCurrent('nl');

        $veld = new Field('brochures', ['type' => 'brochure_checkboxes']);

        foreach ($veld->fieldtype()->augment([self::PERGOLA_SO]) as $rij) {
            $this->assertNotEmpty($rij['url'], "Geen downloadlink voor {$rij['value']}.");
        }
    }
}
