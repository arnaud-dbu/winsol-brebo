<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Tests\Concerns\AssertsSiteVoice;
use Tests\TestCase;

class OffertePageTest extends TestCase
{
    use AssertsSiteVoice;

    public function test_the_entry_exists_on_its_own_blueprint_and_template(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'offerte')->first();

        $this->assertNotNull($entry, 'De offerte-entry ontbreekt.');
        $this->assertSame('offerte', $entry->blueprint()->handle());
        $this->assertSame('offerte', $entry->get('template'));
    }

    /**
     * Het stilleven uit de briefing. Staat het pad hier niet vast, dan valt
     * het beeld stil weg zonder dat iets faalt.
     */
    public function test_the_still_life_image_is_set(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'offerte')->first();

        $this->assertSame('quicklinks/offerte-2.png', $entry->get('image'));
    }

    public function test_the_page_renders_the_heading_the_form_and_the_image(): void
    {
        $html = $this->get('/offerte')->assertOk()->getContent();

        $this->assertStringContainsString('Vraag een offerte aan', $html);
        // Het klasse-attribuut zelf, niet de losse tekst: elke veld-id begint
        // met `offerte-form-`, dus een kale substring-check slaagt ook als de
        // klasse van het formulier valt.
        $this->assertStringContainsString('class="form offerte-form"', $html);
        $this->assertStringContainsString('offerte-still', $html);
    }

    /**
     * De DOM-volgorde is de mobiele volgorde: kop, formulier, beeld. Op
     * desktop verzet het raster de kolommen. Draait dit om, dan duwt het
     * beeld het formulier onder de vouw op telefoon.
     */
    public function test_the_form_comes_before_the_image_in_the_markup(): void
    {
        $html = $this->get('/offerte')->getContent();

        $this->assertLessThan(
            strpos($html, 'offerte-still'),
            strpos($html, 'offerte-form'),
        );
    }

    /**
     * De H1 moet `.header-title` dragen en niet `text-display`: Tailwind's
     * utilities staan in `@layer utilities` en verliezen van de ongelaagde
     * `h1`-basisregel in base/typography.css.
     */
    public function test_the_heading_uses_the_unlayered_display_size(): void
    {
        $html = $this->get('/offerte')->getContent();

        $this->assertStringContainsString('<h1 class="header-title">', $html);
    }

    /**
     * De CTA wees naar realisaties, dat on hold staat en verzonnen cases toont.
     * "Nog niet klaar voor een offerte" leidt nu naar de showrooms: eerst eens
     * langskomen. De CTA op /contact wijst naar het aanbod, dus de drie pagina's
     * sturen elk ergens anders heen.
     */
    public function test_the_page_builder_holds_exactly_one_cta_pointing_at_the_showrooms(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'offerte')->first();
        $contact = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'contact')->first();

        $builder = $entry->get('page_builder');

        $this->assertCount(1, $builder);
        $this->assertSame('cta', $builder[0]['type']);
        $this->assertSame('Nog niet klaar voor een offerte?', $builder[0]['title']);
        $this->assertSame('Bezoek een showroom', $builder[0]['link'][0]['label']);
        // Het `entry`-veld in resources/fieldsets/links.yaml heeft max_items: 1
        // en slaat dus een losse id op. De lijstvorm hierboven was handmatig
        // gezaaid; a257ed5 was een CP-save die hem naar de veldvorm trok.
        $this->assertSame($contact->id(), $builder[0]['link'][0]['entry']);
    }

    /**
     * De gele kaart van het cta-component staat links over het beeld. Een
     * dummyfoto viel daardoor niet op: de kaart dekt precies het deel af waar
     * je naar kijkt.
     */
    public function test_the_cta_carries_a_real_photo(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'offerte')->first();

        $image = $entry->get('page_builder')[0]['image'];

        $this->assertStringNotContainsString('dummy-images/', $image);
        $this->assertStringNotContainsString('placeholder/', $image);
    }

    public function test_the_copy_speaks_in_the_je_form_without_em_dashes(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'offerte')->first();
        $cta = $entry->get('page_builder')[0];

        $this->assertSpeaksSiteVoice($entry->get('text'), 'intro');
        $this->assertSpeaksSiteVoice($cta['title'].' '.$cta['text'], 'cta');
    }

    /**
     * Het formulier is de pagina, dus zijn labels en placeholders zijn hier
     * evengoed lopende tekst.
     */
    public function test_the_form_labels_speak_in_the_je_form(): void
    {
        $fields = Blueprint::find('forms.offerte')->fields()->all();

        foreach ($fields as $handle => $field) {
            $this->assertSpeaksSiteVoice($field->display(), "label {$handle}");
            $this->assertSpeaksSiteVoice($field->get('placeholder') ?? '', "placeholder {$handle}");
        }
    }

    public function test_the_cta_renders_below_the_form(): void
    {
        $html = $this->get('/offerte')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'offerte-form'),
        );
    }

    /**
     * Beide wezen naar /contact omdat /offerte nog niet bestond, terwijl hun
     * label "Vraag offerte aan" is.
     */
    public function test_the_existing_offerte_links_point_at_this_page(): void
    {
        $offerte = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'offerte')->first();

        $quicklink = Entry::query()->where('collection', 'quicklinks')->where('site', 'nl')->where('slug', 'vraag-offerte-aan')->first();
        $this->assertSame($offerte->id(), $quicklink->get('link')[0]['entry'][0]);

        $nieuws = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'nieuws')->first();
        $this->assertSame($offerte->id(), $nieuws->get('page_builder')[0]['link'][0]['entry'][0]);
    }
}
