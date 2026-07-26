<?php

namespace Tests\Feature\Content;

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

    public function test_the_contact_blueprint_no_longer_carries_a_dead_quicklinks_field(): void
    {
        // De component leest altijd de hele collectie, dus dit veld deed niets
        // meer en hoort niet als loze knop in de CP te blijven staan.
        $blueprint = Entry::query()->where('collection', 'pages')->where('slug', 'contact')->first()->blueprint();

        $this->assertNull($blueprint->field('quicklinks'));
    }
}
