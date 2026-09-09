<?php

namespace Tests\Feature\Content;

use App\Services\RunningPromotion;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\TestCase;

/**
 * De nieuwscollectie stond tot 07-09-2026 alleen op `nl`. Daardoor bleef de
 * actiebalk weg op /fr en /en — er was geen artikel om naartoe te linken — en
 * kon een actie niet in het Frans geadverteerd worden. De collectie, de
 * themataxonomie en de overzichtspagina lopen nu over de drie talen.
 */
class MultilingualNewsTest extends TestCase
{
    public function test_the_collection_and_its_taxonomy_run_over_the_three_sites(): void
    {
        foreach (['content/collections/articles.yaml', 'content/taxonomies/themes.yaml'] as $pad) {
            $yaml = file_get_contents(base_path($pad));

            $this->assertStringContainsString("sites:\n  - nl\n  - fr\n  - en", $yaml, "{$pad} staat niet op drie talen");
        }
    }

    public function test_the_overview_page_exists_in_every_language(): void
    {
        $titels = ['nl' => 'Nieuws', 'fr' => 'Actualités', 'en' => 'News'];

        foreach ($titels as $site => $titel) {
            $pagina = Entry::query()
                ->where('collection', 'pages')
                ->where('site', 'nl')
                ->where('slug', 'nieuws')
                ->first()
                ?->in($site);

            $this->assertNotNull($pagina, "De nieuwspagina ontbreekt in {$site}");
            $this->assertSame($titel, $pagina->value('title'));

            // Het template en de page builder erven van het Nederlands; zonder
            // die overerving zou de vertaalde pagina leeg renderen.
            $this->assertSame('articles/index', $pagina->value('template'));
        }
    }

    public function test_the_overview_renders_in_every_language(): void
    {
        $verwacht = [
            '/nieuws' => 'Nieuws',
            '/fr/actualites' => 'Actualités',
            '/en/news' => 'News',
        ];

        foreach ($verwacht as $url => $titel) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString($titel, $html);
            $this->assertStringContainsString('article-card ', $html, "Geen artikelkaart op {$url}");
        }
    }

    public function test_the_themes_carry_a_translated_title(): void
    {
        $html = $this->get('/fr/actualites')->assertOk()->getContent();

        // De filterpil leest de term in de taal van de site. Zonder
        // localisaties op de taxonomie stond hier "Events".
        $this->assertStringContainsString('Événements', $html);
    }

    public function test_the_action_article_is_translated_and_keeps_its_period(): void
    {
        $verwacht = [
            'fr' => 'Journées Rénovation',
            'en' => 'Renovation Days',
        ];

        foreach ($verwacht as $site => $titel) {
            $artikel = Entry::query()
                ->where('collection', 'articles')
                ->where('site', 'nl')
                ->where('slug', 'renovatiedagen-2026')
                ->first()
                ?->in($site);

            $this->assertNotNull($artikel, "De vertaling ontbreekt in {$site}");
            $this->assertStringContainsString($titel, $artikel->value('title'));

            // Periode en schakelaar staan alleen op het Nederlands: één
            // waarheid, anders zou een verlengde actie er drie keer aangepast
            // moeten worden.
            $this->assertNull($artikel->get('promo_end'));
            $this->assertSame('2026-10-18', $artikel->value('promo_end'));
            $this->assertTrue((bool) $artikel->value('promo'));

            // De tekst in de balk vertaalt wél.
            $this->assertNotNull($artikel->get('promo_label'));
        }
    }

    public function test_the_bar_finds_the_action_in_every_language(): void
    {
        $labels = [];

        foreach (['nl', 'fr', 'en'] as $site) {
            Site::setCurrent($site);

            $artikel = (new RunningPromotion)->find();

            $this->assertNotNull($artikel, "Geen lopende actie op {$site}");
            $this->assertStringStartsWith(match ($site) {
                'nl' => '/nieuws', 'fr' => '/fr/actualites', 'en' => '/en/news'
            }, $artikel->url());

            $labels[] = $artikel->value('promo_label');
        }

        Site::setCurrent('nl');

        $this->assertCount(3, array_unique($labels), 'Elke taal hoort zijn eigen tekst in de balk te krijgen');
    }
}
