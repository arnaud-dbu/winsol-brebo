# Winsol Brebo — Content Structure Design

**Date:** 2026-07-24
**Status:** Approved for planning
**Scope:** Statamic 6 blueprints, fieldsets, collections, taxonomy and globals for the Winsol Brebo site. Structure only — no templates, styling or content entries.

## Goal

Set up the full content architecture (collections, pages, page builder, taxonomies, globals) for the Winsol Brebo site inside the existing Statamic 6 starter kit, reusing the repo's mature fieldset-driven conventions rather than introducing new patterns.

## Conventions

These hold across every field defined below:

- **Link** → always `import: link` (the custom link grid fieldset already in the repo).
- **Icon** → always `import: icon` (Phosphor regular icon picker).
- **Image** → always `import: image` (single asset). Where multiple images are needed it is called out explicitly (an `assets` field with no `max_files`).
- **Text** → a bare `text` field is a **textarea** by default. `(bard)` means a `bard` field. `(text)` also means a textarea. Single-line `title`/`overline`/label fields use `type: text`.
- **Replicators/grids** → every replicator and grid uses `collapse: accordion` so only one item is open at a time.
- **Blueprint tabs** → page and routed-collection blueprints carry the repo's standard `preview`, `seo`, and `sidebar` (slug + template) tabs, following the existing `home.yaml` / `page.yaml` pattern. Data-only collections omit these.

## Build method

Hand-written YAML files placed directly under `resources/` (blueprints, fieldsets) and `content/` (collection/taxonomy/global config + tree files), matching the existing repo style. Git-reviewable.

---

## 1. Atomic fieldsets

Reused as-is (already present in `resources/fieldsets/`):

| Fieldset | Role |
|---|---|
| `link` | custom link grid — every link |
| `icon` | Phosphor icon picker — every icon |
| `image` | single asset — every image |
| `section_header` | `overline` + `title` + `text` (bard) + `link` — imported where a set matches this shape exactly |

**New fieldset — `page_intro`:**

```yaml
# resources/fieldsets/page_intro.yaml
title: 'Page Intro'
fields:
  - handle: title
    field:
      type: text
      display: Title
      required: true
      validate: [required]
  - handle: text
    field:
      type: textarea
      display: Text
```

Imported by the five simple page blueprints (range_overview, projects_overview, page, contact, invoice) to avoid repeating the title/text top block.

---

## 2. `page_builder` fieldset (rebuilt)

Replaces the current 4-set `resources/fieldsets/page_builder.yaml`. One shared replicator (`handle: page_builder`, `collapse: accordion`), imported by every page-builder-bearing blueprint. Sets:

- **cta** — `overline` (text), `title` (text), `text` (textarea), import `link`, import `image`.
  *(The two identical `cta` entries in the source spec are merged into this one set.)*
- **cards** — `overline` (text), `title` (text), `text` (textarea), `cards` replicator with a single set **card**: import `image`, `title` (text), `text` (bard).
- **image_gallery** — `overline` (text), `title` (text), `images` (assets, container `assets`, no `max_files` → multiple).
- **technical_details** — `overline` (text), `title` (text), `text` (textarea), import `link`, `technical_details` grid: `key` (text) + `value` (text) rows.
- **ranges** — `overline` (text), `title` (text), `range` (entries → `ranges` collection, multiple).
- **text** — import `section_header`.
- **text_image** — import `section_header`, `background` (toggle), import `image`.
- **products** — `overline` (text), `title` (text), `products` (entries → `products` collection, multiple).
- **projects** — `overline` (text), `title` (text), import `link`, `projects` (entries → `projects` collection, multiple).
- **features** — `overline` (text), `title` (text), `features` replicator with a single set **feature**: import `icon`, `title` (text), `text` (textarea).
- **grid_cta** — import `image`, `grid` replicator with a single set **item**: `title` (text), `text` (textarea), import `link`.

**Array → grid note:** the source spec's `technical_details` and `locations.opening_hours` are described as "array". They are implemented as **grid** fields (repeatable rows), since Statamic's `array` type does not support dynamic arbitrary rows.

---

## 3. Pages — blueprints in the `pages` collection

All page blueprints include the standard `preview` / `seo` / `sidebar` (slug + template) tabs.

### home (`resources/blueprints/collections/pages/home.yaml`) — replaces existing
Main tab:
- Hero: `title` (text, required), `text` (textarea), import `link`, import `image`.
- `value_proposition` (group): `title` (text) + `value_proposition` replicator with a single set: import `icon`, `title` (text), `text` (textarea).
- import `page_builder`.

### range_overview (Aanbod) — new
- import `page_intro`, import `page_builder`.

### projects_overview (Realisaties) — new
- import `page_intro`, import `page_builder`.

### services_overview (Service) — new
- import `page_intro`.
- `services` replicator with a single set: `overline` (text), `title` (text), `text` (bard), import `image`.
- `reparation` (group): `overline` (text), `title` (text), `text` (textarea).
- **No** `page_builder` (per spec).

### page (Over ons) (`resources/blueprints/collections/pages/page.yaml`) — replaces existing
- import `page_intro`, import `page_builder`.

### contact — new
- import `page_intro`.
- `quicklinks` (entries → `quicklinks` collection, multiple).
- import `page_builder`.

### invoice (Offerte) — new
- import `page_intro`, import `page_builder`.

> The seven Statamic entries themselves (Home, Aanbod, …) are not created by this structure work; only the blueprints. Entry creation is out of scope.

---

## 4. Collections

### ranges — routed
- `title` (text), `long_description` (textarea), `short_description` (textarea), import `image`, `range_category` (terms field → `range_categories` taxonomy), import `page_builder`.
- Standard `preview` / `seo` / `sidebar` tabs. Public route.

### products — routed
- `title` (text), `text` (textarea), import `image`, import `page_builder`.
- Standard tabs. Public route.

### projects — routed
- `title` (text), `text` (textarea), `product` (entries → `products`, `max_items: 1`), import `image`, import `page_builder`.
- Standard tabs. Public route.

### quicklinks — data-only
- `title` (text), `text` (textarea), import `image`, import `link`, `link_style` (select: `primary` / `outline`, default `primary`).
- No route, no seo/preview tabs.

### locations — data-only
- `name` (text), `street` (text), `number` (text), `postal_code` (text), `city` (text).
- `opening_hours` grid: `day` (text) + `time` (text) rows.
- No route, no seo/preview tabs.

Each collection needs its `content/collections/<handle>.yaml` config file. Routed collections define a `route`; data-only collections omit it.

---

## 5. Taxonomy

### range_categories
- Terms blueprint: `title` (text).
- Config `content/taxonomies/range_categories.yaml`.
- Attached to the `ranges` collection (via `taxonomies` in the ranges collection config), so the `range_category` field resolves.

---

## 6. Globals

### general_information — extend existing `globals` set
The existing `globals` global set (`resources/blueprints/globals/globals.yaml`, title "General Information") already holds `contact { phone, email }` plus `company` and `socials`.

**Change:** add a `mobile` key to the `contact` array, so it holds `mobile / phone / email` as specced. `company` and `socials` are kept. Update `content/globals/globals.yaml` accordingly so the stored value carries the new key.

---

## 7. Removals

Delete (not in spec, per approved cleanup — keeping articles, legal, and the seo global):

- Collection `cases`: `content/collections/cases/`, `content/collections/cases.yaml`, `resources/blueprints/collections/cases/`.
- Blueprint `resources/blueprints/collections/projects/project.yaml` (the old article-style project blueprint; the new `projects` collection blueprint replaces it).
- Blueprint `resources/blueprints/collections/pages/testing.yaml`.
- Globals `cta` and `service_nav`: `resources/blueprints/globals/cta.yaml`, `resources/blueprints/globals/service_nav.yaml`, and any corresponding `content/globals/*` values.

**Kept:** `articles`, `legal` collections; `seo` global; `main` navigation; the extended `globals` global; the standard `preview` / `seo` / `template` / `slug` fieldsets and other atomic fieldsets.

**Replaced:** `home` and `page` blueprints; the `page_builder` fieldset.

---

## Out of scope

- Antlers templates, Blade/HTML, CSS, front-end.
- Creating actual entries, terms, or asset uploads.
- Navigation tree contents beyond what already exists.
- Any change to `articles`, `legal`, `seo`, or the `main` navigation structure.

## Open assumptions (safe defaults, flag if wrong)

- Routed collections (`ranges`, `products`, `projects`) use straightforward routes (e.g. `/aanbod/{slug}`, `/producten/{slug}`, `/realisaties/{slug}`); exact route strings to be finalized in the plan.
- `link_style` uses handle `link_style` with options `primary` / `outline`.
- Grid handles: `technical_details` rows use `key`/`value`; `opening_hours` rows use `day`/`time`.
