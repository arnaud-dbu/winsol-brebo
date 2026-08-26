<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class NieuwsPageTest extends TestCase
{
    public function test_the_page_lives_at_nieuws_and_keeps_the_old_entry_id(): void
    {
        // De id wordt overgenomen van realisaties.md, want hij staat in de
        // navigatieboom, in de paginaboom én als `mount` op de collectie.
        // Een nieuwe id zou alle drie moeten bijwerken.
        $page = Entry::find('c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03');

        $this->assertNotNull($page);
        $this->assertSame('nieuws', $page->slug());
        $this->assertSame('Nieuws', $page->get('title'));
        $this->assertSame('articles/index', $page->get('template'));
    }

    /**
     * De boilerplate-realisatiespagina ging er destijds uit; op 26-08 kwam
     * er een echte voor terug (werkoverleg Jimmy). Zie RealisatiesPageTest
     * voor de inhoud; hier alleen dat hij bestaat en rendert.
     */
    public function test_realisaties_is_back_as_a_real_page(): void
    {
        $this->assertNotNull(
            Entry::query()->where('collection', 'pages')->where('slug', 'realisaties')->first()
        );

        $this->get('/realisaties')->assertOk();
    }

    public function test_the_collection_is_mounted_on_this_page(): void
    {
        $yaml = file_get_contents(base_path('content/collections/articles.yaml'));

        $this->assertStringContainsString('mount: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03', $yaml);
    }

    public function test_the_main_navigation_points_at_nieuws(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('href="/nieuws"', $html);
    }

    public function test_the_dead_boilerplate_mount_is_out_of_the_page_tree(): void
    {
        $tree = file_get_contents(base_path('content/trees/collections/nl/pages.yaml'));

        $this->assertStringNotContainsString('8cf703da-5dde-4543-89aa-8f2d5c3011d9', $tree);
    }
}
