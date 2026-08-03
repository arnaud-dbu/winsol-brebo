<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\Concerns\AssertsSiteVoice;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use AssertsSiteVoice;

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
        $response->assertSee('Bel of mail ons gerust', false);
    }

    /**
     * De CTA wees eerst naar nieuws, net als die op /offerte en met bijna
     * dezelfde tekst. De brief vraagt een unieke CTA per pagina, dus deze wijst
     * naar het aanbod: een andere bestemming én een andere invalshoek.
     */
    public function test_the_cta_carries_its_copy_and_points_at_the_catalogue(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('Weet je nog niet precies wat je zoekt?', false);
        $response->assertSee('Bekijk het aanbod', false);
        $response->assertSee('href="/aanbod"', false);
    }

    /**
     * Beide quicklinks wezen naar de contactpagina zelf, waar ze op staan.
     */
    public function test_the_quicklinks_point_away_from_this_page(): void
    {
        $contact = Entry::findByUri('/contact')->entry();

        $targets = collect($contact->get('quicklinks'))
            ->map(fn ($quicklink) => $quicklink['link'][0]['entry'][0])
            ->all();

        $this->assertNotContains($contact->id(), $targets, 'Een quicklink wijst naar de contactpagina zelf.');

        $offerte = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();
        $service = Entry::query()->where('collection', 'pages')->where('slug', 'service')->first();

        $this->assertSame([$offerte->id(), $service->id()], $targets);
    }

    public function test_the_copy_speaks_in_the_je_form_without_em_dashes(): void
    {
        $contact = Entry::findByUri('/contact')->entry();
        $cta = $contact->get('page_builder')[0];

        $this->assertSpeaksSiteVoice($contact->get('text'), 'intro');
        $this->assertSpeaksSiteVoice($cta['title'].' '.$cta['text'], 'cta');

        foreach ($contact->get('quicklinks') as $quicklink) {
            $this->assertSpeaksSiteVoice($quicklink['title'].' '.$quicklink['text'], "quicklink {$quicklink['id']}");
        }
    }

    public function test_the_cta_carries_a_real_photo(): void
    {
        $image = Entry::findByUri('/contact')->entry()->get('page_builder')[0]['image'];

        $this->assertStringNotContainsString('dummy-images/', $image);
        $this->assertStringNotContainsString('placeholder/', $image);
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
