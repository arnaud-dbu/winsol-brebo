<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class RangeShowPageTest extends TestCase
{
    /**
     * `locations` heeft zijn eigen dekking in LocationsTest (kaarten, adres,
     * volgorde). Wat daar niet uit blijkt is dat de rangepagina het blok ook
     * echt insluit — dat pint deze test vast, niet de inhoud van het blok.
     */
    public function test_it_renders_the_locations_block(): void
    {
        $html = $this->get('/aanbod/terrasoverkapping')->getContent();

        $this->assertStringContainsString('data-section="locations"', $html);
    }
}
