<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ArticlesContentTest extends TestCase
{
    use \Tests\Concerns\CreatesTemporaryContent;

    protected function setUp(): void
    {
        parent::setUp();

        // Deze klasse bewaakte de acht nieuwsartikels toen ze nog in de
        // content stonden. Ze zijn op 05-09-2026 verwijderd (testdata) en
        // leven verder als fixtures; de controles blijven zinvol omdat de
        // andere artikeltests op diezelfde set steunen.
        $this->seedArticles();
    }

    public function test_eight_articles_exist_with_an_image_a_theme_and_a_body(): void
    {
        $articles = Entry::query()->where('collection', 'articles')->where('site', 'nl')->get();

        $this->assertCount(8, $articles);

        foreach ($articles as $article) {
            $this->assertNotEmpty($article->get('image'), "Artikel {$article->slug()} heeft geen beeld");
            $this->assertNotEmpty($article->get('themes'), "Artikel {$article->slug()} heeft geen thema");
            $this->assertNotEmpty($article->get('redactor'), "Artikel {$article->slug()} heeft een lege redactor");
        }
    }

    public function test_every_theme_resolves_to_a_term_of_the_themes_taxonomy(): void
    {
        // `themes` heeft `max_items: 1` en augmenteert dus naar één term, niet
        // naar een collectie. Deze test legt dat vast, want de header en de
        // kaart lezen `themes.title` met dot-notatie.
        foreach (Entry::query()->where('collection', 'articles')->where('site', 'nl')->get() as $article) {
            $term = $article->augmentedValue('themes')->value();

            $this->assertNotNull($term, "Het thema van {$article->slug()} augmenteert niet naar een term");
            $this->assertSame('themes', $term->taxonomy()->handle());
            $this->assertNotEmpty($term->get('title'));
        }
    }

    public function test_the_articles_cover_every_theme(): void
    {
        // Het filter toont alleen thema's met minstens één artikel. Blijft er
        // eentje leeg, dan verdwijnt die pil en houdt het overzicht er minder
        // over dan de vijf categorieën die de taxonomie belooft.
        $slugs = Entry::query()->where('collection', 'articles')->where('site', 'nl')->get()
            ->map(fn ($article) => $article->augmentedValue('themes')->value()->slug())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['bedrijfsnieuws', 'events', 'producten', 'realisaties', 'showroom'],
            $slugs
        );
    }

    public function test_at_least_one_article_carries_a_video_block_and_one_an_inline_image(): void
    {
        $articles = Entry::query()->where('collection', 'articles')->where('site', 'nl')->get();

        $types = $articles->flatMap(fn ($article) => collect($article->augmentedValue('redactor')->value())
            ->map(fn ($node) => $node['type'] ?? null));

        $this->assertTrue($types->contains('video'), 'Geen enkel artikel heeft een videoblok');

        $html = $articles->flatMap(fn ($article) => collect($article->augmentedValue('redactor')->value())
            ->where('type', 'text')
            ->map(fn ($node) => (string) $node['text']))
            ->implode('');

        $this->assertStringContainsString('<img', $html, 'Geen enkel artikel heeft een beeld in de tekst');
    }

    public function test_the_collection_routes_under_nieuws_and_sorts_newest_first(): void
    {
        $yaml = file_get_contents(base_path('content/collections/articles.yaml'));

        $this->assertStringContainsString("route: '/nieuws/{slug}'", $yaml);
        $this->assertStringContainsString('sort_dir: desc', $yaml);
        $this->assertStringContainsString('- themes', $yaml);
    }

    public function test_the_legal_blueprint_keeps_the_plain_redactor_fieldset(): void
    {
        // `redactor.yaml` is gedeeld met legal. De video-set hoort alleen bij
        // artikels, dus die krijgt een eigen fieldset.
        $shared = file_get_contents(resource_path('fieldsets/redactor.yaml'));
        $this->assertStringNotContainsString('sets:', $shared);

        $legal = file_get_contents(resource_path('blueprints/collections/legal/legal.yaml'));
        $this->assertStringContainsString('import: redactor', $legal);

        $article = file_get_contents(resource_path('blueprints/collections/articles/article.yaml'));
        $this->assertStringContainsString('import: article_redactor', $article);
    }
}
