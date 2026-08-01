<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ServicePageTest extends TestCase
{
    public function test_the_entry_uses_the_services_overview_blueprint_and_template(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'service')->first();

        $this->assertNotNull($entry, 'De service-entry ontbreekt.');
        $this->assertSame('services_overview', $entry->blueprint()->handle());
        $this->assertSame('service', $entry->get('template'));
    }

    public function test_the_reparation_group_carries_an_image_field(): void
    {
        $blueprint = Blueprint::find('collections.pages.services_overview');

        $this->assertTrue(
            $blueprint->hasField('reparation'),
            'De reparation-group ontbreekt in het blueprint.'
        );

        // `field('reparation')->get('fields')` geeft de ruwe yaml terug en
        // lost `import: image` niet op — dat gebeurt pas wanneer de
        // Group-fieldtype zijn eigen Fields-collectie bouwt via fieldtype()
        // ->fields(), precies zoals de CP en augmentatie het veld
        // consumeren. Deze accessor toetst dus het echte gedrag.
        $this->assertTrue(
            $blueprint->field('reparation')->fieldtype()->fields()->has('image'),
            'Het image-veld ontbreekt op de reparation-group.'
        );
    }

    public function test_the_page_renders_the_four_sections_and_the_form(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $response = $this->get('/service');

        $response->assertOk();
        $response->assertSee('id="advies"', false);
        $response->assertSee('id="installatie"', false);
        $response->assertSee('id="onderhoud"', false);
        $response->assertSee('id="garantie"', false);
        $response->assertSee('id="herstelling"', false);
    }

    public function test_the_nav_anchors_all_resolve_to_a_section_on_the_page(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        // Dit is de test die het hele plan aan elkaar knoopt: sectionNav bouwt
        // de hrefs uit de overlines, textImage bouwt de ids uit dezelfde bron.
        // Loopt dat uiteen, dan springt de balk nergens naartoe.
        $html = $this->get('/service')->getContent();

        preg_match_all('/href="#([^"]+)"/', $html, $matches);
        $anchors = array_unique($matches[1]);

        $this->assertNotEmpty($anchors, 'Er staan geen ankerlinks op de pagina.');

        foreach ($anchors as $anchor) {
            $this->assertStringContainsString(
                'id="'.$anchor.'"',
                $html,
                "De ankerlink #{$anchor} wijst naar een sectie die niet bestaat."
            );
        }
    }

    public function test_the_four_service_blocks_use_the_imported_photos(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'service')->first();

        $images = collect($entry->get('services'))->pluck('image');

        $this->assertCount(4, $images);

        foreach ($images as $image) {
            $this->assertStringStartsWith(
                'service/',
                $image,
                "Serviceblok wijst naar {$image} in plaats van naar de servicemap."
            );
        }

        $this->assertSame(
            $images->unique()->count(),
            $images->count(),
            'Twee serviceblokken delen dezelfde foto.'
        );
    }

    /**
     * De overline ligt vast: hij levert zowel het label in de ankerrij als het
     * anker zelf. De kop eronder mag dat woord daarom niet nog eens herhalen,
     * anders leest het blok als "Onderhoud / Onderhoud en nazicht".
     */
    public function test_no_service_title_repeats_its_overline(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'service')->first();

        foreach ($entry->get('services') as $service) {
            $this->assertStringNotContainsStringIgnoringCase(
                $service['overline'],
                $service['title'],
                "De kop \"{$service['title']}\" herhaalt zijn overline."
            );
        }
    }

    /**
     * De twee taalregels uit de brief, op de pagina waar de eigenaar ze het
     * eerst gevraagd heeft: de site tutoyeert, en gedachtestreepjes zijn eruit
     * omdat ze bij het overnemen van tekst meeverhuizen.
     */
    public function test_the_copy_speaks_in_the_je_form_without_em_dashes(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'service')->first();

        foreach ($this->copyBlocks($entry) as $label => $text) {
            $this->assertDoesNotMatchRegularExpression(
                '/[—–]/u',
                $text,
                "{$label} bevat een gedachtestreepje."
            );

            $this->assertDoesNotMatchRegularExpression(
                '/\b[Uu]w?\b/u',
                $text,
                "{$label} spreekt in de u-vorm."
            );
        }
    }

    /**
     * Alle lopende tekst van de pagina, per blok gelabeld zodat een rode test
     * meteen zegt wélk blok het is.
     *
     * @param  \Statamic\Contracts\Entries\Entry  $entry
     * @return array<string, string>
     */
    private function copyBlocks($entry): array
    {
        $blocks = ['intro' => $entry->get('text')];

        foreach ($entry->get('services') as $service) {
            $blocks[$service['overline']] = $service['title'].' '.$this->flattenBard($service['text']);
        }

        $reparation = $entry->get('reparation');
        $blocks['herstelling'] = $reparation['title'].' '.$reparation['text'];

        return $blocks;
    }

    /**
     * Bard levert een boom van nodes; alleen de `text`-bladeren dragen inhoud.
     *
     * @param  array<int, mixed>  $nodes
     */
    private function flattenBard(array $nodes): string
    {
        $text = '';

        array_walk_recursive($nodes, function ($value, $key) use (&$text) {
            if ($key === 'text') {
                $text .= ' '.$value;
            }
        });

        return trim($text);
    }

    public function test_the_sections_alternate_starting_with_the_image(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->get('/service')->getContent();

        // Gestapeld staat de foto overal bovenaan: alle vier de tekstkolommen
        // dragen `order-last`.
        $this->assertSame(4, substr_count($html, 'order-last'));

        // Zodra de kolommen naast elkaar staan draaien sectie 2 en 4 dat terug
        // en komt de tekst links.
        $this->assertSame(2, substr_count($html, 'sm:order-none'));
    }
}
