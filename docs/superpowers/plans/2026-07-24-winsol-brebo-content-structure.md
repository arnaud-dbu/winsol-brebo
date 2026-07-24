# Winsol Brebo Content Structure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full Statamic 6 content architecture (fieldsets, blueprints, collections, taxonomy, global) for the Winsol Brebo site by hand-writing YAML that reuses the repo's existing atomic fieldsets.

**Architecture:** Flat-file Statamic. Atomic fieldsets (`link`, `icon`, `image`, `section_header`) are imported into a shared `page_builder` replicator and into page/collection blueprints. Every blueprint follows the repo's existing tab convention (`main` + `preview` + `seo` + `sidebar`). No PHP, templates, or entries — structure only.

**Tech Stack:** Statamic 6 / Laravel 12, YAML config under `resources/` (schemas) and `content/` (collection/taxonomy/global config). Verification via Python's YAML parser.

## Global Constraints

- **Reuse atomics, never re-define them:** every link → `- import: link`; every icon → `- import: icon`; every single image → `- import: image`; the `overline + title + text(bard) + link` shape → `- import: section_header`.
- **Text convention:** a bare `text` field → `type: textarea`; `(bard)` → `type: bard`; single-line `title`/`overline`/`name` → `type: text`.
- **Every replicator and grid** carries `collapse: accordion` (one item open at a time).
- **Follow existing block-style YAML** (2-space indent, `handle:`/`field:` pairs) exactly as in `resources/fieldsets/section_header.yaml` — no flow maps.
- **Do not touch** `articles`, `legal`, the `seo` global, the `main` navigation, or any atomic fieldset other than `globals`.
- **Routes:** `ranges` → `/aanbod/{slug}`, `products` → `/producten/{slug}`, `projects` → `/realisaties/{slug}`.

---

## Reusable snippets (referenced verbatim by tasks below)

**`YAMLCHECK`** — the syntax-verification command used in every task. Substitute the file paths:

```bash
python3 -c "import yaml,sys; [yaml.safe_load(open(f)) for f in sys.argv[1:]]; print('YAML OK')" <file1> <file2> ...
```
Expected output: `YAML OK`. Any parse error prints a traceback and non-zero exit → fix the YAML and re-run.

**`STANDARD_TABS`** — the three tab blocks appended after `main:` in every page blueprint and every routed-collection blueprint (`ranges`, `products`, `projects`). Paste verbatim as siblings of `main:` under `tabs:` (2-space indent under `tabs:`):

```yaml
  preview:
    display: Preview
    sections:
      -
        fields:
          -
            import: preview
  seo:
    display: SEO
    sections:
      -
        fields:
          -
            import: seo
  sidebar:
    display: Sidebar
    sections:
      -
        fields:
          -
            handle: slug
            field:
              type: slug
              localizable: true
              validate: 'max:200'
          -
            import: template
```

**`SLUG_ONLY_SIDEBAR`** — the minimal sidebar for the two data-only collections (`quicklinks`, `locations`). Paste as a sibling of `main:` under `tabs:`:

```yaml
  sidebar:
    display: Sidebar
    sections:
      -
        fields:
          -
            handle: slug
            field:
              type: slug
              localizable: true
              validate: 'max:200'
```

---

## Task 1: Remove non-spec scaffolding

**Files:**
- Delete: `content/collections/cases.yaml`, `content/collections/cases/` (dir)
- Delete: `resources/blueprints/collections/cases/` (dir)
- Delete: `resources/blueprints/collections/projects/project.yaml`
- Delete: `resources/blueprints/collections/pages/testing.yaml`
- Delete: `resources/blueprints/globals/cta.yaml`, `resources/blueprints/globals/service_nav.yaml`

**Interfaces:**
- Produces: a repo whose collections are `articles`, `legal`, `pages`; whose global blueprints are `globals`, `seo`; and a clean `pages` blueprint dir (only `home.yaml`, `page.yaml` remain, both replaced later).

- [ ] **Step 1: Confirm the navigation does not reference `cases`**

Run: `grep -rn "cases" content/trees content/navigation`
Expected: no output (empty). If any line references `cases`, stop and report it before deleting — a nav link would dangle.

- [ ] **Step 2: Delete the files and directories**

```bash
git rm -r content/collections/cases.yaml content/collections/cases \
  resources/blueprints/collections/cases \
  resources/blueprints/collections/projects/project.yaml \
  resources/blueprints/collections/pages/testing.yaml \
  resources/blueprints/globals/cta.yaml \
  resources/blueprints/globals/service_nav.yaml
```

- [ ] **Step 3: Remove the now-empty `projects` blueprint dir if empty**

```bash
rmdir resources/blueprints/collections/projects 2>/dev/null || true
```
(A `projects` collection blueprint dir is re-created fresh in Task 7.)

- [ ] **Step 4: Verify the expected state**

Run: `ls content/collections && echo "---" && ls resources/blueprints/globals && echo "---" && ls resources/blueprints/collections/pages`
Expected: collections list shows `articles articles.yaml legal legal.yaml pages pages.yaml` (no `cases`); globals shows `globals.yaml seo.yaml`; pages shows `home.yaml page.yaml` (no `testing.yaml`).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove non-spec starter scaffolding (cases, testing, cta/service_nav globals)"
```

---

## Task 2: `page_intro` fieldset

**Files:**
- Create: `resources/fieldsets/page_intro.yaml`

**Interfaces:**
- Produces: fieldset `page_intro` exposing handles `title` (text, required) and `text` (textarea). Imported by `page`, `range_overview`, `projects_overview`, `invoice`, `services_overview`, `contact` blueprints.

- [ ] **Step 1: Create the fieldset**

`resources/fieldsets/page_intro.yaml`:
```yaml
title: 'Page Intro'
fields:
  -
    handle: title
    field:
      type: text
      display: Title
      required: true
      validate:
        - required
  -
    handle: text
    field:
      type: textarea
      display: Text
```

- [ ] **Step 2: Verify YAML**

Run `YAMLCHECK` with: `resources/fieldsets/page_intro.yaml`
Expected: `YAML OK`

- [ ] **Step 3: Commit**

```bash
git add resources/fieldsets/page_intro.yaml
git commit -m "feat: add page_intro fieldset"
```

---

## Task 3: Rebuild the `page_builder` fieldset

**Files:**
- Modify (full replace): `resources/fieldsets/page_builder.yaml`

**Interfaces:**
- Consumes: atomic fieldsets `link`, `icon`, `image`, `section_header`; collection handles `ranges`, `products`, `projects` (as `entries` targets — string references, resolved at runtime).
- Produces: fieldset `page_builder` (replicator, handle `page_builder`) with sets `cta`, `cards`, `image_gallery`, `technical_details`, `ranges`, `text`, `text_image`, `products`, `projects`, `features`, `grid_cta`. Imported by every page-builder-bearing blueprint.

- [ ] **Step 1: Replace the file contents**

`resources/fieldsets/page_builder.yaml`:
```yaml
title: 'Page Builder'
fields:
  -
    handle: page_builder
    field:
      type: replicator
      display: 'Page Builder'
      collapse: accordion
      button_label: 'Add Section'
      sets:
        new_set_group:
          display: 'New Set Group'
          sets:
            cta:
              display: 'Call to Action'
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: text
                  field:
                    type: textarea
                    display: Text
                -
                  import: link
                -
                  import: image
            cards:
              display: Cards
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: text
                  field:
                    type: textarea
                    display: Text
                -
                  handle: cards
                  field:
                    type: replicator
                    display: Cards
                    collapse: accordion
                    button_label: 'Add Card'
                    sets:
                      new_set_group:
                        display: 'New Set Group'
                        sets:
                          card:
                            display: Card
                            fields:
                              -
                                import: image
                              -
                                handle: title
                                field:
                                  type: text
                                  display: Title
                              -
                                handle: text
                                field:
                                  type: bard
                                  display: Text
                                  buttons:
                                    - bold
                                    - italic
                                    - anchor
                                  container: assets
                                  remove_empty_nodes: false
            image_gallery:
              display: 'Image Gallery'
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: images
                  field:
                    type: assets
                    container: assets
                    display: Images
            technical_details:
              display: 'Technical Details'
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: text
                  field:
                    type: textarea
                    display: Text
                -
                  import: link
                -
                  handle: technical_details
                  field:
                    type: grid
                    mode: table
                    display: 'Technical Details'
                    add_row: 'Add Row'
                    fields:
                      -
                        handle: key
                        field:
                          type: text
                          display: Key
                      -
                        handle: value
                        field:
                          type: text
                          display: Value
            ranges:
              display: Ranges
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: range
                  field:
                    type: entries
                    collections:
                      - ranges
                    display: Ranges
            text:
              display: Text
              fields:
                -
                  import: section_header
            text_image:
              display: 'Text + Image'
              fields:
                -
                  import: section_header
                -
                  handle: background
                  field:
                    type: toggle
                    display: Background
                -
                  import: image
            products:
              display: Products
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: products
                  field:
                    type: entries
                    collections:
                      - products
                    display: Products
            projects:
              display: Projects
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  import: link
                -
                  handle: projects
                  field:
                    type: entries
                    collections:
                      - projects
                    display: Projects
            features:
              display: Features
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: features
                  field:
                    type: replicator
                    display: Features
                    collapse: accordion
                    button_label: 'Add Feature'
                    sets:
                      new_set_group:
                        display: 'New Set Group'
                        sets:
                          feature:
                            display: Feature
                            fields:
                              -
                                import: icon
                              -
                                handle: title
                                field:
                                  type: text
                                  display: Title
                              -
                                handle: text
                                field:
                                  type: textarea
                                  display: Text
            grid_cta:
              display: 'Grid CTA'
              fields:
                -
                  import: image
                -
                  handle: grid
                  field:
                    type: replicator
                    display: Grid
                    collapse: accordion
                    button_label: 'Add Item'
                    sets:
                      new_set_group:
                        display: 'New Set Group'
                        sets:
                          item:
                            display: Item
                            fields:
                              -
                                handle: title
                                field:
                                  type: text
                                  display: Title
                              -
                                handle: text
                                field:
                                  type: textarea
                                  display: Text
                              -
                                import: link
```

- [ ] **Step 2: Verify YAML**

Run `YAMLCHECK` with: `resources/fieldsets/page_builder.yaml`
Expected: `YAML OK`

- [ ] **Step 3: Confirm all 11 sets are present**

Run: `python3 -c "import yaml; s=yaml.safe_load(open('resources/fieldsets/page_builder.yaml'))['fields'][0]['field']['sets']['new_set_group']['sets']; print(sorted(s)); assert len(s)==11, len(s)"`
Expected: a sorted list of 11 set handles: `['cards', 'cta', 'features', 'grid_cta', 'image_gallery', 'products', 'projects', 'ranges', 'text', 'text_image', 'technical_details']`

- [ ] **Step 4: Commit**

```bash
git add resources/fieldsets/page_builder.yaml
git commit -m "feat: rebuild page_builder fieldset with Winsol Brebo sets"
```

---

## Task 4: `range_categories` taxonomy

**Files:**
- Create: `content/taxonomies/range_categories.yaml`
- Create: `resources/blueprints/taxonomies/range_categories/range_categories.yaml`

**Interfaces:**
- Produces: taxonomy handle `range_categories` with a term `title` field. Consumed by the `ranges` collection (Task 5) via a `terms` field and the collection's `taxonomies` config.

- [ ] **Step 1: Create the taxonomy config**

`content/taxonomies/range_categories.yaml`:
```yaml
title: 'Range Categories'
```

- [ ] **Step 2: Create the term blueprint**

`resources/blueprints/taxonomies/range_categories/range_categories.yaml`:
```yaml
title: 'Range Category'
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
```

- [ ] **Step 3: Verify YAML**

Run `YAMLCHECK` with: `content/taxonomies/range_categories.yaml resources/blueprints/taxonomies/range_categories/range_categories.yaml`
Expected: `YAML OK`

- [ ] **Step 4: Commit**

```bash
git add content/taxonomies/range_categories.yaml resources/blueprints/taxonomies/range_categories/range_categories.yaml
git commit -m "feat: add range_categories taxonomy"
```

---

## Task 5: `ranges` collection

**Files:**
- Create: `content/collections/ranges.yaml`
- Create: `content/collections/ranges/` (dir for entries)
- Create: `resources/blueprints/collections/ranges/ranges.yaml`

**Interfaces:**
- Consumes: `image`, `page_builder`, `template`, `preview`, `seo` fieldsets; `range_categories` taxonomy.
- Produces: collection handle `ranges` (routed `/aanbod/{slug}`), referenced by the `page_builder` `ranges` set and the projects `product`? No — referenced by `page_builder.ranges`.

- [ ] **Step 1: Create the collection config**

`content/collections/ranges.yaml`:
```yaml
title: Ranges
route: '/aanbod/{slug}'
taxonomies:
  - range_categories
```

- [ ] **Step 2: Create the entries directory placeholder**

```bash
mkdir -p content/collections/ranges && touch content/collections/ranges/.gitkeep
```

- [ ] **Step 3: Create the blueprint**

`resources/blueprints/collections/ranges/ranges.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
title: Range
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
          -
            handle: short_description
            field:
              type: textarea
              display: 'Short Description'
          -
            handle: long_description
            field:
              type: textarea
              display: 'Long Description'
          -
            import: image
          -
            handle: range_category
            field:
              type: terms
              taxonomies:
                - range_categories
              display: 'Range Category'
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `content/collections/ranges.yaml resources/blueprints/collections/ranges/ranges.yaml`
Expected: `YAML OK`

- [ ] **Step 5: Confirm tabs present**

Run: `python3 -c "import yaml; t=yaml.safe_load(open('resources/blueprints/collections/ranges/ranges.yaml'))['tabs']; print(sorted(t)); assert set(t)=={'main','preview','seo','sidebar'}"`
Expected: `['main', 'preview', 'seo', 'sidebar']`

- [ ] **Step 6: Commit**

```bash
git add content/collections/ranges.yaml content/collections/ranges/.gitkeep resources/blueprints/collections/ranges/ranges.yaml
git commit -m "feat: add ranges collection"
```

---

## Task 6: `products` collection

**Files:**
- Create: `content/collections/products.yaml`
- Create: `content/collections/products/.gitkeep`
- Create: `resources/blueprints/collections/products/products.yaml`

**Interfaces:**
- Consumes: `image`, `page_builder`, `template`, `preview`, `seo`.
- Produces: collection handle `products` (routed `/producten/{slug}`), referenced by `page_builder.products` and by the `projects` collection's `product` field.

- [ ] **Step 1: Create the collection config**

`content/collections/products.yaml`:
```yaml
title: Products
route: '/producten/{slug}'
```

- [ ] **Step 2: Create the entries directory placeholder**

```bash
mkdir -p content/collections/products && touch content/collections/products/.gitkeep
```

- [ ] **Step 3: Create the blueprint**

`resources/blueprints/collections/products/products.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
title: Product
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
          -
            handle: text
            field:
              type: textarea
              display: Text
          -
            import: image
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `content/collections/products.yaml resources/blueprints/collections/products/products.yaml`
Expected: `YAML OK`

- [ ] **Step 5: Commit**

```bash
git add content/collections/products.yaml content/collections/products/.gitkeep resources/blueprints/collections/products/products.yaml
git commit -m "feat: add products collection"
```

---

## Task 7: `projects` collection

**Files:**
- Create: `content/collections/projects.yaml`
- Create: `content/collections/projects/.gitkeep`
- Create: `resources/blueprints/collections/projects/projects.yaml`

**Interfaces:**
- Consumes: `image`, `page_builder`, `template`, `preview`, `seo`; collection `products` (via `product` entries field, `max_items: 1`).
- Produces: collection handle `projects` (routed `/realisaties/{slug}`), referenced by `page_builder.projects`.

- [ ] **Step 1: Create the collection config**

`content/collections/projects.yaml`:
```yaml
title: Projects
route: '/realisaties/{slug}'
```

- [ ] **Step 2: Create the entries directory placeholder**

```bash
mkdir -p content/collections/projects && touch content/collections/projects/.gitkeep
```

- [ ] **Step 3: Create the blueprint**

`resources/blueprints/collections/projects/projects.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
title: Project
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
          -
            handle: text
            field:
              type: textarea
              display: Text
          -
            handle: product
            field:
              type: entries
              collections:
                - products
              max_items: 1
              display: Product
          -
            import: image
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `content/collections/projects.yaml resources/blueprints/collections/projects/projects.yaml`
Expected: `YAML OK`

- [ ] **Step 5: Commit**

```bash
git add content/collections/projects.yaml content/collections/projects/.gitkeep resources/blueprints/collections/projects/projects.yaml
git commit -m "feat: add projects collection"
```

---

## Task 8: `quicklinks` collection (data-only)

**Files:**
- Create: `content/collections/quicklinks.yaml`
- Create: `content/collections/quicklinks/.gitkeep`
- Create: `resources/blueprints/collections/quicklinks/quicklinks.yaml`

**Interfaces:**
- Consumes: `image`, `link` fieldsets.
- Produces: collection handle `quicklinks` (no route), referenced by the `contact` page blueprint's `quicklinks` entries field. Exposes handles `title`, `text`, `image`, `link`, `link_style`.

- [ ] **Step 1: Create the collection config**

`content/collections/quicklinks.yaml`:
```yaml
title: Quicklinks
```

- [ ] **Step 2: Create the entries directory placeholder**

```bash
mkdir -p content/collections/quicklinks && touch content/collections/quicklinks/.gitkeep
```

- [ ] **Step 3: Create the blueprint**

`resources/blueprints/collections/quicklinks/quicklinks.yaml` (append `SLUG_ONLY_SIDEBAR` verbatim where marked):
```yaml
title: Quicklink
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
          -
            handle: text
            field:
              type: textarea
              display: Text
          -
            import: image
          -
            import: link
          -
            handle: link_style
            field:
              type: select
              display: 'Link Style'
              default: primary
              options:
                primary: Primary
                outline: Outline
# <<< paste SLUG_ONLY_SIDEBAR here (sidebar) >>>
```

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `content/collections/quicklinks.yaml resources/blueprints/collections/quicklinks/quicklinks.yaml`
Expected: `YAML OK`

- [ ] **Step 5: Commit**

```bash
git add content/collections/quicklinks.yaml content/collections/quicklinks/.gitkeep resources/blueprints/collections/quicklinks/quicklinks.yaml
git commit -m "feat: add quicklinks collection"
```

---

## Task 9: `locations` collection (data-only)

**Files:**
- Create: `content/collections/locations.yaml`
- Create: `content/collections/locations/.gitkeep`
- Create: `resources/blueprints/collections/locations/locations.yaml`

**Interfaces:**
- Produces: collection handle `locations` (no route). Uses `name` as the title-substitute via `title_format`. Exposes handles `name`, `street`, `number`, `postal_code`, `city`, `opening_hours` (grid of `day`/`time`).

- [ ] **Step 1: Create the collection config**

`content/collections/locations.yaml`:
```yaml
title: Locations
title_format: '{{ name }}'
```

- [ ] **Step 2: Create the entries directory placeholder**

```bash
mkdir -p content/collections/locations && touch content/collections/locations/.gitkeep
```

- [ ] **Step 3: Create the blueprint**

`resources/blueprints/collections/locations/locations.yaml` (append `SLUG_ONLY_SIDEBAR` verbatim where marked):
```yaml
title: Location
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: name
            field:
              type: text
              display: Name
              required: true
              validate:
                - required
          -
            handle: street
            field:
              type: text
              display: Street
          -
            handle: number
            field:
              type: text
              display: Number
          -
            handle: postal_code
            field:
              type: text
              display: 'Postal Code'
          -
            handle: city
            field:
              type: text
              display: City
          -
            handle: opening_hours
            field:
              type: grid
              mode: table
              display: 'Opening Hours'
              add_row: 'Add Row'
              fields:
                -
                  handle: day
                  field:
                    type: text
                    display: Day
                -
                  handle: time
                  field:
                    type: text
                    display: Time
# <<< paste SLUG_ONLY_SIDEBAR here (sidebar) >>>
```

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `content/collections/locations.yaml resources/blueprints/collections/locations/locations.yaml`
Expected: `YAML OK`

- [ ] **Step 5: Commit**

```bash
git add content/collections/locations.yaml content/collections/locations/.gitkeep resources/blueprints/collections/locations/locations.yaml
git commit -m "feat: add locations collection"
```

---

## Task 10: `home` page blueprint

**Files:**
- Modify (full replace): `resources/blueprints/collections/pages/home.yaml`

**Interfaces:**
- Consumes: `link`, `image`, `icon`, `page_builder`, `template`, `preview`, `seo`.
- Produces: `home` blueprint with hero (`title`, `text`, `link`, `image`), `value_proposition` group (`title` + replicator of `icon`/`title`/`text`), and `page_builder`.

- [ ] **Step 1: Replace the file contents**

`resources/blueprints/collections/pages/home.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
hide: true
title: Home
tabs:
  main:
    display: Main
    sections:
      -
        display: Hero
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
          -
            handle: text
            field:
              type: textarea
              display: Text
          -
            import: link
          -
            import: image
      -
        display: 'Value Proposition'
        fields:
          -
            handle: value_proposition
            field:
              type: group
              display: 'Value Proposition'
              fields:
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: value_proposition
                  field:
                    type: replicator
                    display: 'Value Propositions'
                    collapse: accordion
                    button_label: 'Add Value Proposition'
                    sets:
                      new_set_group:
                        display: 'New Set Group'
                        sets:
                          value_proposition:
                            display: 'Value Proposition'
                            fields:
                              -
                                import: icon
                              -
                                handle: title
                                field:
                                  type: text
                                  display: Title
                              -
                                handle: text
                                field:
                                  type: textarea
                                  display: Text
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 2: Verify YAML**

Run `YAMLCHECK` with: `resources/blueprints/collections/pages/home.yaml`
Expected: `YAML OK`

- [ ] **Step 3: Commit**

```bash
git add resources/blueprints/collections/pages/home.yaml
git commit -m "feat: rebuild home page blueprint"
```

---

## Task 11: `page` blueprint (Over ons / generic)

**Files:**
- Modify (full replace): `resources/blueprints/collections/pages/page.yaml`

**Interfaces:**
- Consumes: `page_intro`, `page_builder`, `template`, `preview`, `seo`.
- Produces: `page` blueprint = `page_intro` + `page_builder`.

- [ ] **Step 1: Replace the file contents**

`resources/blueprints/collections/pages/page.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
title: Page
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 2: Verify YAML**

Run `YAMLCHECK` with: `resources/blueprints/collections/pages/page.yaml`
Expected: `YAML OK`

- [ ] **Step 3: Commit**

```bash
git add resources/blueprints/collections/pages/page.yaml
git commit -m "feat: rebuild page blueprint as page_intro + page_builder"
```

---

## Task 12: `range_overview`, `projects_overview`, `invoice` blueprints

These three are structurally identical to `page` (page_intro + page_builder + standard tabs); only `title` differs. Full YAML is given for each — do not abbreviate.

**Files:**
- Create: `resources/blueprints/collections/pages/range_overview.yaml`
- Create: `resources/blueprints/collections/pages/projects_overview.yaml`
- Create: `resources/blueprints/collections/pages/invoice.yaml`

**Interfaces:**
- Consumes: `page_intro`, `page_builder`, `template`, `preview`, `seo`.
- Produces: blueprints `range_overview` (Aanbod), `projects_overview` (Realisaties), `invoice` (Offerte).

- [ ] **Step 1: Create `range_overview.yaml`**

`resources/blueprints/collections/pages/range_overview.yaml` (append `STANDARD_TABS`):
```yaml
title: 'Range Overview'
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 2: Create `projects_overview.yaml`**

`resources/blueprints/collections/pages/projects_overview.yaml` (append `STANDARD_TABS`):
```yaml
title: 'Projects Overview'
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 3: Create `invoice.yaml`**

`resources/blueprints/collections/pages/invoice.yaml` (append `STANDARD_TABS`):
```yaml
title: Invoice
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `resources/blueprints/collections/pages/range_overview.yaml resources/blueprints/collections/pages/projects_overview.yaml resources/blueprints/collections/pages/invoice.yaml`
Expected: `YAML OK`

- [ ] **Step 5: Commit**

```bash
git add resources/blueprints/collections/pages/range_overview.yaml resources/blueprints/collections/pages/projects_overview.yaml resources/blueprints/collections/pages/invoice.yaml
git commit -m "feat: add range_overview, projects_overview, invoice page blueprints"
```

---

## Task 13: `services_overview` blueprint

**Files:**
- Create: `resources/blueprints/collections/pages/services_overview.yaml`

**Interfaces:**
- Consumes: `page_intro`, `image`, `template`, `preview`, `seo`.
- Produces: `services_overview` blueprint with `page_intro`, a `services` replicator (`overline`, `title`, `text` bard, `image`), and a `reparation` group (`overline`, `title`, `text`). No `page_builder` (per spec).

- [ ] **Step 1: Create the blueprint**

`resources/blueprints/collections/pages/services_overview.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
title: 'Services Overview'
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        display: Services
        fields:
          -
            handle: services
            field:
              type: replicator
              display: Services
              collapse: accordion
              button_label: 'Add Service'
              sets:
                new_set_group:
                  display: 'New Set Group'
                  sets:
                    service:
                      display: Service
                      fields:
                        -
                          handle: overline
                          field:
                            type: text
                            display: Overline
                        -
                          handle: title
                          field:
                            type: text
                            display: Title
                        -
                          handle: text
                          field:
                            type: bard
                            display: Text
                            buttons:
                              - bold
                              - italic
                              - anchor
                            container: assets
                            remove_empty_nodes: false
                        -
                          import: image
      -
        display: Reparation
        fields:
          -
            handle: reparation
            field:
              type: group
              display: Reparation
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  handle: text
                  field:
                    type: textarea
                    display: Text
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 2: Verify YAML**

Run `YAMLCHECK` with: `resources/blueprints/collections/pages/services_overview.yaml`
Expected: `YAML OK`

- [ ] **Step 3: Commit**

```bash
git add resources/blueprints/collections/pages/services_overview.yaml
git commit -m "feat: add services_overview page blueprint"
```

---

## Task 14: `contact` blueprint

**Files:**
- Create: `resources/blueprints/collections/pages/contact.yaml`

**Interfaces:**
- Consumes: `page_intro`, `page_builder`, `template`, `preview`, `seo`; collection `quicklinks` (via `quicklinks` entries field).
- Produces: `contact` blueprint with `page_intro`, `quicklinks` (entries → quicklinks, multiple), `page_builder`.

- [ ] **Step 1: Create the blueprint**

`resources/blueprints/collections/pages/contact.yaml` (append `STANDARD_TABS` verbatim where marked):
```yaml
title: Contact
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            handle: quicklinks
            field:
              type: entries
              collections:
                - quicklinks
              display: Quicklinks
      -
        fields:
          -
            import: page_builder
# <<< paste STANDARD_TABS here (preview, seo, sidebar) >>>
```

- [ ] **Step 2: Verify YAML**

Run `YAMLCHECK` with: `resources/blueprints/collections/pages/contact.yaml`
Expected: `YAML OK`

- [ ] **Step 3: Commit**

```bash
git add resources/blueprints/collections/pages/contact.yaml
git commit -m "feat: add contact page blueprint"
```

---

## Task 15: Extend `general_information` global with `mobile`

**Files:**
- Modify: `resources/blueprints/globals/globals.yaml` (add `mobile` key to `contact`)
- Modify: `content/globals/globals.yaml` (add `mobile: null` under `contact`)
- Modify (if it contains a `contact` block): `content/globals/default/globals.yaml`

**Interfaces:**
- Produces: the existing "General Information" global with `contact` now holding `mobile`, `phone`, `email` (company + socials retained).

- [ ] **Step 1: Add `mobile` to the blueprint's `contact` keys**

In `resources/blueprints/globals/globals.yaml`, the `contact` field's `keys:` list currently is:
```yaml
              keys:
                - key: phone
                  value: null
                - key: email
                  value: null
```
Change it to:
```yaml
              keys:
                - key: mobile
                  value: null
                - key: phone
                  value: null
                - key: email
                  value: null
```

- [ ] **Step 2: Add `mobile` to the stored value in `content/globals/globals.yaml`**

The `contact:` block currently reads:
```yaml
contact:
  phone: null
  email: null
```
Change it to:
```yaml
contact:
  mobile: null
  phone: null
  email: null
```

- [ ] **Step 3: Mirror in the site value file if present**

Run: `grep -n "contact" content/globals/default/globals.yaml`
- If a `contact:` block is present there, apply the identical change from Step 2 to `content/globals/default/globals.yaml`.
- If the file has no `contact:` block (or does not exist), skip — nothing to change.

- [ ] **Step 4: Verify YAML**

Run `YAMLCHECK` with: `resources/blueprints/globals/globals.yaml content/globals/globals.yaml`
Expected: `YAML OK`
Then confirm the key landed: `python3 -c "import yaml; print(list(yaml.safe_load(open('content/globals/globals.yaml'))['contact']))"`
Expected: `['mobile', 'phone', 'email']`

- [ ] **Step 5: Commit**

```bash
git add resources/blueprints/globals/globals.yaml content/globals/globals.yaml content/globals/default/globals.yaml
git commit -m "feat: add mobile to general_information global"
```

---

## Task 16: Full integration verification (requires dependencies)

This task boots Statamic to confirm the CMS parses every blueprint/collection/taxonomy/global and resolves all `import:` and `collections:`/`taxonomies:` references. It needs Composer dependencies and an app key, which are **not** installed in the current environment — run it once the environment is provisioned.

**Files:** none (verification only).

**Interfaces:**
- Consumes: everything built in Tasks 1–15.

- [ ] **Step 1: Install dependencies and app key (if not already present)**

```bash
composer install
cp -n .env.example .env
php please key:generate
```
Expected: `vendor/` present, `.env` exists with an `APP_KEY`.

- [ ] **Step 2: Clear the Stache and boot**

Run: `php please stache:clear`
Expected: `Stache cleared` with no YAML/blueprint parse errors.

- [ ] **Step 3: Assert all collections are registered**

Run: `php please tinker --execute "echo \Statamic\Facades\Collection::all()->map->handle()->sort()->values()->implode(', ');"`
Expected output includes: `articles, legal, locations, pages, products, projects, quicklinks, ranges` (no `cases`).

- [ ] **Step 4: Assert the taxonomy and blueprints resolve**

Run: `php please tinker --execute "echo \Statamic\Facades\Taxonomy::findByHandle('range_categories')?->title(); echo PHP_EOL; \Statamic\Facades\Blueprint::in('collections/pages')->each(fn(\$b)=>print(\$b->handle().PHP_EOL));"`
Expected: prints `Range Categories`, then the page blueprint handles: `home, page, range_overview, projects_overview, services_overview, contact, invoice`.

- [ ] **Step 5: Load the Control Panel manually**

Start the app (`php please serve`), log in, and open **Fields → Blueprints** and each new collection. Confirm the `page_builder` shows all 11 sets and imported `link`/`icon`/`image` fields render. No commit (verification only).

---

## Self-Review

**1. Spec coverage** — every spec section maps to a task:
- Atomic fieldset reuse → Global Constraints + used throughout.
- `page_intro` → Task 2. `page_builder` (11 sets) → Task 3.
- Taxonomy `range_categories` → Task 4.
- Collections `ranges`/`products`/`projects`/`quicklinks`/`locations` → Tasks 5–9.
- Pages `home`/`page`/`range_overview`/`projects_overview`/`services_overview`/`contact`/`invoice` → Tasks 10–14.
- Global `general_information` (add `mobile`) → Task 15.
- Removals (cases, testing, cta/service_nav, old project blueprint) → Task 1.
- Integration verification → Task 16.

**2. Placeholder scan** — no "TBD"/"add error handling"/"similar to Task N" left; the two factored snippets (`STANDARD_TABS`, `SLUG_ONLY_SIDEBAR`) are given in full at the top and referenced by exact name, and every field body is written out.

**3. Type/handle consistency** — collection handles referenced in `page_builder` (`ranges`, `products`, `projects`) match the collections created in Tasks 5–7; `quicklinks` referenced in `contact` (Task 14) matches Task 8; `products` referenced by `projects.product` (Task 7) matches Task 6; `range_categories` referenced by `ranges.range_category` (Task 5) matches Task 4; the `contact` array key added in Task 15 is `mobile` in both blueprint and value file.
