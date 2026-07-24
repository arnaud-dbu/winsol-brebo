# Static Caching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make static caching ready-to-enable in statamic-base via one env var (`half` on prod, off locally), with the dynamic exclusions clearly documented so clients never hit stale content or broken forms.

**Architecture:** No application code changes. This is config annotation + an env hint + a user-facing guide. The `half`/`full` strategies and the `STATAMIC_STATIC_CACHING_STRATEGY` switch already exist in the Statamic default config; we add explanatory comments, an example invalidation rule (commented), an `.env.example` hint, and a documentation file. The per-project work (wrapping listings in `{{ nocache }}`) is documented, not implemented in the base.

**Tech Stack:** Statamic 6, Laravel 12, PHP config files, Markdown docs. Prod cache-store is redis; `half` measure uses the application driver which writes to that store.

**Verification note:** There is no testable code logic here. Do NOT add PHPUnit tests. Verification is: config file stays valid PHP, the site renders, and (optionally) a manual cache smoke test. The existing `php artisan test` suite has a pre-existing memory failure on the image-compression test — unrelated, do not treat as a regression.

---

### Task 1: Annotate the static caching config

Add an explanatory comment block above the `strategy` key (explaining `half` vs `full` and the base's choice), and a commented example invalidation rule inside the `invalidation.rules` array.

**Files:**
- Modify: `config/statamic/static_caching.php:15` (above the `strategy` key)
- Modify: `config/statamic/static_caching.php:86-88` (the `rules` array)

- [ ] **Step 1: Add the strategy explanation comment**

Replace the existing block (lines 5-15):

```php
    /*
    |--------------------------------------------------------------------------
    | Active Static Caching Strategy
    |--------------------------------------------------------------------------
    |
    | To enable Static Caching, you should choose a strategy from the ones
    | you have defined below. Leave this null to disable static caching.
    |
    */

    'strategy' => env('STATAMIC_STATIC_CACHING_STRATEGY', null),
```

with:

```php
    /*
    |--------------------------------------------------------------------------
    | Active Static Caching Strategy
    |--------------------------------------------------------------------------
    |
    | To enable Static Caching, choose a strategy from the ones defined below.
    | Leave this null to disable static caching.
    |
    | This base uses "half" on production and null (off) locally. See
    | docs/static-caching.md for the full strategy and per-project workflow.
    |
    |   half  (application driver): PHP still boots but skips the heavy
    |         render and serves stored HTML from the cache store (redis on
    |         prod). Forms, CSRF and {{ nocache }} work without extra setup.
    |         No per-project nginx config. This base's default on prod.
    |
    |   full  (file driver): writes .html files that nginx serves directly,
    |         without booting PHP. Fastest, but needs per-project nginx
    |         rewrite rules and JS rehydration for every dynamic snippet.
    |         Opt in per project only when a site truly needs it.
    |
    */

    'strategy' => env('STATAMIC_STATIC_CACHING_STRATEGY', null),
```

- [ ] **Step 2: Add the commented example invalidation rule**

Replace the `rules` array (lines 86-88):

```php
        'rules' => [
            //
        ],
```

with:

```php
        'rules' => [
            // This base prefers wrapping dynamic listing blocks in
            // {{ nocache }} over maintaining invalidation rules (see
            // docs/static-caching.md). Use rules only when you want a
            // listing to stay fully cached for maximum speed. Example:
            //
            // 'collections' => [
            //     'blog' => [
            //         'urls' => [
            //             '/blog',
            //             '/',
            //         ],
            //     ],
            // ],
        ],
```

- [ ] **Step 3: Verify the config file is still valid PHP**

Run: `php -l config/statamic/static_caching.php`
Expected: `No syntax errors detected in config/statamic/static_caching.php`

- [ ] **Step 4: Verify the config loads**

Run: `php artisan config:clear && php artisan tinker --execute="echo config('statamic.static_caching.strategy') === null ? 'null-ok' : 'unexpected';"`
Expected: output contains `null-ok` (strategy is off by default, no exceptions thrown)

- [ ] **Step 5: Commit**

```bash
git add config/statamic/static_caching.php
git commit -m "$(cat <<'EOF'
docs(config): annotate static caching strategy and example rule

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Add the env hint

Add a comment above the existing strategy var in `.env.example` documenting the prod value and pointing to the doc. Leave the value itself at `null`.

**Files:**
- Modify: `.env.example:72`

- [ ] **Step 1: Add the comment line**

Replace line 72:

```
STATAMIC_STATIC_CACHING_STRATEGY=null
```

with:

```
# Static caching: null (off) locally, "half" on production. See docs/static-caching.md
STATAMIC_STATIC_CACHING_STRATEGY=null
```

- [ ] **Step 2: Verify the change**

Run: `grep -n -A1 "Static caching:" .env.example`
Expected: the comment line followed by `STATAMIC_STATIC_CACHING_STRATEGY=null`

- [ ] **Step 3: Commit**

```bash
git add .env.example
git commit -m "$(cat <<'EOF'
docs(env): document static caching strategy var

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Write the static caching guide

Create the user-facing guide that travels with the base.

**Files:**
- Create: `docs/static-caching.md`

- [ ] **Step 1: Write the guide**

Create `docs/static-caching.md` with this exact content:

```markdown
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
```

- [ ] **Step 2: Verify the file exists and renders as expected**

Run: `head -5 docs/static-caching.md`
Expected: starts with `# Static caching` and the intro sentence.

- [ ] **Step 3: Commit**

```bash
git add docs/static-caching.md
git commit -m "$(cat <<'EOF'
docs: add static caching guide

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Optional manual smoke test (not a task — run if you want confidence)

1. Set `STATAMIC_STATIC_CACHING_STRATEGY=half` in `.env`, run `php artisan config:clear`.
2. Serve: `php artisan serve`.
3. Hit a page twice. Confirm it renders correctly on both requests.
4. Submit the contact form — confirm CSRF still validates (form works).
5. Reset: set the var back to `null`, run `php please static:clear` and `php artisan config:clear`.

---

## Self-review notes

- **Spec coverage:** strategy choice (Task 1 comment + doc), local-vs-live env
  (Task 2 + doc), nocache-for-listings approach (Task 3 doc + Task 1 example),
  "what doesn't belong in cache" checklist (Task 3 doc), config annotation
  (Task 1), env hint (Task 2), guide (Task 3). The YAGNI exclusions (no custom
  invalidator, no warm-queue, no nginx config) are respected — nothing in the
  plan adds them.
- **Naming consistency:** env var `STATAMIC_STATIC_CACHING_STRATEGY`, config key
  `statamic.static_caching.strategy`, doc path `docs/static-caching.md`, and
  command `php please static:clear` are used identically across all tasks.
```
