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

    public function test_the_sections_alternate_starting_with_the_image(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->get('/service')->getContent();

        // Sectie 1 en 3 hebben `order-last` op de tekstkolom (beeld links),
        // sectie 2 en 4 niet.
        $this->assertSame(2, substr_count($html, 'order-last'));
    }
}
