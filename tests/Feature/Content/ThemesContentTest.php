<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Term;
use Tests\TestCase;

class ThemesContentTest extends TestCase
{
    /**
     * `site` staat er sinds 07-09-2026 bij: de taxonomie loopt over nl, fr en
     * en, en zonder die filter telt een kale query elke term drie keer.
     */
    public function test_the_five_themes_exist_with_a_title(): void
    {
        $themes = Term::query()->where('taxonomy', 'themes')->where('site', 'nl')->get();

        $this->assertCount(5, $themes);

        foreach ($themes as $theme) {
            $this->assertNotEmpty($theme->get('title'), "Thema {$theme->slug()} heeft geen titel");
        }
    }

    public function test_every_theme_carries_its_own_title_per_language(): void
    {
        // Zonder vertaling toont de filterpil op /fr/nieuws de Nederlandse
        // categorie, en dat is het enige woord dat de bezoeker daar leest.
        // "Showroom" en "Events" vallen in het Engels samen met het
        // Nederlands; die staan hier uitgeschreven zodat ze niet als vergeten
        // vertaling gelezen worden.
        $verwacht = [
            'nl' => [
                'bedrijfsnieuws' => 'Bedrijfsnieuws',
                'events' => 'Events',
                'producten' => 'Producten',
                'realisaties' => 'Realisaties',
                'showroom' => 'Showroom',
            ],
            'fr' => [
                'bedrijfsnieuws' => "Actualités d'entreprise",
                'events' => 'Événements',
                'producten' => 'Produits',
                'realisaties' => 'Réalisations',
                'showroom' => 'Showroom',
            ],
            'en' => [
                'bedrijfsnieuws' => 'Company news',
                'events' => 'Events',
                'producten' => 'Products',
                'realisaties' => 'Projects',
                'showroom' => 'Showroom',
            ],
        ];

        foreach ($verwacht as $site => $titels) {
            foreach ($titels as $slug => $titel) {
                $term = Term::query()
                    ->where('taxonomy', 'themes')
                    ->where('site', $site)
                    ->where('slug', $slug)
                    ->first();

                $this->assertNotNull($term, "{$slug} bestaat niet in {$site}");
                $this->assertSame($titel, $term->value('title'), "{$slug} heeft de verkeerde titel in {$site}");
            }
        }
    }

    public function test_the_slugs_are_the_ones_the_filter_and_the_articles_refer_to(): void
    {
        $slugs = Term::query()->where('taxonomy', 'themes')->where('site', 'nl')->get()
            ->map->slug()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['bedrijfsnieuws', 'events', 'producten', 'realisaties', 'showroom'],
            $slugs
        );
    }

    public function test_the_themes_carry_no_order_field(): void
    {
        // Anders dan `range_categories` heeft dit filter geen ontworpen
        // volgorde: het sorteert alfabetisch. Een `order`-veld zou suggereren
        // dat er wél een bedoelde volgorde is.
        $blueprint = file_get_contents(resource_path('blueprints/taxonomies/themes/themes.yaml'));

        $this->assertStringNotContainsString('order', $blueprint);
    }
}
