<?php

namespace Tests\Feature\Sections;

class QuicklinksTest extends SectionTestCase
{
    /**
     * `quicklinks` leest zijn eigen collectie, maar de brochurekaart daarin
     * rendert pas iets zodra de omringende pagina een `brochure` in scope
     * zet (Task 5). Deze tests simuleren die pagina, zodat ze blijven pinnen
     * hoe de collectie er in de praktijk — mét brochure — uitziet.
     */
    private function withBrochure(): array
    {
        return ['brochure' => ['url' => '/assets/brochures/pergola-so.pdf']];
    }

    public function test_the_brochure_only_reaches_the_card_when_passed_explicitly(): void
    {
        // `withBrochure()` zet `brochure` rechtstreeks in de rootcontext, wat
        // ook zonder expliciete doorgifte al doorsijpelt en dus niets pint.
        // Deze test bindt de buitenvariabele onder een andere naam (`pdf`) en
        // geeft hem alleen door via `:brochure="pdf"` op de aanroep — zonder
        // die doorgifte bestaat er nergens een variabele die `brochure` heet.
        $html = $this->render('{{ partial:quicklinks :brochure="pdf" }}', [
            'pdf' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('/assets/brochures/pergola-so.pdf', $html);
    }

    public function test_it_renders_a_card_per_quicklink_under_the_hardcoded_title(): void
    {
        $html = $this->render('{{ partial:quicklinks }}', $this->withBrochure());

        $this->assertStringContainsString('data-section="quicklinks"', $html);
        $this->assertStringContainsString('Zet de volgende stap', $html);
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }

    public function test_it_renders_the_copy_from_the_collection(): void
    {
        $html = $this->render('{{ partial:quicklinks }}', $this->withBrochure());

        $this->assertStringContainsString('Vraag offerte aan', $html);
        $this->assertStringContainsString('Vraag brochure aan', $html);
        $this->assertStringContainsString('Bezoek een showroom', $html);
        $this->assertStringContainsString('Ontvang de volledige brochure met opties en kleuren in uw bus of mailbox.', $html);
        $this->assertStringContainsString('Plan een bezoek', $html);
    }

    public function test_the_first_button_is_filled_and_the_other_two_are_outlined(): void
    {
        $html = $this->render('{{ partial:quicklinks }}', $this->withBrochure());

        // De link_style-mapping is de enige vertakking in de partial, dus dit
        // is wat vastgepind hoort te worden.
        $this->assertSame(1, substr_count($html, 'btn--primary'));
        $this->assertSame(2, substr_count($html, 'btn--outline'));

        $this->assertLessThan(
            strpos($html, 'btn--outline'),
            strpos($html, 'btn--primary'),
            'De gevulde knop hoort op de eerste kaart te staan'
        );
    }

    public function test_it_lists_the_quicklinks_in_their_designed_order(): void
    {
        $html = $this->render('{{ partial:quicklinks }}', $this->withBrochure());

        $offerte = strpos($html, 'Vraag offerte aan');
        $brochure = strpos($html, 'Vraag brochure aan');
        $showroom = strpos($html, 'Bezoek een showroom');

        $this->assertLessThan($brochure, $offerte, 'Offerte hoort eerst te staan');
        $this->assertLessThan($showroom, $brochure, 'Brochure hoort tweede te staan');
    }

    public function test_every_card_now_carries_its_photo(): void
    {
        // De foto's stonden al in de assets-container onder quicklinks/; ze
        // waren alleen nog niet aan de entries gekoppeld. Dat sluit open punt 2
        // uit docs/superpowers/specs/2026-07-26-locations-quicklinks-design.md.
        $html = $this->render('{{ partial:quicklinks }}', $this->withBrochure());

        $this->assertSame(3, substr_count($html, 'quicklink-media'));
        $this->assertSame(3, substr_count($html, '<img'));
    }

    public function test_the_card_markup_comes_from_the_shared_partial(): void
    {
        // Dezelfde kaart wordt door de collectie-component en door
        // pageQuicklinks gerenderd. Dit pint vast dat er één bron is: de
        // losse kaart levert dezelfde klassen op als de component eromheen.
        $html = $this->render('{{ partial:quicklinkCard }}', [
            'title' => 'Losse kaart',
            'text' => 'Met een tekst.',
            'link_style' => 'outline',
            'link' => [[
                'type' => 'url',
                'url' => 'example.com',
                'label' => 'Naar example',
            ]],
        ]);

        $this->assertStringContainsString('quicklink-card', $html);
        $this->assertStringContainsString('Losse kaart', $html);
        $this->assertStringContainsString('btn--outline', $html);

        // Zonder `image` mag de media-box niet meekomen, anders reserveert de
        // overhang ruimte voor een foto die er niet is.
        $this->assertStringNotContainsString('quicklink-media', $html);
    }

    public function test_the_grid_reserves_room_for_the_overhanging_photo(): void
    {
        // De foto hangt over de bovenrand van het lichte vlak. Zonder die
        // klasse op het grid valt hij over de kaart erboven en tegen de <h2>.
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('quicklink-grid', $html);
    }
}
