# Static caching

This base ships with static caching ready to enable. It is **off locally** and
uses the **`half`** strategy **on production**.

## How it works (half vs full)

Static caching skips the expensive work of rendering a page on every request.
The difference between the two strategies is *how far* it skips it.

- **`half` measure** (application driver, this base's default on prod): PHP/Laravel
  still boots, but skips the Stache + Antlers render and serves the stored HTML
  from the cache store (redis on prod). A few milliseconds per request. Because
  PHP still boots, CSRF tokens, forms and `{{ nocache }}` blocks work without any
  extra setup, and no per-project nginx config is needed.
- **`full` measure** (file driver): writes `.html` files that nginx serves
  directly, without booting PHP. Fastest possible, but requires per-project nginx
  rewrite rules and JavaScript rehydration for every dynamic snippet. Opt in per
  project only when a site truly needs it.

For brochure sites the user-facing speed difference is negligible, while `half`
is far simpler and safer. That is why it is the default.

## Enabling it

Local / fresh installs: leave it off (you see content edits immediately):

```
STATAMIC_STATIC_CACHING_STRATEGY=null
```

Production `.env`:

```
STATAMIC_STATIC_CACHING_STRATEGY=half
```

Clear the cache after enabling or after deploys:

```
php please static:clear
```

## What does NOT belong in the cache

- **Forms / CSRF** — handled automatically by the `CsrfTokenReplacer` (already
  active in `config/statamic/static_caching.php`). No action needed.
- **Per-visitor content** (logged-in state, cart, "hello {name}", live
  timestamps) — wrap in `{{ nocache }}`. Rare on brochure sites.
- **Dynamic listings** — see below.

## Dynamic listings (the stale-content trap)

When a client publishes a new entry, the entry's own page updates, but listing
pages (a blog index, a "latest news" block on the homepage) keep serving their
cached version → "I published it but it's not showing" complaints.

**This base's approach: wrap the dynamic listing block in `{{ nocache }}`.**
Only the list re-renders on each request; the rest of the page stays cached.
Since PHP boots anyway under `half`, the cost is negligible and it can never be
stale.

```antlers
{{ nocache }}
    {{ collection:blog limit="3" }}
        <a href="{{ url }}">{{ title }}</a>
    {{ /collection:blog }}
{{ /nocache }}
```

### Alternative: invalidation rules

If you want a listing to stay *fully* cached for maximum speed, define
invalidation rules in `config/statamic/static_caching.php` instead (a commented
example is in the `invalidation.rules` array). You then maintain, per content
type, which URLs get flushed when an entry changes. Use this only when the extra
speed is worth the maintenance.

## Per-project workflow

The base carries the convention; each project supplies the details. At the end
of a project, have the listings wrapped: inspect the actual collections and
templates and place the `{{ nocache }}` wrappers (or invalidation rules) where
they belong.

## When to reach for `full`

Only when a specific site needs the absolute fastest TTFB and you accept the
cost: add the nginx rewrite rules to the Forge vhost, set
`STATAMIC_STATIC_CACHING_STRATEGY=full`, and make sure every dynamic snippet is
wrapped in `{{ nocache }}` with JS rehydration. See
https://statamic.dev/static-caching for the nginx configuration.
