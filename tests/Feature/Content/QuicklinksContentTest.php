<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Tests\TestCase;

class QuicklinksContentTest extends TestCase
{
    public function test_every_quicklink_exists_with_its_copy_and_button_style(): void
    {
        $expected = [
            'vraag-offerte-aan' => [
                'title' => 'Vraag offerte aan',
                'label' => 'Vraag offerte aan',
                'link_style' => 'primary',
            ],
            'vraag-brochure-aan' => [
                'title' => 'Vraag brochure aan',
                'label' => 'Brochure aanvragen',
                'link_style' => 'outline',
            ],
            'bezoek-een-showroom' => [
                'title' => 'Bezoek een showroom',
                'label' => 'Plan een bezoek',
                'link_style' => 'outline',
            ],
        ];

        foreach ($expected as $slug => $fields) {
            $entry = Entry::query()->where('collection', 'quicklinks')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Quicklink {$slug} ontbreekt");
            $this->assertSame($fields['title'], $entry->get('title'));
            $this->assertSame($fields['link_style'], $entry->get('link_style'));
            $this->assertNotEmpty($entry->get('text'), "Quicklink {$slug} heeft geen tekst");

            $link = $entry->get('link');
            $this->assertIsArray($link);
            $this->assertCount(1, $link, "Quicklink {$slug} hoort precies een link te hebben");
            $this->assertSame($fields['label'], $link[0]['label']);
            $this->assertSame('entry', $link[0]['type']);
        }
    }

    public function test_every_quicklink_points_at_an_entry_that_actually_exists(): void
    {
        $entries = Entry::query()->where('collection', 'quicklinks')->get();

        $this->assertCount(3, $entries);

        foreach ($entries as $entry) {
            $targetId = $entry->get('link')[0]['entry'][0];

            $this->assertNotNull(
                Entry::find($targetId),
                "De knop van {$entry->slug()} wijst naar een niet-bestaande entry"
            );
        }
    }

    public function test_the_quicklinks_are_ordered_as_designed(): void
    {
        $slugs = Entry::query()
            ->where('collection', 'quicklinks')
            ->orderBy('order')
            ->get()
            ->map->slug()
            ->all();

        $this->assertSame(
            ['vraag-offerte-aan', 'vraag-brochure-aan', 'bezoek-een-showroom'],
            $slugs,
            'De volgorde uit het design (offerte, brochure, showroom) klopt niet'
        );
    }

    public function test_the_contact_blueprint_carries_a_page_local_quicklinks_grid(): void
    {
        // Het oude `quicklinks`-veld was een entries-picker die dubbelde met
        // de collectie en daarom verdween. Dit veld is iets anders: een grid
        // met pagina-eigen rijen, die de collectie niet raken. Het onderscheid
        // staat hier vast zodat de picker niet terugsluipt.
        $blueprint = Blueprint::find('collections.pages.contact');

        $this->assertNotNull($blueprint, 'Blueprint collections.pages.contact niet gevonden');

        $field = $blueprint->field('quicklinks');

        $this->assertNotNull($field, 'Het quicklinks-veld ontbreekt op de contact-blueprint');
        $this->assertSame('grid', $field->type());

        // $field->get('fields') geeft de ruwe config terug, waarin `import:
        // image` en `import: link` nog niet zijn uitgevouwen naar hun handle.
        // fieldtype()->fields() loopt dezelfde resolutie als de CP en de
        // publish-form, en levert dus wel de echte handles op.
        $subfields = $field->fieldtype()->fields()->all()->keys()->all();

        $this->assertSame(['title', 'text', 'image', 'link', 'link_style'], $subfields);
    }

    public function test_the_contact_blueprint_keeps_its_template_picker(): void
    {
        // De entry draagt `template: contact` in zijn front matter. Zonder het
        // veld in het blueprint kan een CP-bewerking dat laten vallen, waarna
        // /contact terugvalt op default.antlers.html.
        $blueprint = Blueprint::find('collections.pages.contact');

        $this->assertNotNull($blueprint->field('template'));
    }

    public function test_exactly_one_quicklink_is_the_brochure_card(): void
    {
        $brochureCards = Entry::query()
            ->where('collection', 'quicklinks')
            ->get()
            ->filter(fn ($entry) => $entry->get('type') === 'brochure');

        $this->assertCount(1, $brochureCards, 'Het kolomaantal in quicklinks.antlers.html gaat uit van precies een brochurekaart');
    }
}
