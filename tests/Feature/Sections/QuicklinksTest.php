<?php

namespace Tests\Feature\Sections;

class QuicklinksTest extends SectionTestCase
{
    public function test_it_renders_a_card_per_quicklink_under_the_hardcoded_title(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('data-section="quicklinks"', $html);
        $this->assertStringContainsString('Zet de volgende stap', $html);
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }

    public function test_it_renders_the_copy_from_the_collection(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('Vraag offerte aan', $html);
        $this->assertStringContainsString('Vraag brochure aan', $html);
        $this->assertStringContainsString('Bezoek een showroom', $html);
        $this->assertStringContainsString('Ontvang de volledige brochure met opties en kleuren in uw bus of mailbox.', $html);
        $this->assertStringContainsString('Plan een bezoek', $html);
    }

    public function test_the_first_button_is_filled_and_the_other_two_are_outlined(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        // De link_style-mapping is de enige vertakking in de partial, dus dit
        // is wat vastgepind hoort te worden.
        $this->assertSame(1, substr_count($html, 'btn--accent'));
        $this->assertSame(2, substr_count($html, 'btn--outline'));

        $this->assertLessThan(
            strpos($html, 'btn--outline'),
            strpos($html, 'btn--accent'),
            'De gevulde knop hoort op de eerste kaart te staan'
        );
    }

    public function test_it_lists_the_quicklinks_in_their_designed_order(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $offerte = strpos($html, 'Vraag offerte aan');
        $brochure = strpos($html, 'Vraag brochure aan');
        $showroom = strpos($html, 'Bezoek een showroom');

        $this->assertLessThan($brochure, $offerte, 'Offerte hoort eerst te staan');
        $this->assertLessThan($showroom, $brochure, 'Brochure hoort tweede te staan');
    }

    public function test_it_renders_every_card_while_the_photos_are_still_unlinked(): void
    {
        // De entries hebben nog geen beeld (het assets-pad is nog niet bekend),
        // dus dit pint vast dat alle drie de kaarten renderen zolang dat zo is.
        //
        // De `{{ if image }}` guard zelf wordt hier niet gedekt: er is geen
        // beeld-fixture, dus er is geen manier om vanuit deze test aan te
        // tonen dat de guard het verschil maakt (een assertNotContainsString
        // op '<img' zou evengoed slagen zonder de guard, want de Img-tag
        // geeft toch al niets terug als `image` null is).
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }
}
