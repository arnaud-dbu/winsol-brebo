<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    public function test_the_entry_uses_the_contact_blueprint(): void
    {
        // Zonder deze pin kan de entry stilzwijgend terugvallen op
        // `blueprint: page`, waarna het pagina-eigen `quicklinks`-veld
        // verdwijnt. Die exacte fout heeft dit project al eens gekost (zie
        // de eindreview-observaties in
        // 2026-07-26-locations-quicklinks-followups.md).
        //
        // `Entry::findByUri()` geeft voor entries in een gestructureerde
        // collectie (zoals `pages`) een `Structures\Page` terug, niet de
        // entry zelf — diens `blueprint()` bestaat wel, maar retourneert
        // altijd null buiten een Nav-structuur. `->entry()` haalt de
        // onderliggende `Entries\Entry` op, waarvan `blueprint()` wél de
        // echte blueprint-handle geeft.
        $this->assertSame('contact', Entry::findByUri('/contact')->entry()->blueprint()->handle());
    }

    public function test_the_page_renders_every_block_from_the_design(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();

        foreach (['contact_details', 'page_quicklinks', 'cta'] as $section) {
            $response->assertSee('data-section="'.$section.'"', false);
        }
    }

    public function test_the_header_shows_the_title_and_the_intro_from_the_design(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('Contact', false);
        $response->assertSee('Bel of mail rechtstreeks het filiaal in uw buurt', false);
    }

    public function test_the_cta_carries_its_copy_and_points_at_the_projects_overview(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('Liever eerst even rondkijken?', false);
        $response->assertSee('Naar realisaties', false);
        $response->assertSee('href="/realisaties"', false);
    }

    public function test_the_blocks_appear_in_the_designed_order(): void
    {
        $html = $this->get('/contact')->getContent();

        $details = strpos($html, 'data-section="contact_details"');
        $quicklinks = strpos($html, 'data-section="page_quicklinks"');
        $cta = strpos($html, 'data-section="cta"');

        $this->assertLessThan($quicklinks, $details, 'Het contactpaneel hoort boven de quicklinks te staan');
        $this->assertLessThan($cta, $quicklinks, 'De quicklinks horen boven de CTA te staan');
    }
}
