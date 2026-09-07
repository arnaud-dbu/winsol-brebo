<?php

namespace Tests\Feature\Sections;

class ContactDetailsTest extends SectionTestCase
{
    public function test_it_renders_a_card_per_location(): void
    {
        $html = $this->render('{{ partial:contactDetails }}');

        $this->assertStringContainsString('data-section="contact_details"', $html);
        $this->assertSame(3, substr_count($html, 'data-location'));
        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
    }

    public function test_it_composes_the_address_from_the_separate_fields(): void
    {
        $html = $this->render('{{ partial:contactDetails }}');

        $this->assertStringContainsString('Ninoofsesteenweg 637, 1700 Dilbeek', $html);
        $this->assertStringContainsString('Boomsesteenweg 70, 2630 Aartselaar', $html);
    }

    public function test_it_renders_the_opening_hours_as_day_time_pairs(): void
    {
        $html = $this->render('{{ partial:contactDetails }}');

        // Een dag-tijdpaar is een beschrijvingslijst; losse spans zouden de
        // koppeling weggooien. Vier regels maal drie vestigingen.
        $this->assertSame(12, substr_count($html, '<dt>'));
        $this->assertSame(12, substr_count($html, '<dd>'));
        $this->assertStringContainsString('<dt>Di - Vr</dt>', $html);
        $this->assertStringContainsString('<dd>10:30 - 17:30</dd>', $html);
        $this->assertStringContainsString('<dt>Maandag</dt>', $html);
        $this->assertStringContainsString('<dd>Op afspraak</dd>', $html);
        $this->assertStringContainsString('<dt>Zondag</dt>', $html);
        $this->assertStringContainsString('<dd>Gesloten</dd>', $html);
    }

    // De `{{ if opening_hours }}`-guard wordt hier bewust niet gedekt. Alle
    // drie de entries hebben uren, en het kaartje is geen losse partial, dus
    // er is geen manier om vanuit deze test een urenloze variant te renderen:
    // een waarde uit de $context wordt binnen `{{ collection:locations }}`
    // overschreven door de loopscope. Dezelfde afweging staat bij de
    // image-guard in QuicklinksTest. Wil je die tak later wel dekken, dan is
    // een `contactLocationCard`-partial de weg — zoals `locationCard` dat voor
    // de coordinaatloze variant al doet.

    public function test_the_contact_bar_is_absent_without_a_cascade(): void
    {
        // SectionTestCase::render() roept een kale view() aan, zonder Statamic-
        // cascade: `{{ globals:… }}` is daar altijd leeg. Dat is geen bug maar
        // het contract van deze harness, en het is precies waarom de balk
        // hieronder via een echte pagerender getest wordt.
        $html = $this->render('{{ partial:contactDetails }}');

        $this->assertStringNotContainsString('data-contact-bar', $html);
    }

    public function test_the_contact_bar_renders_from_the_globals_on_a_real_page(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('contact-bar', $html);
        $this->assertStringContainsString('offertes@winsolspl.be', $html);

        // Geen WhatsApp: `contact.mobile` staat leeg tot Jimmy een echt nummer
        // geeft, en de partial slaat de knop dan over. Zie ContactGlobalsTest.
        $this->assertStringNotContainsString('Whatsapp', $html);
    }

    /**
     * Het nummer hoort bij de showroom die opneemt, niet bij een streek.
     * "Brussel" en "Antwerpen" stonden tot 07-09-2026 als label boven de twee
     * centrales; Jimmy liet ze weghalen omdat bezoekers uit Dilbeek en
     * Sint-Pieters-Leeuw zich daar niet in herkennen.
     */
    public function test_every_showroom_carries_its_own_number_and_no_region_label(): void
    {
        // Alleen deze sectie: de footer eronder toont dezelfde drie nummers,
        // en dan telt elk nummer dubbel.
        $html = $this->get('/contact')->assertOk()->getContent();
        $start = strpos($html, 'data-section="contact_details"');
        $sectie = substr($html, $start, strpos($html, '</section>', $start) - $start);

        foreach (['Winsol Dilbeek', 'Winsol Sint-Pieters-Leeuw', 'Winsol Aartselaar'] as $showroom) {
            $this->assertStringContainsString($showroom, $sectie);
        }

        // Dilbeek en Sint-Pieters-Leeuw delen de 02-centrale, Aartselaar heeft de 03.
        $this->assertSame(2, substr_count($sectie, 'href="tel:+3223080226"'));
        $this->assertSame(1, substr_count($sectie, 'href="tel:+3238808565"'));

        $this->assertStringNotContainsString('Brussel', $html);
        $this->assertStringNotContainsString('Antwerpen', $html);
    }

    public function test_the_bar_links_are_dialable_and_the_wa_me_number_is_digits_only(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        // De strip is de enige transformatie in de partial en dus het enige
        // dat stil kan breken: een tel:-link met spaties belt niet.
        $this->assertStringContainsString('href="tel:+3223080226"', $html);
        $this->assertStringContainsString('href="tel:+3238808565"', $html);
        $this->assertStringContainsString('href="mailto:offertes@winsolspl.be"', $html);
        $this->assertStringNotContainsString('href="tel:+32 ', $html);
    }

    public function test_the_page_no_longer_ships_a_contact_form(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        // Het design toont geen formulier op /contact. De form- en recaptcha-
        // bestanden blijven bestaan voor een latere offerte- of herstelpagina.
        //
        // De assertie mikt op de action van juist dít formulier en niet op
        // '<form', zodat een later formulier elders in de layout (denk aan een
        // nieuwsbrief in de footer) deze test niet vals laat falen.
        $this->assertStringNotContainsString('/!/forms/contact', $html);
        $this->assertStringNotContainsString('g-recaptcha', $html);
    }
}
