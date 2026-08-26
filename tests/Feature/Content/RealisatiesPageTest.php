<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class RealisatiesPageTest extends TestCase
{
    public function test_the_page_lives_at_realisaties_with_the_filter_and_the_grid(): void
    {
        $response = $this->get('/realisaties');

        $response->assertOk();
        $response->assertSee('Realisaties');
        $response->assertSee('Toon alles');
        $response->assertSee('data-section="realisaties-overview"', false);
    }

    /**
     * Jimmy (werkoverleg 21/24-08): in één oogopslag het verschil zien
     * tussen producten, met filters bovenaan op productgroep. De pillen
     * komen uit de realisatie_ranges-tag: gepubliceerde ranges met minstens
     * één realisatie, dus een klik levert nooit een lege grid op.
     */
    public function test_the_filter_offers_exactly_the_groups_that_have_realisaties(): void
    {
        $html = $this->get('/realisaties')->getContent();

        foreach (['ramen-en-deuren', 'rolluiken', 'zonwering', 'terrasoverkapping', 'garagepoorten'] as $slug) {
            $this->assertStringContainsString('?groep='.$slug.'"', $html, "Filterpil {$slug} ontbreekt.");
        }

        $this->assertStringNotContainsString('?groep=velux', $html);
        $this->assertStringNotContainsString('?groep=airco', $html);
    }

    public function test_a_groep_parameter_prefilters_the_grid_server_side(): void
    {
        $html = $this->get('/realisaties?groep=rolluiken')->getContent();

        // Alle kaarten staan in de DOM (het filter werkt client-side zonder
        // request), maar de niet-matchende staan bij de eerste paint hidden.
        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function test_every_realisatie_carries_an_image_and_a_published_range(): void
    {
        $realisaties = Entry::query()->where('collection', 'realisaties')->get();

        $this->assertGreaterThan(0, $realisaties->count(), 'De realisaties-collectie is leeg.');

        foreach ($realisaties as $realisatie) {
            $this->assertNotEmpty($realisatie->get('image'), "{$realisatie->slug()} heeft geen beeld.");

            $range = Entry::find($realisatie->get('range'));
            $this->assertNotNull($range, "{$realisatie->slug()} wijst naar een range die niet bestaat.");
            $this->assertTrue($range->published(), "{$realisatie->slug()} hangt aan een gedepubliceerde range.");
        }
    }

    public function test_the_page_sits_in_the_main_and_footer_navigation(): void
    {
        $home = $this->get('/')->getContent();

        $this->assertStringContainsString('href="/realisaties"', $home);
        $this->assertStringContainsString('Realisaties', $home);
    }
}
