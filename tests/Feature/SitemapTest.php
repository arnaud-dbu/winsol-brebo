<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_the_sitemap_index_names_child_sitemaps(): void
    {
        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<sitemapindex', $body);
        $this->assertNotEmpty(
            $this->locs($body),
            'Een sitemap-index zonder child-sitemaps wijst nergens heen.',
        );
    }

    /**
     * De sitemap is een uitnodiging aan Google. Een URL die daarin staat en
     * vervolgens een serverfout gooit, kost crawl budget en vertrouwen — en
     * dat is precies hoe /cases onopgemerkt kapot kon staan: die pagina was
     * nergens gelinkt, dus geen enkele klik kwam er ooit langs. Alleen de
     * sitemap wees er nog naar.
     */
    public function test_every_url_in_the_sitemap_renders(): void
    {
        $paths = $this->sitemapPaths();

        $this->assertNotEmpty($paths, 'Zonder URLs toetst deze test niets.');

        $broken = [];

        foreach ($paths as $path) {
            $status = $this->get($path)->getStatusCode();

            if ($status !== 200) {
                $broken[] = "{$path} → {$status}";
            }
        }

        $this->assertSame([], $broken, "Deze URL's staan in de sitemap maar renderen niet:\n".implode("\n", $broken));
    }

    public function test_the_afgevoerde_cases_page_is_gone_from_both_the_site_and_the_sitemap(): void
    {
        $this->get('/cases')->assertNotFound();

        $this->assertNotContains(
            '/cases',
            $this->sitemapPaths(),
            'De cases-pagina is afgevoerd; hij hoort niet meer in de sitemap te staan.',
        );
    }

    /**
     * Loopt de index af en verzamelt de paden uit elke child-sitemap.
     *
     * @return list<string>
     */
    private function sitemapPaths(): array
    {
        $paths = [];

        foreach ($this->locs($this->get('/sitemap.xml')->getContent()) as $childUrl) {
            $child = $this->get($this->pathOf($childUrl))->getContent();

            foreach ($this->locs($child) as $pageUrl) {
                $paths[] = $this->pathOf($pageUrl);
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function locs(string $xml): array
    {
        preg_match_all('#<loc>(.*?)</loc>#s', $xml, $matches);

        return array_map(trim(...), $matches[1]);
    }

    /**
     * Een sitemap-loc zonder pad is de homepage: `https://host`.
     */
    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return ($path === null || $path === '') ? '/' : $path;
    }
}
