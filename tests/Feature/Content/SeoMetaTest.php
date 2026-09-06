<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

/**
 * Twee dingen die `partials/seo.antlers.html` uitstuurt en die zich niet laten
 * afdekken door de fallback-tests: de vorm van het `<title>`-element en het
 * contenttype dat Open Graph meekrijgt.
 */
class SeoMetaTest extends TestCase
{
    use \Tests\Concerns\CreatesTemporaryContent;

    protected function setUp(): void
    {
        parent::setUp();

        // De acht nieuwsartikels stonden tot 05-09-2026 in de content en zijn
        // toen als testdata verwijderd. Deze tests toetsen de weergave ervan,
        // dus zetten ze de fixtures zelf neer.
        $this->seedArticles();
    }

    private const ARTICLE = '/nieuws/so-universe-bundelt-pergola-poolhouse-en-tuinkantoor';

    private function html(string $uri): string
    {
        return $this->get($uri)->assertOk()->getContent();
    }

    /**
     * Browsers en Google normaliseren whitespace, dus dit kost geen ranking.
     * Maar tooling dat de titellengte letterlijk telt — SERP-previews,
     * audits, linters — telt de inspringing mee en rapporteert dan tien te
     * lange titels waar er twee zijn.
     */
    public function test_the_title_element_holds_no_line_breaks_or_padding(): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->html('/aanbod/zonwering/screens'), $match);

        $this->assertSame(
            'Screens voor ramen | Winsol Brebo',
            $match[1] ?? '',
            'De titel hoort letterlijk in het element te staan, zonder newlines of inspringing.'
        );
    }

    /**
     * Geen enkele pagina in de contentset heet toevallig "Winsol Brebo", dus
     * een test op een echte route oefent de `==`-tak in de partial nooit uit
     * — de titel wordt daar altijd aangevuld met `| Winsol Brebo`. Render de
     * partial daarom direct, met een titel die wél gelijk is aan de sitenaam.
     */
    public function test_a_page_that_is_named_after_the_site_does_not_repeat_the_site_name(): void
    {
        $html = view('partials.seo', [
            'meta_title' => 'Winsol Brebo',
            'site' => ['name' => 'Winsol Brebo'],
        ])->render();

        preg_match('/<title>(.*?)<\/title>/s', $html, $match);

        $this->assertSame('Winsol Brebo', $match[1] ?? '');
        $this->assertStringNotContainsString('| Winsol Brebo | Winsol Brebo', $match[1] ?? '');
    }

    public function test_a_news_article_is_an_open_graph_article(): void
    {
        $this->assertStringContainsString(
            'property="og:type" content="article"',
            $this->html(self::ARTICLE)
        );
    }

    public function test_a_news_article_carries_its_publication_date(): void
    {
        $this->assertMatchesRegularExpression(
            '/property="article:published_time" content="2026-01-22T[^"]+"/',
            $this->html(self::ARTICLE)
        );
    }

    public function test_everything_that_is_not_an_article_stays_a_website(): void
    {
        foreach (['/', '/aanbod/zonwering/screens', '/contact'] as $uri) {
            $html = $this->html($uri);

            $this->assertStringContainsString(
                'property="og:type" content="website"',
                $html,
                "Verkeerd og:type op {$uri}"
            );
            $this->assertStringNotContainsString('article:published_time', $html, "Publicatiedatum op {$uri}");
        }
    }
}
