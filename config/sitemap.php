<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Collections
    |--------------------------------------------------------------------------
    |
    | The ONLY setting you'll typically touch. Add collection handles here
    | to remove an entire collection from the sitemap.
    |
    | Everything else is included by default and handled automatically.
    |
    | Example: ['forms', 'redirects', 'error_pages']
    |
    */

    'excluded_collections' => [
        // 'forms',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Taxonomies
    |--------------------------------------------------------------------------
    |
    | Same principle: list taxonomy handles you want to keep out.
    |
    */

    'excluded_taxonomies' => [
        // 'tags',
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Taxonomies
    |--------------------------------------------------------------------------
    |
    | Whether taxonomy term URLs should be included. Enabled by default
    | because term pages are often valuable landing pages.
    |
    */

    'include_taxonomies' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-Exclude Rules (opinionated defaults)
    |--------------------------------------------------------------------------
    |
    | These run automatically on every entry. You shouldn't need to change
    | these unless you have a very specific use case.
    |
    | - skip_redirects:  Entries with a `redirect` field are excluded.
    | - skip_noindex:    Entries with `seo_noindex: true` are excluded.
    | - skip_unlisted:   Entries with `status: draft` are excluded (safety net).
    | - skip_no_url:     Entries without a routable URL are excluded.
    | - skip_canonical:  Entries whose canonical URL differs from their own
    |                    URL are excluded (avoids duplicate content).
    |
    */

    'auto_exclude' => [
        'skip_redirects' => true,
        'skip_noindex'   => true,
        'skip_unlisted'  => true,
        'skip_no_url'    => true,
        'skip_canonical'  => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Strategy
    |--------------------------------------------------------------------------
    |
    | "auto" = intelligently calculated based on URI depth:
    |   Homepage → 1.0, depth-1 → 0.8, depth-2 → 0.6, deeper → 0.5
    |
    | "flat" = same priority for everything (uses default_priority below).
    |
    */

    'priority_strategy' => 'auto',
    'default_priority'  => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Change Frequency Strategy
    |--------------------------------------------------------------------------
    |
    | "auto" = derived from the entry's actual last modification date:
    |   Updated < 7 days ago   → daily
    |   Updated < 30 days ago  → weekly
    |   Updated < 180 days ago → monthly
    |   Older                  → yearly
    |
    | "flat" = same changefreq for everything (uses default_changefreq).
    |
    */

    'changefreq_strategy' => 'auto',
    'default_changefreq'  => 'weekly',

    /*
    |--------------------------------------------------------------------------
    | Cache Duration (minutes)
    |--------------------------------------------------------------------------
    |
    | The sitemap is cached and auto-busted when content changes.
    | Set to 0 to disable caching entirely.
    |
    */

    'cache_duration' => 60,

];
