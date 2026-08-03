<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ArticlesContentTest extends TestCase
{
    public function test_six_articles_exist_with_an_image_a_theme_and_a_body(): void
    {
        $articles = Entry::query()->where('collection', 'articles')->get();

        $this->assertCount(6, $articles);

        foreach ($articles as $article) {
            $this->assertNotEmpty($article->get('image'), "Artikel {$article->slug()} heeft geen beeld");
            $this->assertNotEmpty($article->get('theme'), "Artikel {$article->slug()} heeft geen thema");
            $this->assertNotEmpty($article->get('redactor'), "Artikel {$article->slug()} heeft een lege redactor");
        }
    }

    public function test_every_theme_resolves_to_a_term_of_the_themes_taxonomy(): void
    {
        // `theme` heeft `max_items: 1` en augmenteert dus naar één term, niet
        // naar een collectie. Deze test legt dat vast, want de header en de
        // kaart lezen `theme.title` met dot-notatie.
        foreach (Entry::query()->where('collection', 'articles')->get() as $article) {
            $term = $article->augmentedValue('theme')->value();

            $this->assertNotNull($term, "Het thema van {$article->slug()} augmenteert niet naar een term");
            $this->assertSame('themes', $term->taxonomy()->handle());
            $this->assertNotEmpty($term->get('title'));
        }
    }

    public function test_the_articles_cover_at_least_three_themes(): void
    {
        // Het filter moet zichtbaar iets doen. Met alles onder één thema is
        // een klik niet van "Toon alles" te onderscheiden.
        $slugs = Entry::query()->where('collection', 'articles')->get()
            ->map(fn ($article) => $article->augmentedValue('theme')->value()->slug())
            ->unique();

        $this->assertGreaterThanOrEqual(3, $slugs->count());
    }

    public function test_at_least_one_article_carries_a_video_block_and_one_an_inline_image(): void
    {
        $articles = Entry::query()->where('collection', 'articles')->get();

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
