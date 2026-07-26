# Overzichtspagina's `/aanbod` en `/realisaties` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Twee overzichtspagina's bouwen — `/aanbod` (ranges gegroepeerd per range-categorie) en `/realisaties` (projecten met een client-side rangefilter) — zonder kaartmarkup of kaart-CSS te dupliceren.

**Architecture:** De range- en projectkaart worden uit hun page-builder-secties gelicht naar losse partials, zodat de bestaande sliders en de nieuwe grids dezelfde bron delen. `projects` ruilt zijn `product`-relatie voor een `range`-relatie, waardoor het filter één hop nodig heeft. Het filter rendert alle projecten server-side en stuurt zichtbaarheid via het `hidden`-attribuut aan; Alpine neemt datzelfde attribuut over via `:hidden`, zodat server en client identiek gedrag vertonen en er geen animatie in het spel is.

**Tech Stack:** Statamic 6 (Antlers), Laravel, Tailwind CSS 4, Alpine.js 3, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-26-overview-pages-design.md`

## Global Constraints

- **Bestaande pagina's mogen niet veranderen, op één afgesproken uitzondering na.** Elke wijziging aan een gedeeld partial is additief, via een argument met een default die het huidige gedrag exact bewaart. De uitzondering is de `product` → `range`-omzetting uit Taak 3 en 5: die is bewust doorgevoerd en verandert het categorielabel op de projectkaart, ook in de `projects`-sectie van de page builder. Dat is geen defect.
- **Geen animatie in het filter.** Zichtbaarheid is uitsluitend het `hidden`-attribuut, nooit een transition of `x-transition`.
- **Geen swiper op de overzichtspagina's.** Kaarten stapelen op mobile via een grid met één kolom, niet via een uitgezette slider.
- **Tailwind-klassen altijd letterlijk uitschrijven.** Nooit een breakpoint of variant in een klassenaam interpoleren — Tailwind's scanner leest alleen letterlijke tekst uit de bronbestanden. Dit is elders in dit project al een gedocumenteerde valkuil (zie de comment-blokken in `sectionHeader.antlers.html`).
- **Antlers-toewijzingen (`{{ x = ... }}`) schrijven in de gedeelde render-cascade**, niet in een partial-lokale scope. Nooit een variabele hertoewijzen binnen een partial dat meermaals per pagina gerenderd wordt.
- **De volle PHPUnit-suite loopt niet op een `memory_limit` van 128 MB** — `intervention/image` in `AssetUploadCompressionTest` blaast op. Draai per taak met `--filter`, nooit de kale suite.
- **Testcommando:** `php artisan test --filter <TestClass>` vanuit de projectroot.
- **Branch:** `overview-pages` staat al uitgechecked met de spec erop gecommit.

## File Structure

**Nieuw:**

| Bestand | Verantwoordelijkheid |
|---|---|
| `resources/views/partials/rangeCard.antlers.html` | De range-kaart als link. Eén bron voor slider én grid. |
| `resources/views/partials/projectCard.antlers.html` | De project-kaart als link, met de range als categorielabel. |
| `resources/views/partials/rangeFilter.antlers.html` | De filterpillen op `/realisaties`. Kent de projectgrid niet. |
| `app/Tags/ProjectRanges.php` | Antlers-tag die de ranges met minstens één project levert. |
| `resources/views/range-overview.antlers.html` | Template van `/aanbod`. |
| `resources/views/projects-overview.antlers.html` | Template van `/realisaties`. |
| `resources/css/components/range-filter.css` | Pilvorm, actieve/inactieve staat, mobiele scrollrij. |
| `resources/js/components/project-filter.js` | Alpine-component: actieve range plus URL-synchronisatie. |
| `content/collections/pages/aanbod.md` | Page-entry voor `/aanbod`. |
| `content/collections/pages/realisaties.md` | Page-entry voor `/realisaties`. |
| `tests/Feature/Content/RangeCategoriesContentTest.php` | Taxonomie-indeling en volgorde. |
| `tests/Feature/Content/RangeOverviewPageTest.php` | `/aanbod` end-to-end. |
| `tests/Feature/Content/ProjectsOverviewPageTest.php` | `/realisaties` end-to-end, inclusief `?range=`. |
| `tests/Feature/Sections/RangeCardTest.php` | Het uitgetrokken range-kaartpartial. |
| `tests/Feature/Sections/ProjectCardTest.php` | Het uitgetrokken project-kaartpartial. |
| `tests/Feature/Sections/ProjectRangesTagTest.php` | De `project_ranges`-tag. |
| `tests/Feature/Sections/RangeFilterTest.php` | Het filterpartial. |

**Gewijzigd:**

| Bestand | Wijziging |
|---|---|
| `resources/blueprints/taxonomies/range_categories/range_categories.yaml` | Veld `order` erbij. |
| `content/taxonomies/range_categories/*.yaml` | Drie termen hernoemd, `order` gezet. |
| `content/collections/ranges/*.md` (9) | Nieuwe `range_category`, twee titels bijgesteld. |
| `resources/blueprints/collections/projects/projects.yaml` | `product` eruit, `range` erin. |
| `content/collections/projects/*.md` (6) | `product` → `range`. |
| `resources/views/partials/sections/ranges.antlers.html` | Kaartmarkup vervangen door partial-aanroep. |
| `resources/views/partials/sections/projects.antlers.html` | Idem, plus `product` → `range` in het label. |
| `resources/views/partials/headers/default.antlers.html` | Argument `divider`. |
| `resources/css/site.css` | Import van `range-filter.css`. |
| `resources/js/site.js` | Registratie van `projectFilter`. |
| `lang/nl/site.php` | Sleutel `filter_all`. |
| `content/trees/collections/pages.yaml` | Twee entry-id's erbij. |
| `tests/Feature/Content/RangesContentTest.php` | Verwachte categorietitels en range-titels. |
| `tests/Feature/Content/CatalogContentTest.php` | `product`-assertie wordt `range`. |
| `tests/Feature/Sections/ProjectsSectionTest.php` | Categorielabel komt uit `range`. |
| `tests/Feature/Sections/PageHeaderTest.php` | Cases voor `divider`. |

## Afwijkingen van de spec

Twee dingen die tijdens de voorbereiding zijn scherpgesteld. Beide maken het werk kleiner, niet groter:

1. **Geen `sizes`-argument op de kaartpartials.** De spec stelde er een voor. Overbodig gebleken: de beeldkolom van de range-kaart is een vaste `w-24 lg:w-32`, dus `sizes="128px"` klopt in slider én grid; en de projectkaart is in beide gevallen ongeveer een derde van de viewport, waardoor `(min-width: 1024px) 33vw, 90vw` blijft staan. De partials krijgen dus géén argumenten — echt één component.
2. **Paginatests staan in `tests/Feature/Content/`**, niet in een nieuwe map `tests/Feature/Pages/`. Dat volgt `PageBuilderPageTest.php`, dat daar al staat en op dezelfde manier een echte URL ophaalt.

De CTA-variant op `/realisaties` (donker paneel, rechts uitgelijnd) valt buiten dit plan: die variatie hoort bij de page builder en is daar al afgehandeld. Deze pagina's renderen simpelweg `{{ partial:pageBuilder }}`.

---

### Task 1: Range-categorieën hernoemen, ordenen en herindelen

**Files:**
- Modify: `resources/blueprints/taxonomies/range_categories/range_categories.yaml`
- Modify: `content/taxonomies/range_categories/buitenzonwering.yaml` → hernoemen naar `rondom-je-woning.yaml`
- Modify: `content/taxonomies/range_categories/schrijnwerk.yaml` → hernoemen naar `voor-je-woning.yaml`
- Modify: `content/taxonomies/range_categories/comfort-en-techniek.yaml` → hernoemen naar `slim-en-comfort.yaml`
- Modify: alle negen bestanden in `content/collections/ranges/`
- Modify: `tests/Feature/Content/RangesContentTest.php:26-58`
- Test: `tests/Feature/Content/RangeCategoriesContentTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: drie termen met de slugs `voor-je-woning`, `rondom-je-woning`, `slim-en-comfort` en integer-veld `order` met respectievelijk 1, 2 en 3. Taak 7 leest die volgorde via `sort="order:asc"`.

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Content/RangeCategoriesContentTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Tests\TestCase;

class RangeCategoriesContentTest extends TestCase
{
    public function test_the_three_categories_exist_in_the_designed_order(): void
    {
        $expected = [
            'voor-je-woning' => ['Voor je woning', 1],
            'rondom-je-woning' => ['Rondom je woning', 2],
            'slim-en-comfort' => ['Slim & comfort', 3],
        ];

        foreach ($expected as $slug => [$title, $order]) {
            $term = Term::query()->where('taxonomy', 'range_categories')->where('slug', $slug)->first();

            $this->assertNotNull($term, "Range-categorie {$slug} ontbreekt");
            $this->assertSame($title, $term->value('title'), "Titel van {$slug} klopt niet");
            $this->assertSame($order, (int) $term->value('order'), "Volgorde van {$slug} klopt niet");
        }

        $all = Term::query()->where('taxonomy', 'range_categories')->get();

        $this->assertCount(3, $all, 'Er horen precies drie range-categorieën te zijn');
    }

    public function test_every_range_sits_in_its_designed_category(): void
    {
        $expected = [
            'ramen-en-deuren' => 'voor-je-woning',
            'stalen-binnendeuren' => 'voor-je-woning',
            'velux' => 'voor-je-woning',
            'airco' => 'voor-je-woning',
            'rolluiken' => 'rondom-je-woning',
            'zonwering' => 'rondom-je-woning',
            'pergolas' => 'rondom-je-woning',
            'garagepoorten' => 'rondom-je-woning',
            'somfy-smart-home' => 'slim-en-comfort',
        ];

        foreach ($expected as $rangeSlug => $categorySlug) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $rangeSlug)->first();

            $this->assertNotNull($entry, "Range {$rangeSlug} ontbreekt");

            $terms = $entry->augmentedValue('range_category')->value()->get();

            $this->assertCount(1, $terms, "Range {$rangeSlug} hoort in precies één categorie te zitten");
            $this->assertSame($categorySlug, $terms->first()->slug(), "Range {$rangeSlug} zit in de verkeerde categorie");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter RangeCategoriesContentTest`
Expected: FAIL — `Range-categorie voor-je-woning ontbreekt`.

- [ ] **Step 3: Add the `order` field to the taxonomy blueprint**

Vervang `resources/blueprints/taxonomies/range_categories/range_categories.yaml` volledig door:

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
          -
            handle: order
            field:
              type: integer
              display: Volgorde
              instructions: 'Bepaalt de volgorde op de aanbod-pagina. Laag getal komt eerst.'
              required: true
              validate:
                - required
```

- [ ] **Step 4: Rename the three term files**

De `id` van elke term blijft ongewijzigd — de ranges verwijzen naar de slug, maar het id hergebruiken houdt de Statamic-stash consistent.

```bash
git mv content/taxonomies/range_categories/schrijnwerk.yaml content/taxonomies/range_categories/voor-je-woning.yaml
git mv content/taxonomies/range_categories/buitenzonwering.yaml content/taxonomies/range_categories/rondom-je-woning.yaml
git mv content/taxonomies/range_categories/comfort-en-techniek.yaml content/taxonomies/range_categories/slim-en-comfort.yaml
```

Schrijf daarna de drie bestanden volledig:

`content/taxonomies/range_categories/voor-je-woning.yaml`
```yaml
---
id: 5f1a0d64-6b34-4f2e-9e5c-1a2b3c4d5e02
title: 'Voor je woning'
order: 1
---
```

`content/taxonomies/range_categories/rondom-je-woning.yaml`
```yaml
---
id: 5f1a0d64-6b34-4f2e-9e5c-1a2b3c4d5e01
title: 'Rondom je woning'
order: 2
---
```

`content/taxonomies/range_categories/slim-en-comfort.yaml`
```yaml
---
id: 5f1a0d64-6b34-4f2e-9e5c-1a2b3c4d5e03
title: 'Slim & comfort'
order: 3
---
```

- [ ] **Step 5: Reassign all nine ranges**

Zet in elk bestand de `range_category`-lijst om. De rest van de front matter blijft ongemoeid.

| Bestand | `range_category` |
|---|---|
| `content/collections/ranges/ramen-en-deuren.md` | `- voor-je-woning` |
| `content/collections/ranges/stalen-binnendeuren.md` | `- voor-je-woning` |
| `content/collections/ranges/velux.md` | `- voor-je-woning` |
| `content/collections/ranges/airco.md` | `- voor-je-woning` |
| `content/collections/ranges/rolluiken.md` | `- rondom-je-woning` |
| `content/collections/ranges/zonwering.md` | `- rondom-je-woning` |
| `content/collections/ranges/pergolas.md` | `- rondom-je-woning` |
| `content/collections/ranges/garagepoorten.md` | `- rondom-je-woning` |
| `content/collections/ranges/somfy-smart-home.md` | `- slim-en-comfort` |

Concreet ziet dat er in elk bestand zo uit:

```yaml
range_category:
  - rondom-je-woning
```

- [ ] **Step 6: Update the expectations in RangesContentTest**

In `tests/Feature/Content/RangesContentTest.php` staat `test_every_range_category_relation_resolves_to_a_real_term` met de oude titels hardcoded. Vervang de `$expectedCategoryTitles`-array door:

```php
        $expectedCategoryTitles = [
            'pergolas' => 'Rondom je woning',
            'ramen-en-deuren' => 'Voor je woning',
            'rolluiken' => 'Rondom je woning',
            'zonwering' => 'Rondom je woning',
            'garagepoorten' => 'Rondom je woning',
            'velux' => 'Voor je woning',
            'airco' => 'Voor je woning',
            'somfy-smart-home' => 'Slim & comfort',
            'stalen-binnendeuren' => 'Voor je woning',
        ];
```

- [ ] **Step 7: Clear the Statamic stache and run both tests**

Run:
```bash
php please stache:clear
php artisan test --filter RangeCategoriesContentTest
php artisan test --filter RangesContentTest
```
Expected: beide PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/blueprints/taxonomies content/taxonomies content/collections/ranges tests/Feature/Content/RangeCategoriesContentTest.php tests/Feature/Content/RangesContentTest.php
git commit -m "feat(content): regroup ranges into the three designed categories"
```

---

### Task 2: Twee range-titels uitlijnen op Figma

**Files:**
- Modify: `content/collections/ranges/pergolas.md`
- Modify: `content/collections/ranges/velux.md`
- Test: `tests/Feature/Content/RangesContentTest.php`

**Interfaces:**
- Consumes: de ranges uit Taak 1.
- Produces: `pergolas` heet "Terrasoverkappingen & pergola's", `velux` heet "VELUX dakramen". Taak 8 rendert die titels letterlijk als filterknoppen.

- [ ] **Step 1: Write the failing test**

Voeg deze methode toe aan `tests/Feature/Content/RangesContentTest.php`:

```php
    public function test_range_titles_match_the_design(): void
    {
        $expectedTitles = [
            'pergolas' => "Terrasoverkappingen & pergola's",
            'velux' => 'VELUX dakramen',
        ];

        foreach ($expectedTitles as $slug => $title) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Range {$slug} ontbreekt");
            $this->assertSame($title, $entry->get('title'), "Titel van {$slug} wijkt af van het ontwerp");
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter RangesContentTest`
Expected: FAIL — `Titel van pergolas wijkt af van het ontwerp`, verwacht `Terrasoverkappingen & pergola's`, gekregen `Pergola's`.

- [ ] **Step 3: Update the two titles**

In `content/collections/ranges/pergolas.md`:
```yaml
title: "Terrasoverkappingen & pergola's"
```

In `content/collections/ranges/velux.md`:
```yaml
title: 'VELUX dakramen'
```

De slugs, id's, beelden en beschrijvingen blijven ongewijzigd.

- [ ] **Step 4: Run tests to verify they pass**

Run:
```bash
php please stache:clear
php artisan test --filter RangesContentTest
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add content/collections/ranges tests/Feature/Content/RangesContentTest.php
git commit -m "feat(content): align two range titles with the design"
```

---

### Task 3: `projects` ruilt `product` voor `range`

**Files:**
- Modify: `resources/blueprints/collections/projects/projects.yaml:16-24`
- Modify: alle zes bestanden in `content/collections/projects/`
- Modify: `tests/Feature/Content/CatalogContentTest.php:21-35`

**Interfaces:**
- Consumes: de ranges uit Taak 1 en 2.
- Produces: elk project heeft een veld `range` dat augmenteert naar één `ranges`-entry. Taak 5 leest `range.title`, Taak 10 leest `range.slug`.

- [ ] **Step 1: Write the failing test**

Vervang in `tests/Feature/Content/CatalogContentTest.php` de methode `test_six_projects_exist_and_reference_a_product` volledig door:

```php
    public function test_six_projects_exist_and_reference_a_range(): void
    {
        $projects = Entry::query()->where('collection', 'projects')->get();

        $this->assertCount(6, $projects);

        foreach ($projects as $project) {
            $this->assertNotEmpty($project->get('image'), "Project {$project->slug()} heeft geen beeld");
            $this->assertNotEmpty($project->get('range'), "Project {$project->slug()} verwijst niet naar een range");

            $relatedRange = $project->augmentedValue('range')->value();

            $this->assertNotNull($relatedRange, "Project {$project->slug()} zijn range-relatie augmenteert niet naar een entry");
            $this->assertSame('ranges', $relatedRange->collectionHandle(), "Project {$project->slug()} verwijst niet naar de ranges-collectie");
            $this->assertNotEmpty($relatedRange->get('title'), "De range van project {$project->slug()} heeft geen titel");
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter CatalogContentTest`
Expected: FAIL — `Project pergola-so-met-glazen-schuifwanden verwijst niet naar een range`.

- [ ] **Step 3: Swap the field in the blueprint**

In `resources/blueprints/collections/projects/projects.yaml` vervang je het `product`-veld:

```yaml
          -
            handle: product
            field:
              type: entries
              collections:
                - products
              max_items: 1
              display: Product
```

door:

```yaml
          -
            handle: range
            field:
              type: entries
              collections:
                - ranges
              max_items: 1
              display: Range
              required: true
              validate:
                - required
```

- [ ] **Step 4: Rewrite the six project entries**

Vervang in elk bestand de regel `product: <uuid>` door een `range:` met het **id** van de range hieronder. De rest van de front matter blijft staan.

| Bestand | `range:` |
|---|---|
| `content/collections/projects/pergola-so-met-glazen-schuifwanden.md` | `pergolas` |
| `content/collections/projects/veranda-met-schuifdeuren.md` | `pergolas` |
| `content/collections/projects/carport-in-hout-en-aluminium.md` | `pergolas` |
| `content/collections/projects/zip-screens-op-nieuwbouwwoning.md` | `zonwering` |
| `content/collections/projects/ramen-en-voordeur-in-aluminium.md` | `ramen-en-deuren` |
| `content/collections/projects/rolluiken-op-rijwoning.md` | `rolluiken` |

Voor `pergola-so-met-glazen-schuifwanden.md` betekent dat:

```yaml
---
id: b7d4e2c3-0001-4f5a-9b8c-6d7e8f9a0b01
title: 'Pergola SO! met glazen schuifwanden'
text: 'Een aangebouwde pergola met draaibare lamellen, glazen schuifwanden en zip-screens. Geïntegreerde ledverlichting verlengt de avonden tot ver in het seizoen.'
range: 8c2e41a0-0001-4a1b-9c7d-3e5f6a7b8c01
image: dummy-images/test-img-7.jpg
---
```

Gebruik het `id` uit het bijbehorende bestand in `content/collections/ranges/`, niet de slug. Het `entries`-fieldtype resolvet uitsluitend op id — `Entries::toItemArray()` doet `Entry::find($id)` en de query-builder een `whereIn('id', $ids)`, dus een slug laat `augmentedValue('range')` op `null` staan terwijl `get('range')` wél gevuld lijkt. Dat is ook al de conventie elders in deze content (`author`, `updated_by`, `duplicated_from`).

- [ ] **Step 5: Run test to verify it passes**

Run:
```bash
php please stache:clear
php artisan test --filter CatalogContentTest
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/blueprints/collections/projects content/collections/projects tests/Feature/Content/CatalogContentTest.php
git commit -m "feat(content): relate projects to a range instead of a product"
```

---

### Task 4: Range-kaart naar een eigen partial

**Files:**
- Create: `resources/views/partials/rangeCard.antlers.html`
- Modify: `resources/views/partials/sections/ranges.antlers.html:30-51`
- Test: `tests/Feature/Sections/RangeCardTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: `{{ partial:rangeCard }}` rendert één `<a class="range-card">` uit de velden `url`, `title`, `short_description` en `image` in de huidige scope. Taak 7 roept het aan binnen een grid-cel.

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Sections/RangeCardTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class RangeCardTest extends SectionTestCase
{
    public function test_renders_a_full_card_link_with_its_copy(): void
    {
        $html = $this->render('{{ partial src="rangeCard" }}', [
            'title' => 'Rolluiken',
            'short_description' => 'Verduistering, isolatie en inbraakwering.',
            'url' => '/aanbod/rolluiken',
        ]);

        $this->assertStringContainsString('class="range-card', $html);
        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
        $this->assertStringContainsString('<h3>Rolluiken</h3>', $html);
        $this->assertStringContainsString('Verduistering, isolatie en inbraakwering.', $html);
    }

    public function test_omits_the_description_paragraph_when_there_is_none(): void
    {
        $html = $this->render('{{ partial src="rangeCard" }}', [
            'title' => 'Airco',
            'url' => '/aanbod/airco',
        ]);

        $this->assertStringContainsString('<h3>Airco</h3>', $html);
        $this->assertStringNotContainsString('<p>', $html);
    }

    public function test_carries_no_slider_markup_of_its_own(): void
    {
        $html = $this->render('{{ partial src="rangeCard" }}', [
            'title' => 'Zonwering',
            'url' => '/aanbod/zonwering',
        ]);

        $this->assertStringNotContainsString('swiper-slide', $html);
        $this->assertStringNotContainsString('data-slider', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter RangeCardTest`
Expected: FAIL — de view `partials.rangeCard` bestaat niet.

- [ ] **Step 3: Create the partial**

Maak `resources/views/partials/rangeCard.antlers.html` met exact de markup die nu in `sections/ranges.antlers.html` binnen de `swiper-slide` staat:

```antlers
{{#
    Eén bron voor de range-kaart: `sections/ranges` rendert hem binnen een
    `swiper-slide`, `range-overview` binnen een grid-cel. De kaart kent zijn
    eigen context niet en neemt daarom geen argumenten — de beeldkolom is een
    vaste `w-24 lg:w-32`, dus `sizes="128px"` klopt in beide gevallen.
#}}
<a href="{{ url }}" class="range-card relative isolate flex h-full items-center gap-5 overflow-hidden rounded-md bg-light card-padding lg:gap-8">
    {{ svg src="shape" aria-hidden="true" class="pointer-events-none absolute -bottom-10 -left-14 -z-10 h-40 w-40 text-white/70 sm:h-52 sm:w-52 lg:-left-16 lg:-bottom-14 lg:h-64 lg:w-64" }}

    {{ if image }}
        <div class="relative z-10 flex w-24 shrink-0 items-center justify-center lg:w-32">
            {{ img :src="image" max_width="320" sizes="128px" class="h-auto max-h-24 w-full object-contain lg:max-h-32" }}
        </div>
    {{ /if }}

    <div class="relative z-10 flex flex-col gap-2 lg:gap-3">
        <h3>{{ title }}</h3>
        {{ if short_description }}
            <p>{{ short_description }}</p>
        {{ /if }}
    </div>
</a>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter RangeCardTest`
Expected: PASS.

- [ ] **Step 5: Point the ranges section at the partial**

In `resources/views/partials/sections/ranges.antlers.html` vervang je het hele `<a href="{{ url }}" class="range-card …">…</a>`-blok binnen de `swiper-slide` door één regel. Het resultaat:

```antlers
                {{ partial:slider per_view="1.15,lg:2.25" navigation="true" }}
                    {{ range }}
                        <div class="swiper-slide">
                            {{ partial:rangeCard }}
                        </div>
                    {{ /range }}
                {{ /partial:slider }}
```

Het commentaarblok bovenaan het bestand blijft ongewijzigd staan — de redenering over `per_view` en de navigatie gaat over de slider, niet over de kaart.

- [ ] **Step 6: Verify the ranges section still renders identically**

Run:
```bash
php artisan test --filter RangesSectionTest
php artisan test --filter PageBuilderPageTest
```
Expected: beide PASS zonder wijziging aan die tests. `PageBuilderPageTest` telt negen `range-card`-voorkomens en 25 `swiper-slide`-voorkomens; blijven die kloppen, dan is de extractie gedragsneutraal.

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/rangeCard.antlers.html resources/views/partials/sections/ranges.antlers.html tests/Feature/Sections/RangeCardTest.php
git commit -m "refactor(sections): extract the range card into its own partial"
```

---

### Task 5: Project-kaart naar een eigen partial, met de range als label

**Files:**
- Create: `resources/views/partials/projectCard.antlers.html`
- Modify: `resources/views/partials/sections/projects.antlers.html:7-36`
- Modify: `tests/Feature/Sections/ProjectsSectionTest.php`
- Test: `tests/Feature/Sections/ProjectCardTest.php`

**Interfaces:**
- Consumes: het `range`-veld uit Taak 3.
- Produces: `{{ partial:projectCard }}` rendert één `<a class="project-card">` uit `url`, `title`, `image` en `range` in de huidige scope, met `range.title` in `.project-card__category`.

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Sections/ProjectCardTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ProjectCardTest extends SectionTestCase
{
    public function test_renders_a_linked_card_with_the_range_as_category(): void
    {
        $html = $this->render('{{ partial src="projectCard" }}', [
            'title' => 'Zip-screens op nieuwbouwwoning',
            'url' => '/realisaties/zip-screens-op-nieuwbouwwoning',
            'range' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
        ]);

        $this->assertStringContainsString('class="project-card', $html);
        $this->assertStringContainsString('href="/realisaties/zip-screens-op-nieuwbouwwoning"', $html);
        $this->assertStringContainsString('project-card__category', $html);
        $this->assertStringContainsString('Zonwering', $html);
        $this->assertStringContainsString('<h3>Zip-screens op nieuwbouwwoning</h3>', $html);
    }

    public function test_omits_the_category_when_no_range_is_set(): void
    {
        $html = $this->render('{{ partial src="projectCard" }}', [
            'title' => 'Los project',
            'url' => '/realisaties/los-project',
        ]);

        $this->assertStringNotContainsString('project-card__category', $html);
        $this->assertStringContainsString('<h3>Los project</h3>', $html);
    }

    public function test_carries_no_slider_markup_of_its_own(): void
    {
        $html = $this->render('{{ partial src="projectCard" }}', [
            'title' => 'Rolluiken op rijwoning',
            'url' => '/realisaties/rolluiken-op-rijwoning',
        ]);

        $this->assertStringNotContainsString('swiper-slide', $html);
        $this->assertStringNotContainsString('data-slider', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter ProjectCardTest`
Expected: FAIL — de view `partials.projectCard` bestaat niet.

- [ ] **Step 3: Create the partial**

Maak `resources/views/partials/projectCard.antlers.html`. Dit is de markup uit `sections/projects.antlers.html` met `product` vervangen door `range`:

```antlers
{{#
    Eén bron voor de project-kaart: `sections/projects` rendert hem binnen een
    `swiper-slide`, `projects-overview` binnen een grid-cel. Het categorielabel
    leest `range`, niet `product` — de ranges zijn wat het ontwerp op de kaart
    toont en waarop `/realisaties` filtert.
#}}
<a href="{{ url }}" class="project-card group flex flex-col gap-8">
    {{ if image }}
        {{ img :src="image" ratio="1/1" max_width="960" sizes="(min-width: 1024px) 33vw, 90vw" class="rounded-md" }}
    {{ /if }}

    <div class="flex flex-col gap-6 border-b border-black/10 pb-8">
        <div class="flex flex-col gap-3">
            {{ if range }}
                {{ range }}
                    <span class="project-card__category">{{ title }}</span>
                {{ /range }}
            {{ /if }}

            <div class="flex items-end justify-between gap-4">
                <h3>{{ title }}</h3>
                <span aria-hidden="true" class="contents">
                    {{ svg src="icons/regular/arrow-right" class="size-3.5 shrink-0 -rotate-45 transition-transform group-hover:translate-x-1" }}
                </span>
            </div>
        </div>
    </div>
</a>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter ProjectCardTest`
Expected: PASS.

- [ ] **Step 5: Point the projects section at the partial**

In `resources/views/partials/sections/projects.antlers.html` wordt het `{{ projects }}`-blok:

```antlers
                {{ partial:slider per_view="1.15" from="md" bleed="true" }}
                    {{ projects }}
                        <div class="swiper-slide">
                            {{ partial:projectCard }}
                        </div>
                    {{ /projects }}
                {{ /partial:slider }}
```

- [ ] **Step 6: Update ProjectsSectionTest for the range label**

Vervang de testmethode in `tests/Feature/Sections/ProjectsSectionTest.php` door:

```php
    public function test_renders_a_linked_card_per_project(): void
    {
        $html = $this->render('{{ partial src="sections/projects" }}', [
            'title' => 'Recent gerealiseerd',
            'overline' => 'realisaties',
            'projects' => [
                [
                    'title' => 'Pergola SO! met glazen schuifwanden',
                    'url' => '/realisaties/pergola-so',
                    'range' => ['title' => "Terrasoverkappingen & pergola's", 'slug' => 'pergolas'],
                ],
                [
                    'title' => 'Zip-screens op nieuwbouwwoning',
                    'url' => '/realisaties/zip-screens',
                    'range' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-section="projects"', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(2, substr_count($html, 'project-card '));
        $this->assertStringContainsString('href="/realisaties/pergola-so"', $html);
        $this->assertStringContainsString('Zonwering', $html);
    }
```

Let op de spatie in `'project-card '`: zonder die spatie telt `substr_count` ook de twee `project-card__category`-voorkomens mee en wordt het er vier.

- [ ] **Step 7: Run the affected tests**

Run:
```bash
php artisan test --filter ProjectCardTest
php artisan test --filter ProjectsSectionTest
php artisan test --filter PageBuilderPageTest
```
Expected: alle PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/partials/projectCard.antlers.html resources/views/partials/sections/projects.antlers.html tests/Feature/Sections/ProjectCardTest.php tests/Feature/Sections/ProjectsSectionTest.php
git commit -m "refactor(sections): extract the project card and label it by range"
```

---

### Task 6: `divider`-argument op de page header

**Files:**
- Modify: `resources/views/partials/headers/default.antlers.html`
- Modify: `tests/Feature/Sections/PageHeaderTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: `{{ partial:headers/default divider="true" }}` rendert een decoratieve lijn onder de header vanaf `lg`, herkenbaar aan de klasse `page-header__divider`. Zonder het argument verandert er niets. Taak 7 en 10 gebruiken het.

- [ ] **Step 1: Write the failing test**

Voeg deze twee methodes toe aan `tests/Feature/Sections/PageHeaderTest.php`:

```php
    public function test_renders_a_divider_when_asked(): void
    {
        $html = $this->render('{{ partial src="headers/default" divider="true" }}', [
            'title' => 'Ons aanbod',
        ]);

        $this->assertStringContainsString('page-header__divider', $html);
        $this->assertStringContainsString('lg:block', $html);
    }

    public function test_renders_no_divider_by_default(): void
    {
        $html = $this->render('{{ partial src="headers/default" }}', [
            'title' => 'Ons aanbod',
        ]);

        $this->assertStringNotContainsString('page-header__divider', $html);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter PageHeaderTest`
Expected: FAIL op `test_renders_a_divider_when_asked` — `page-header__divider` komt niet voor.

- [ ] **Step 3: Add the argument to the partial**

Vervang `resources/views/partials/headers/default.antlers.html` volledig door:

```antlers
{{#
    `divider="true"` tekent een lijn onder de header, uitsluitend vanaf `lg`.
    In Figma zit die lijn in het Header-component zelf (component 293:3753,
    430px hoog inclusief lijn); instanties die hem niet tonen zijn 360px
    (/over-ons, /contact, /page-builder) en op beide mobile-frames staat
    `Line 4` op hidden. Vandaar: opt-in argument, desktop-only, zodat de acht
    bestaande aanroepers ongewijzigd blijven renderen.
#}}
<section class="section--default bg-white pt-16 pb-16 lg:pb-28">
    <div class="container">
        <div class="section-header-gap max-w-3xl items-center mx-auto text-center">
            <h1>{{ title }}</h1>

            {{ if text }}
                <p class="text-lg">{{ text }}</p>
            {{ /if }}
        </div>

        {{ if divider }}
            <div class="page-header__divider mt-16 hidden border-t border-black/10 lg:block" aria-hidden="true"></div>
        {{ /if }}
    </div>
</section>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter PageHeaderTest`
Expected: PASS, beide nieuwe methodes plus de bestaande.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/headers/default.antlers.html tests/Feature/Sections/PageHeaderTest.php
git commit -m "feat(header): add an opt-in divider below the page header"
```

---

### Task 7: `/aanbod` — entry, template en categorie-grid

**Files:**
- Create: `content/collections/pages/aanbod.md`
- Create: `resources/views/range-overview.antlers.html`
- Modify: `content/trees/collections/pages.yaml`
- Test: `tests/Feature/Content/RangeOverviewPageTest.php`

**Interfaces:**
- Consumes: `rangeCard` (Taak 4), het `divider`-argument (Taak 6), de geordende categorieën (Taak 1).
- Produces: een werkende `/aanbod`. Niets uit latere taken hangt hiervan af.

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Content/RangeOverviewPageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class RangeOverviewPageTest extends TestCase
{
    public function test_the_page_renders_with_its_header_and_divider(): void
    {
        $response = $this->get('/aanbod');

        $response->assertOk();
        $response->assertSee('Ons aanbod', false);
        $response->assertSee('page-header__divider', false);
    }

    public function test_it_lists_the_three_categories_in_their_designed_order(): void
    {
        $html = $this->get('/aanbod')->getContent();

        $voorJeWoning = strpos($html, 'Voor je woning');
        $rondomJeWoning = strpos($html, 'Rondom je woning');
        $slimEnComfort = strpos($html, 'Slim &amp; comfort');

        $this->assertNotFalse($voorJeWoning, 'Categorie "Voor je woning" ontbreekt');
        $this->assertNotFalse($rondomJeWoning, 'Categorie "Rondom je woning" ontbreekt');
        $this->assertNotFalse($slimEnComfort, 'Categorie "Slim & comfort" ontbreekt');

        $this->assertLessThan($rondomJeWoning, $voorJeWoning, '"Voor je woning" hoort eerst te staan');
        $this->assertLessThan($slimEnComfort, $rondomJeWoning, '"Rondom je woning" hoort tweede te staan');
    }

    public function test_it_renders_every_range_as_a_card_without_a_slider(): void
    {
        $html = $this->get('/aanbod')->getContent();

        $this->assertSame(9, substr_count($html, 'range-card'), 'Er horen negen range-kaarten te staan');
        $this->assertStringNotContainsString('data-slider', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);

        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
        $this->assertStringContainsString('href="/aanbod/somfy-smart-home"', $html);
    }

    public function test_the_page_builder_renders_below_the_categories(): void
    {
        $html = $this->get('/aanbod')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('Niet zeker welke oplossing past?', $html);

        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'data-section="range-overview"'),
            'De page builder hoort onder de categorieën te staan'
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter RangeOverviewPageTest`
Expected: FAIL — `/aanbod` geeft 404.

- [ ] **Step 3: Create the page entry**

Maak `content/collections/pages/aanbod.md`:

```yaml
---
id: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a02
blueprint: range_overview
title: 'Ons aanbod'
text: "Van ramen en rolluiken tot pergola's en smart home — alles om uw woning comfortabeler, veiliger en energiezuiniger te maken, op maat gemaakt en geplaatst door ons eigen team."
template: range-overview
seo_noindex: false
page_builder:
  -
    id: aanbodcta
    type: cta
    overline: Contact
    title: 'Niet zeker welke oplossing past?'
    text: 'Vraag vrijblijvend advies of een offerte. Onze lokale experts denken met u mee en stellen de juiste combinatie voor.'
    image: dummy-images/test-img-7.jpg
    link:
      -
        type: entry
        entry:
          - f0ee3161-1534-4986-9ef1-a92fccfba619
        label: 'Neem contact op'
        new_tab: false
---
```

Het `entry`-id is dat van `content/collections/pages/contact.md`.

- [ ] **Step 4: Register the entry in the pages tree**

`content/collections/pages.yaml` heeft `structure.root: true`, dus een entry verschijnt pas als hij in de boom staat. Voeg onderaan `content/trees/collections/pages.yaml` toe:

```yaml
  -
    entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a02
```

- [ ] **Step 5: Create the template**

Maak `resources/views/range-overview.antlers.html`:

```antlers
{{ partial:headers/default divider="true" }}

<section class="section section--default" data-section="range-overview">
    <div class="container">
        <div class="section-y-gap">
            {{ taxonomy:range_categories collection="ranges" sort="order:asc" min_count="1" }}
                <div class="section-header-gap">
                    {{ partial:overline :label="title" }}

                    <ul class="grid grid-gutter lg:grid-cols-2">
                        {{ entries }}
                            <li>{{ partial:rangeCard }}</li>
                        {{ /entries }}
                    </ul>
                </div>
            {{ /taxonomy:range_categories }}
        </div>
    </div>
</section>

{{ partial:pageBuilder }}
```

Drie dingen die deze tag doen:
- `collection="ranges"` scoopt `{{ entries }}` binnen elke term naar de ranges-collectie.
- `sort="order:asc"` gebruikt het veld uit Taak 1; zonder dat sorteert Statamic op `title:asc`.
- `min_count="1"` laat categorieën zonder ranges weg, zodat er geen lege overline blijft hangen.

Het aantal categorieën is nergens hardcoded: meer of minder dan drie rendert zonder aanpassing, en een categorie met één range vult gewoon de linkerkolom.

- [ ] **Step 6: Run test to verify it passes**

Run:
```bash
php please stache:clear
php artisan test --filter RangeOverviewPageTest
```
Expected: PASS, alle vier de methodes.

Loopt `test_it_renders_every_range_as_a_card_without_a_slider` stuk op een verkeerd aantal, controleer dan eerst of `min_count` een categorie wegfiltert die wél ranges hoort te hebben — dat wijst op een `range_category` die in Taak 1 niet is omgezet.

- [ ] **Step 7: Commit**

```bash
git add content/collections/pages/aanbod.md content/trees/collections/pages.yaml resources/views/range-overview.antlers.html tests/Feature/Content/RangeOverviewPageTest.php
git commit -m "feat(pages): build the /aanbod overview"
```

---

### Task 8: Filtercomponent — tag, partial, stijl en labels

**Files:**
- Create: `app/Tags/ProjectRanges.php`
- Create: `resources/views/partials/rangeFilter.antlers.html`
- Create: `resources/css/components/range-filter.css`
- Modify: `resources/css/site.css`
- Modify: `lang/nl/site.php`
- Test: `tests/Feature/Sections/ProjectRangesTagTest.php`
- Test: `tests/Feature/Sections/RangeFilterTest.php`

**Interfaces:**
- Consumes: het `range`-veld uit Taak 3 en de range-titels uit Taak 2.
- Produces: twee dingen.
  - `{{ project_ranges }}` — een pair-tag die per range met minstens één gepubliceerd project één item met `slug` en `title` levert, alfabetisch op titel.
  - `{{ partial:rangeFilter }}` — een `<nav class="range-filter">` met een "Toon alles"-knop vooraan en daarna één `<a class="range-filter__btn">` per item uit die tag. Elke knop draagt `data-range="<slug>"` (leeg voor "Toon alles"), roept `select('<slug>')` aan en krijgt `range-filter__btn--active` wanneer hij actief is. Het partial neemt geen argumenten.
- Taak 9 levert `select()`; Taak 10 plaatst het partial.

Waarom een tag en geen view model: dit project registreert al eigen Antlers-tags in `app/Tags/` (`Icon`, `Img`), en Statamic pikt die daar automatisch op via `protected static $handle`. Statamic's `view_model` daarentegen wordt gehydrateerd via `optional($this->get('view_model'))->value()`, wat een geaugmenteerde `Value` verwacht en dus een blueprint-veld vereist — losse front matter faalt daar stilletjes. De tag volgt het bestaande patroon en is direct testbaar.

- [ ] **Step 1: Write the failing test for the tag**

Maak `tests/Feature/Sections/ProjectRangesTagTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ProjectRangesTagTest extends SectionTestCase
{
    public function test_it_yields_only_ranges_that_have_projects(): void
    {
        $html = $this->render('{{ project_ranges }}[{{ slug }}]{{ /project_ranges }}');

        // De vier ranges waaraan de zes projecten hangen.
        $this->assertStringContainsString('[ramen-en-deuren]', $html);
        $this->assertStringContainsString('[rolluiken]', $html);
        $this->assertStringContainsString('[pergolas]', $html);
        $this->assertStringContainsString('[zonwering]', $html);

        // De vijf ranges zonder projecten.
        $this->assertStringNotContainsString('[airco]', $html);
        $this->assertStringNotContainsString('[velux]', $html);
        $this->assertStringNotContainsString('[stalen-binnendeuren]', $html);
        $this->assertStringNotContainsString('[garagepoorten]', $html);
        $this->assertStringNotContainsString('[somfy-smart-home]', $html);
    }

    public function test_it_deduplicates_and_sorts_by_title(): void
    {
        $html = $this->render('{{ project_ranges }}[{{ title }}]{{ /project_ranges }}');

        // Drie projecten hangen aan `pergolas`; die range hoort er één keer te staan.
        $this->assertSame(1, substr_count($html, "[Terrasoverkappingen & pergola's]"));

        $this->assertSame(
            '[Ramen en deuren][Rolluiken][Terrasoverkappingen & pergola\'s][Zonwering]',
            trim($html)
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter ProjectRangesTagTest`
Expected: FAIL — de tag `project_ranges` bestaat niet, dus de output is leeg.

- [ ] **Step 3: Create the tag**

Maak `app/Tags/ProjectRanges.php`:

```php
<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

class ProjectRanges extends Tags
{
    protected static $handle = 'project_ranges';

    /**
     * De ranges waaraan minstens één gepubliceerd project hangt, ontdubbeld en
     * alfabetisch op titel. Voedt het filter op /realisaties, zodat een klik
     * nooit een lege grid oplevert en het filter meegroeit met de content.
     *
     * Alfabetisch, niet in de volgorde van het ontwerp: de ranges-collectie
     * heeft geen handmatig sorteerveld, dus de categorie-volgorde van /aanbod
     * is hier niet reproduceerbaar zonder een extra veld. Zie de openstaande
     * punten in de spec.
     */
    public function index(): array
    {
        return Entry::query()
            ->where('collection', 'projects')
            ->where('published', true)
            ->get()
            ->map(fn ($project) => $project->augmentedValue('range')->value())
            ->filter()
            ->unique(fn ($range) => $range->slug())
            ->sortBy(fn ($range) => $range->get('title'))
            ->map(fn ($range) => [
                'slug' => $range->slug(),
                'title' => $range->get('title'),
            ])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
php please stache:clear
php artisan test --filter ProjectRangesTagTest
```
Expected: PASS.

- [ ] **Step 5: Write the failing test for the partial**

Maak `tests/Feature/Sections/RangeFilterTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class RangeFilterTest extends SectionTestCase
{
    public function test_renders_show_all_first_followed_by_every_used_range(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertStringContainsString('class="range-filter"', $html);
        $this->assertStringContainsString('Toon alles', $html);

        // Eén knop voor "Toon alles" plus één per gebruikte range.
        $this->assertSame(5, substr_count($html, 'data-range='));

        $this->assertLessThan(
            strpos($html, 'data-range="ramen-en-deuren"'),
            strpos($html, 'data-range=""'),
            '"Toon alles" hoort vooraan te staan'
        );
    }

    public function test_show_all_is_active_when_no_range_is_selected(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertMatchesRegularExpression(
            '/data-range=""[^>]*range-filter__btn--active/',
            $html,
            '"Toon alles" hoort standaard actief te zijn'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/data-range="zonwering"[^>]*range-filter__btn--active/',
            $html,
            'Zonder ?range hoort geen enkele range-knop actief te staan'
        );
    }

    public function test_every_range_button_links_to_its_own_query_string(): void
    {
        $html = $this->render('{{ partial src="rangeFilter" }}');

        $this->assertStringContainsString('href="?range=zonwering"', $html);
        $this->assertStringContainsString("select('zonwering')", $html);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter RangeFilterTest`
Expected: FAIL — de view `partials.rangeFilter` bestaat niet.

- [ ] **Step 7: Add the translation keys**

Voeg in `lang/nl/site.php` twee sleutels toe, bij de bestaande in de array:

```php
    'filter_all' => 'Toon alles',
    'filter_label' => 'Filter realisaties op productgroep',
```

- [ ] **Step 8: Create the partial**

Maak `resources/views/partials/rangeFilter.antlers.html`:

```antlers
{{#
    De filterpillen op /realisaties. Dit partial kent de projectgrid niet: het
    muteert alleen `active` op de omliggende Alpine-scope (zie
    resources/js/components/project-filter.js). De knoppen zijn echte links,
    zodat een gedeelde URL ook zonder JavaScript het juiste resultaat toont.

    De actieve staat wordt twee keer gezet: server-side uit `{{ get:range }}`
    voor de eerste paint en het no-JS-pad, en client-side via `:class` zodra
    Alpine draait. Beide sturen dezelfde klasse aan, dus er is geen flits.

    De lijst komt uit de `project_ranges`-tag (app/Tags/ProjectRanges.php),
    niet uit een argument: welke ranges in het filter horen, volgt uit de
    projecten zelf en is geen keuze van de aanroeper.
#}}
<nav class="range-filter" aria-label="{{ trans:site.filter_label }}">
    <ul class="range-filter__list">
        <li>
            <a href="{{ url }}"
               data-range=""
               class="range-filter__btn{{ if !get:range }} range-filter__btn--active{{ /if }}"
               :class="active === 'all' ? 'range-filter__btn range-filter__btn--active' : 'range-filter__btn'"
               :aria-current="active === 'all' ? 'true' : 'false'"
               @click.prevent="select('all')">{{ trans:site.filter_all }}</a>
        </li>

        {{ project_ranges }}
            <li>
                <a href="?range={{ slug }}"
                   data-range="{{ slug }}"
                   class="range-filter__btn{{ if get:range == slug }} range-filter__btn--active{{ /if }}"
                   :class="active === '{{ slug }}' ? 'range-filter__btn range-filter__btn--active' : 'range-filter__btn'"
                   :aria-current="active === '{{ slug }}' ? 'true' : 'false'"
                   @click.prevent="select('{{ slug }}')">{{ title }}</a>
            </li>
        {{ /project_ranges }}
    </ul>
</nav>
```

`href="{{ url }}"` op "Toon alles" wijst naar de pagina zonder querystring; op een echte pagina levert de cascade daar `/realisaties`. In de testharnas is `url` leeg, wat de asserties hierboven niet raakt.

- [ ] **Step 9: Create the stylesheet**

Maak `resources/css/components/range-filter.css`:

```css
/*
 * Figma dgMxUtoYzYrR5FRuwPzQBn, nodes 457:6014 (desktop) / 457:6550 (mobile).
 * Desktop is een verticale kolom pillen naast de grid; mobile een horizontaal
 * scrollende rij die tot de schermrand doorloopt. Er zit bewust geen
 * transition op de actieve staat: het ontwerp vraagt een instant wissel.
 */

.range-filter__list {
    @apply flex gap-2 overflow-x-auto lg:flex-col lg:items-start lg:gap-3 lg:overflow-x-visible;

    scrollbar-width: none;
}

.range-filter__list::-webkit-scrollbar {
    display: none;
}

/*
 * Onder `lg` loopt de rij door tot de schermrand in plaats van tot de
 * container-inset, zoals in 457:6550. Zelfde techniek als `slider-bleed`,
 * maar lokaal gehouden — dit is geen slider.
 */
@media (max-width: 1023px) {
    .range-filter__list {
        margin-inline: calc(var(--spacing-margin-mobile) * -1);
        padding-inline: var(--spacing-margin-mobile);
    }
}

.range-filter__btn {
    @apply inline-flex shrink-0 items-center rounded-full bg-light px-[1.125rem] py-3 font-semibold whitespace-nowrap text-black lg:px-8 lg:py-5;

    /* 11 → 14px, zelfde clamp-vorm als de rest van de typografie */
    font-size: clamp(0.6875rem, 0.554rem + 0.335vw, 0.875rem);
    letter-spacing: 0.02em;
    line-height: 1.1;
}

.range-filter__btn--active {
    @apply bg-black text-white;
}
```

- [ ] **Step 10: Import the stylesheet**

Voeg onderaan de componentenlijst in `resources/css/site.css` toe:

```css
@import './components/range-filter.css';
```

- [ ] **Step 11: Run both tests to verify they pass**

Run:
```bash
php artisan test --filter ProjectRangesTagTest
php artisan test --filter RangeFilterTest
```
Expected: beide PASS.

`{{ get:range }}` komt uit de Statamic-cascade en is in de testharnas leeg, waardoor "Toon alles" daar altijd actief is — precies wat `test_show_all_is_active_when_no_range_is_selected` verwacht. Het gedrag mét `?range=` wordt in Taak 10 over een echte request getest.

- [ ] **Step 12: Commit**

```bash
git add app/Tags/ProjectRanges.php resources/views/partials/rangeFilter.antlers.html resources/css/components/range-filter.css resources/css/site.css lang/nl/site.php tests/Feature/Sections/ProjectRangesTagTest.php tests/Feature/Sections/RangeFilterTest.php
git commit -m "feat(realisaties): add the range filter component"
```

---

### Task 9: Alpine-component voor het filter

**Files:**
- Create: `resources/js/components/project-filter.js`
- Modify: `resources/js/site.js:1-10`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: `Alpine.data('projectFilter', projectFilter)`. De factory neemt één argument (de initiële range-slug, leeg voor "alles") en levert `active` (string), `matches(slug): boolean` en `select(slug): void`. Taak 10 bindt `x-data="projectFilter('{{ get:range }}')"` en `:hidden="!matches('…')"`.

Dit project heeft geen JS-testrunner (zie `package.json`: enkel Vite, geen vitest). Het gedrag wordt daarom via de gerenderde markup in Taak 10 vastgelegd, en de bouw wordt hier geverifieerd met `npm run build`.

- [ ] **Step 1: Create the component**

Maak `resources/js/components/project-filter.js`:

```js
/**
 * Filtert de projectgrid op /realisaties.
 *
 * De server rendert altijd álle projecten en zet `hidden` op de kaarten die
 * bij de eerste paint niet matchen. Dit component neemt datzelfde attribuut
 * over via `:hidden`, zodat server en client hetzelfde mechanisme gebruiken:
 * geen flits bij het booten, geen animatie, en "Toon alles" werkt zonder
 * request omdat alle kaarten al in de DOM staan.
 */
export function projectFilter(initial = '') {
    return {
        active: initial || 'all',

        matches(slug) {
            return this.active === 'all' || this.active === slug
        },

        select(slug) {
            this.active = slug || 'all'

            const url = new URL(window.location)

            if (this.active === 'all') {
                url.searchParams.delete('range')
            } else {
                url.searchParams.set('range', this.active)
            }

            window.history.replaceState({}, '', url)
        },
    }
}
```

- [ ] **Step 2: Register it with Alpine**

In `resources/js/site.js` voeg je de import toe naast de bestaande en registreer je het component vóór `Alpine.start()`:

```js
import Alpine from 'alpinejs'
import Collapse from '@alpinejs/collapse'
import { cookieConsent } from './components/cookie-consent'
import { projectFilter } from './components/project-filter'

window.Alpine = Alpine
Alpine.plugin(Collapse)
Alpine.data('cookieConsent', cookieConsent)
Alpine.data('projectFilter', projectFilter)
Alpine.start()
```

De rest van het bestand blijft ongewijzigd.

- [ ] **Step 3: Verify the bundle builds**

Run: `npm run build`
Expected: build slaagt zonder fouten en zonder waarschuwing over een onopgeloste import.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/project-filter.js resources/js/site.js
git commit -m "feat(realisaties): add the Alpine project filter component"
```

---

### Task 10: `/realisaties` — entry, template en gefilterde grid

**Files:**
- Create: `content/collections/pages/realisaties.md`
- Create: `resources/views/projects-overview.antlers.html`
- Modify: `content/trees/collections/pages.yaml`
- Test: `tests/Feature/Content/ProjectsOverviewPageTest.php`

**Interfaces:**
- Consumes: het `range`-veld (Taak 3), `projectCard` (Taak 5), het `divider`-argument (Taak 6), `rangeFilter` (Taak 8), `projectFilter` (Taak 9).
- Produces: een werkende `/realisaties`. Sluit het plan af.

- [ ] **Step 1: Write the failing test**

Maak `tests/Feature/Content/ProjectsOverviewPageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ProjectsOverviewPageTest extends TestCase
{
    public function test_the_page_renders_with_its_header_and_divider(): void
    {
        $response = $this->get('/realisaties');

        $response->assertOk();
        $response->assertSee('Realisaties', false);
        $response->assertSee('page-header__divider', false);
    }

    public function test_the_filter_only_offers_ranges_that_have_projects(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertStringContainsString('Toon alles', $html);

        // De vier ranges waaraan projecten hangen.
        $this->assertStringContainsString('data-range="pergolas"', $html);
        $this->assertStringContainsString('data-range="zonwering"', $html);
        $this->assertStringContainsString('data-range="ramen-en-deuren"', $html);
        $this->assertStringContainsString('data-range="rolluiken"', $html);

        // Ranges zonder projecten horen er niet te staan.
        $this->assertStringNotContainsString('data-range="airco"', $html);
        $this->assertStringNotContainsString('data-range="velux"', $html);
        $this->assertStringNotContainsString('data-range="somfy-smart-home"', $html);
    }

    public function test_it_renders_every_project_as_a_card_without_a_slider(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertSame(6, substr_count($html, 'project-card '));
        $this->assertStringNotContainsString('data-slider', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);
    }

    public function test_without_a_query_string_nothing_is_hidden(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertSame(0, substr_count($html, '<li hidden'));
    }

    public function test_a_range_query_string_hides_the_others_without_dropping_them(): void
    {
        $html = $this->get('/realisaties?range=zonwering')->getContent();

        // Alle zes kaarten blijven in de DOM staan — Alpine moet ze terug
        // kunnen tonen zonder nieuwe request.
        $this->assertSame(6, substr_count($html, 'project-card '));

        // Eén project hangt aan `zonwering`, dus vijf staan er verborgen.
        $this->assertSame(5, substr_count($html, '<li hidden'));

        $this->assertStringContainsString('Zip-screens op nieuwbouwwoning', $html);
    }

    public function test_a_range_query_string_marks_that_button_active(): void
    {
        $html = $this->get('/realisaties?range=zonwering')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-range="zonwering"[^>]*range-filter__btn--active/',
            $html,
            'De zonwering-knop hoort actief te staan'
        );
    }

    public function test_it_wires_up_the_alpine_filter(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertStringContainsString('x-data="projectFilter(', $html);
        $this->assertStringContainsString(':hidden="!matches(', $html);
        $this->assertStringNotContainsString('x-transition', $html);
    }

    public function test_the_page_builder_renders_below_the_grid(): void
    {
        $html = $this->get('/realisaties')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('Geïnspireerd geraakt?', $html);

        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'data-section="projects-overview"'),
            'De page builder hoort onder de grid te staan'
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter ProjectsOverviewPageTest`
Expected: FAIL — `/realisaties` geeft 404.

- [ ] **Step 3: Create the page entry**

Maak `content/collections/pages/realisaties.md`:

```yaml
---
id: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
blueprint: projects_overview
title: Realisaties
text: 'Samen je huis klaarmaken voor de toekomst — energiebewust, comfortabel en met vakmanschap uit je eigen buurt.'
template: projects-overview
seo_noindex: false
page_builder:
  -
    id: realisatiescta
    type: cta
    overline: Offerte
    title: 'Geïnspireerd geraakt?'
    text: 'Vraag vrijblijvend een offerte aan. We rekenen de juiste maatvoering en opties voor uw terras door.'
    image: dummy-images/test-img-14.jpg
    link:
      -
        type: entry
        entry:
          - f0ee3161-1534-4986-9ef1-a92fccfba619
        label: 'Vraag offerte aan'
        new_tab: false
---
```

- [ ] **Step 4: Register the entry in the pages tree**

Voeg onderaan `content/trees/collections/pages.yaml` toe:

```yaml
  -
    entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
```

- [ ] **Step 5: Create the template**

Maak `resources/views/projects-overview.antlers.html`:

```antlers
{{#
    De server rendert álle projecten en zet `hidden` op wie bij de eerste
    paint niet matcht; Alpine neemt datzelfde attribuut over via `:hidden`.
    Daardoor werkt "Toon alles" zonder request, blijft een gedeelde URL als
    /realisaties?range=zonwering ook zonder JavaScript kloppen, en is er geen
    animatie in het spel.

    Welke ranges in het filter horen, bepaalt `rangeFilter` zelf via de
    `project_ranges`-tag. Deze template geeft die lijst dus niet door.
#}}
{{ partial:headers/default divider="true" }}

<section class="section section--default" data-section="projects-overview"
         x-data="projectFilter('{{ get:range }}')">
    <div class="container">
        <div class="grid grid-gutter lg:grid-cols-[24rem_1fr] lg:gap-x-16">
            {{ partial:rangeFilter }}

            <ul class="grid grid-gutter md:grid-cols-2">
                {{ collection:projects }}
                    <li {{ if get:range && get:range != range:slug }}hidden{{ /if }} :hidden="!matches('{{ range:slug }}')">
                        {{ partial:projectCard }}
                    </li>
                {{ /collection:projects }}
            </ul>
        </div>
    </div>
</section>

{{ partial:pageBuilder }}
```

Het `<li>` staat bewust op één regel: de test telt `<li hidden` als letterlijke string, en een newline tussen tagnaam en attribuut zou die assertie breken.

- [ ] **Step 6: Run test to verify it passes**

Run:
```bash
php please stache:clear
php artisan test --filter ProjectsOverviewPageTest
```
Expected: PASS, alle acht de methodes.

Faalt `test_a_range_query_string_hides_the_others_without_dropping_them` op het aantal `<li hidden`, controleer dan de gerenderde HTML: Antlers laat een `{{ if }}` die niets oplevert een spatie achter, waardoor er `<li  :hidden=` kan staan. Houd `<li` en `{{ if … }}hidden{{ /if }}` op één regel en zonder extra spatie ertussen.

- [ ] **Step 7: Verify the two overview pages and the page builder still agree**

Run:
```bash
php artisan test --filter RangeOverviewPageTest
php artisan test --filter PageBuilderPageTest
php artisan test --filter ProjectsSectionTest
php artisan test --filter RangesSectionTest
```
Expected: alle PASS.

- [ ] **Step 8: Commit**

```bash
git add content/collections/pages/realisaties.md content/trees/collections/pages.yaml resources/views/projects-overview.antlers.html tests/Feature/Content/ProjectsOverviewPageTest.php
git commit -m "feat(pages): build the /realisaties overview with a range filter"
```

---

## Na afloop

Draai de tests die dit werk raakt in één keer:

```bash
php artisan test --filter 'RangeCategoriesContentTest|RangesContentTest|CatalogContentTest|RangeCardTest|ProjectCardTest|ProjectRangesTagTest|RangeFilterTest|PageHeaderTest|RangesSectionTest|ProjectsSectionTest|PageBuilderPageTest|RangeOverviewPageTest|ProjectsOverviewPageTest'
```

Controleer daarna handmatig in de browser, want dit ligt buiten wat de tests dekken:

1. `/aanbod` op 402px breed: drie categorieblokken, kaarten onder elkaar, geen horizontale scroll.
2. `/realisaties` op 402px breed: het filter scrollt horizontaal door tot de schermrand.
3. Klikken op een filterknop wisselt de grid onmiddellijk, zonder animatie en zonder paginalading, en de URL verspringt mee.
4. `/realisaties?range=zonwering` rechtstreeks openen: één kaart zichtbaar, juiste knop actief.
5. Diezelfde URL met JavaScript uitgeschakeld: hetzelfde resultaat.
