<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class OffertePageTest extends TestCase
{
    public function test_the_entry_exists_on_its_own_blueprint_and_template(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();

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
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();

        $this->assertSame('quicklinks/offerte-2.png', $entry->get('image'));
    }

    public function test_the_page_renders_the_heading_the_form_and_the_image(): void
    {
        $html = $this->get('/offerte')->assertOk()->getContent();

        $this->assertStringContainsString('Vraag een offerte aan', $html);
        $this->assertStringContainsString('offerte-form', $html);
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
}
