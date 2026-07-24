<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class SitemapController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Sitemap Index  →  /sitemap.xml
    |--------------------------------------------------------------------------
    | Returns a <sitemapindex> pointing to one sitemap per collection
    | (+ one for taxonomy terms). This keeps things organized and
    | automatically handles the 50,000 URL-per-file limit.
    */

    public function index(): Response
    {
        $xml = $this->cached('sitemap.index', fn() => $this->generateIndex());

        return $this->xmlResponse($xml);
    }

    /*
    |--------------------------------------------------------------------------
    | Collection Sitemap  →  /sitemap_{handle}.xml
    |--------------------------------------------------------------------------
    */

    public function collection(string $handle): Response
    {
        $collection = Collection::findByHandle($handle);

        if (! $collection || $this->isExcludedCollection($handle)) {
            abort(404);
        }

        $xml = $this->cached("sitemap.collection.{$handle}", fn() => $this->generateCollection($collection));

        return $this->xmlResponse($xml);
    }

    /*
    |--------------------------------------------------------------------------
    | Taxonomy Sitemap  →  /sitemap_taxonomies.xml
    |--------------------------------------------------------------------------
    */

    public function taxonomies(): Response
    {
        if (! config('sitemap.include_taxonomies', true)) {
            abort(404);
        }

        $xml = $this->cached('sitemap.taxonomies', fn() => $this->generateTaxonomies());

        return $this->xmlResponse($xml);
    }

    // ========================================================================
    // Generators
    // ========================================================================

    protected function generateIndex(): string
    {
        $sitemaps = collect();
        $baseUrl  = rtrim(config('app.url'), '/');

        // One sitemap per included collection
        Collection::all()
            ->reject(fn($c) => $this->isExcludedCollection($c->handle()))
            ->each(function ($collection) use ($sitemaps, $baseUrl) {
                // Only add if collection actually has qualifying entries
                $count = $this->qualifyingEntries($collection)->count();
                if ($count === 0) {
                    return;
                }

                $lastmod = $this->qualifyingEntries($collection)
                    ->sortByDesc(fn(Entry $e) => $e->lastModified())
                    ->first()
                    ?->lastModified()
                    ?->toAtomString();

                $sitemaps->push([
                    'loc'     => "{$baseUrl}/sitemap_{$collection->handle()}.xml",
                    'lastmod' => $lastmod,
                ]);
            });

        // Taxonomy sitemap
        if (config('sitemap.include_taxonomies', true)) {
            $sitemaps->push([
                'loc'     => "{$baseUrl}/sitemap_taxonomies.xml",
                'lastmod' => now()->toAtomString(),
            ]);
        }

        return $this->renderSitemapIndex($sitemaps);
    }

    protected function generateCollection($collection): string
    {
        $urls = collect();

        $this->qualifyingEntries($collection)
            ->each(function (Entry $entry) use ($urls) {
                $urls->push($this->entryToUrl($entry));
            });

        return $this->renderUrlSet($urls);
    }

    protected function generateTaxonomies(): string
    {
        $urls             = collect();
        $excludedHandles  = config('sitemap.excluded_taxonomies', []);

        Taxonomy::all()
            ->reject(fn($t) => in_array($t->handle(), $excludedHandles))
            ->each(function ($taxonomy) use ($urls) {
                Term::query()
                    ->where('taxonomy', $taxonomy->handle())
                    ->get()
                    ->each(function ($term) use ($urls) {
                        $url = $term->absoluteUrl();
                        if (! $url) {
                            return;
                        }

                        $urls->push([
                            'loc'        => $url,
                            'lastmod'    => now()->toAtomString(),
                            'changefreq' => 'monthly',
                            'priority'   => '0.3',
                        ]);
                    });
            });

        return $this->renderUrlSet($urls);
    }

    // ========================================================================
    // Entry Filtering (opinionated auto-exclusions)
    // ========================================================================

    /**
     * Return only the entries that qualify for the sitemap.
     */
    protected function qualifyingEntries($collection): \Illuminate\Support\Collection
    {
        return $collection->queryEntries()
            ->where('published', true)
            ->get()
            ->filter(fn(Entry $entry) => $this->qualifies($entry));
    }

    protected function qualifies(Entry $entry): bool
    {
        $rules = config('sitemap.auto_exclude', []);

        // Must have a routable URL
        if (data_get($rules, 'skip_no_url', true) && ! $entry->absoluteUrl()) {
            return false;
        }

        // Per-entry toggle override
        if ($entry->get('sitemap_exclude', false)) {
            return false;
        }

        // Entries that redirect are not real landing pages
        if (data_get($rules, 'skip_redirects', true) && $entry->get('redirect')) {
            return false;
        }

        // Entries explicitly marked noindex (common with SEO addons)
        if (data_get($rules, 'skip_noindex', true)) {
            if ($entry->get('seo_noindex') || $entry->get('noindex')) {
                return false;
            }
        }

        // Entries whose canonical URL points elsewhere (duplicate content)
        if (data_get($rules, 'skip_canonical', true)) {
            $canonical = $entry->get('seo_canonical') ?? $entry->get('canonical_url');
            if ($canonical && rtrim($canonical, '/') !== rtrim($entry->absoluteUrl(), '/')) {
                return false;
            }
        }

        return true;
    }

    // ========================================================================
    // Smart Priority & Change Frequency
    // ========================================================================

    protected function entryToUrl(Entry $entry): array
    {
        return [
            'loc'        => $entry->absoluteUrl(),
            'lastmod'    => ($entry->lastModified() ?? $entry->date())?->toAtomString(),
            'changefreq' => $this->resolveChangefreq($entry),
            'priority'   => $this->resolvePriority($entry),
        ];
    }

    /**
     * Auto-calculate priority based on URL depth.
     * Homepage = 1.0, /about = 0.8, /blog/post = 0.6, deeper = 0.5
     */
    protected function resolvePriority(Entry $entry): string
    {
        // Per-entry override
        if ($override = $entry->get('sitemap_priority')) {
            return (string) $override;
        }

        if (config('sitemap.priority_strategy', 'auto') !== 'auto') {
            return (string) config('sitemap.default_priority', 0.5);
        }

        $path  = parse_url($entry->absoluteUrl(), PHP_URL_PATH) ?? '/';
        $depth = count(array_filter(explode('/', trim($path, '/'))));

        return match (true) {
            $depth === 0 => '1.0',   // homepage
            $depth === 1 => '0.8',   // top-level pages
            $depth === 2 => '0.6',   // second level (blog posts, etc.)
            default      => '0.5',   // deeper nested pages
        };
    }

    /**
     * Auto-calculate changefreq based on how recently the entry was modified.
     */
    protected function resolveChangefreq(Entry $entry): string
    {
        // Per-entry override
        if ($override = $entry->get('sitemap_changefreq')) {
            return $override;
        }

        if (config('sitemap.changefreq_strategy', 'auto') !== 'auto') {
            return config('sitemap.default_changefreq', 'weekly');
        }

        $lastmod = $entry->lastModified() ?? $entry->date();

        if (! $lastmod) {
            return 'monthly';
        }

        $daysAgo = $lastmod->diffInDays(now());

        return match (true) {
            $daysAgo < 7   => 'daily',
            $daysAgo < 30  => 'weekly',
            $daysAgo < 180 => 'monthly',
            default        => 'yearly',
        };
    }

    // ========================================================================
    // XML Rendering
    // ========================================================================

    protected function renderSitemapIndex($sitemaps): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($sitemap['loc']) . '</loc>' . PHP_EOL;
            if (! empty($sitemap['lastmod'])) {
                $xml .= '    <lastmod>' . $sitemap['lastmod'] . '</lastmod>' . PHP_EOL;
            }
            $xml .= '  </sitemap>' . PHP_EOL;
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    protected function renderUrlSet($urls): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($urls as $url) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . PHP_EOL;

            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . PHP_EOL;
            }

            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . PHP_EOL;
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    protected function isExcludedCollection(string $handle): bool
    {
        return in_array($handle, config('sitemap.excluded_collections', []));
    }

    protected function xmlResponse(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type'  => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    protected function cached(string $key, callable $generator): string
    {
        $duration = config('sitemap.cache_duration', 60);

        return $duration > 0
            ? Cache::remember($key, $duration * 60, $generator)
            : $generator();
    }
}
