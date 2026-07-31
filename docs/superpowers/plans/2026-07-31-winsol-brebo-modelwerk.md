# Winsol Brebo — Modelwerk (project 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De Statamic-installatie geschikt maken om echte content en echte beelden te dragen, zodat het contentwerk per range (project 2) zonder modelwijzigingen kan starten.

**Architecture:** Alle wijzigingen zijn additief op de bestaande fieldset-gedreven opzet. Blueprints en fieldsets zijn handgeschreven YAML onder `resources/`, collectieconfig onder `content/`. Drie nieuwe Artisan-commando's onder `app/Console/Commands/` verzorgen de beeldpijplijn; ze hergebruiken de bestaande `ImageCompressor` via het `AssetUploaded`-event in plaats van een tweede compressiepad te openen.

**Tech Stack:** Statamic 6, Laravel 12, Antlers, Tailwind v4, PHPUnit, Intervention/PIL-vrije GD via `App\Services\ImageCompressor`.

## Global Constraints

- **Testcommando:** `vendor/bin/phpunit -d memory_limit=1G`. Nooit `php artisan test`.
- **Antlers-condities:** inline condities zijn ternary, geen `{{ if }}`-blok om één waarde. Meerdere takken → bepaal de waarde in een blok bóven de sectie.
- **Styling:** Tailwind-utilities in de markup, geen `style=""`. Herhaalde klassenreeksen worden een `@utility` in `resources/css/components/<naam>.css`. Geen arbitrary values.
- **Iconen:** altijd `{{ icon src="…" }}`, nooit `{{ svg src="icons/…" }}`.
- **Formattering:** Prettier doet klassevolgorde en Antlers-opmaak. Niet handmatig herschikken.
- **Commentaar:** alleen wat je niet uit de code kunt lezen.
- **Nieuwe velden op blueprints die dit plan opent, krijgen `localizable: true`** — de FR-site komt na project 3.
- **Taal van CP-labels en instructies:** Nederlands, zoals de bestaande blueprints.

---

## File Structure

| Bestand | Verantwoordelijkheid |
|---|---|
| `resources/sites.yaml` | **nieuw** — sitedefinitie, één site `nl` |
| `config/statamic/system.php` | `multisite => true` |
| `resources/blueprints/collections/products/products.yaml` | `range`- en `brochure`-veld |
| `resources/blueprints/collections/ranges/ranges.yaml` | `brochure`-veld |
| `resources/blueprints/collections/quicklinks/quicklinks.yaml` | `type`-select |
| `resources/blueprints/assets/assets.yaml` | `watermark` + `watermark_box` |
| `content/collections/products.yaml` | geneste route |
| `app/Providers/AppServiceProvider.php` | computed `range_slug`, commandoregistratie |
| `resources/fieldsets/page_builder.yaml` | `media`-schakelaar op `text_image`, nieuwe `embed`-set |
| `resources/views/partials/sections/textImage.antlers.html` | videotak |
| `resources/views/partials/sections/embed.antlers.html` | **nieuw** — iframe-sectie |
| `resources/views/partials/quicklinkCard.antlers.html` | brochureknop |
| `resources/views/partials/quicklinks.antlers.html` | brochure doorgeven, kolomaantal |
| `resources/views/partials/footer.antlers.html` | footer-navigatie |
| `app/Console/Commands/ImportImages.php` | **nieuw** — beeldimport + watermerkvlag |
| `app/Console/Commands/CleanWatermarks.php` | **nieuw** — bijsnijden van gebruikte watermerkassets |
| `app/Console/Commands/ImageGaps.php` | **nieuw** — placeholderrapport |
| `app/Services/WatermarkDetector.php` | **nieuw** — detectie + bounding box, gedeeld door alle drie |
| `app/Services/WatermarkResult.php` | **nieuw** — uitkomst van de detector |
| `app/Services/UsedAssetFinder.php` | **nieuw** — assetpaden waar entries naar wijzen |
| `tests/Concerns/CreatesTemporaryContent.php` | **nieuw** — `Storage::fake('r2')` en opruimen van testentries |

`WatermarkDetector` staat apart omdat drie commando's hem gebruiken en hij als enige pure beeldlogica bevat — hij is los te testen zonder Statamic.

---

## Task 1: Multisite aanzetten met één site `nl`

Nu doen, nu de site nog leeg is. Dezelfde migratie ná project 2 en 3 raakt ~50 entries, alle assets en elke blueprint tegelijk.

**Files:**
- Create: `resources/sites.yaml`
- Modify: `config/statamic/system.php:33`
- Test: `tests/Feature/Content/MultisiteTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: sitehandle `nl` als `Site::default()->handle()`. Contentbestanden verhuizen naar `content/collections/<handle>/nl/`. Alle latere taken gaan uit van dit pad.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Content/MultisiteTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\TestCase;

class MultisiteTest extends TestCase
{
    public function test_there_is_exactly_one_site_and_it_is_dutch(): void
    {
        $sites = Site::all();

        $this->assertCount(1, $sites);
        $this->assertSame('nl', Site::default()->handle());
        $this->assertSame('nl_BE', Site::default()->locale());
    }

    public function test_existing_entries_still_resolve_after_the_conversion(): void
    {
        $home = Entry::query()->where('collection', 'pages')->where('slug', 'home')->first();

        $this->assertNotNull($home, 'De home-entry is de conversie niet overleefd');
        $this->assertSame('nl', $home->locale());
    }

    public function test_content_files_moved_into_the_site_folder(): void
    {
        $this->assertFileExists(base_path('content/collections/pages/nl/home.md'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter MultisiteTest`
Expected: FAIL — `Site::default()->handle()` is `default`, en `content/collections/pages/nl/home.md` bestaat niet.

- [ ] **Step 3: Write `resources/sites.yaml`**

```yaml
nl:
  name: 'Winsol Brebo'
  url: /
  locale: nl_BE
```

- [ ] **Step 4: Zet multisite aan**

In `config/statamic/system.php`, regel 33:

```php
'multisite' => true,
```

- [ ] **Step 5: Draai de conversie**

Run: `php please multisite`
Expected: het commando bevestigt de conversie en verplaatst de contentbestanden naar `content/collections/<handle>/nl/`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter MultisiteTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Draai de volledige suite**

Run: `vendor/bin/phpunit -d memory_limit=1G`
Expected: PASS. De conversie verplaatst bestanden; slaagt een bestaande contenttest niet meer, dan zoekt die op een hardcoded pad — pas dat pad aan, niet de test-intentie.

- [ ] **Step 8: Commit**

```bash
git add resources/sites.yaml config/statamic/system.php content/ tests/Feature/Content/MultisiteTest.php
git commit -m "feat: zet multisite aan met een enkele nl-site"
```

---

## Task 2: Geneste productroute `/aanbod/{range}/{slug}`

**Files:**
- Modify: `resources/blueprints/collections/products/products.yaml`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `content/collections/products.yaml`
- Modify: `content/collections/products/nl/*.md` (zes entries)
- Test: `tests/Feature/Content/ProductRouteTest.php`

**Interfaces:**
- Consumes: sitehandle `nl` uit Task 1.
- Produces: computed value `range_slug` op de `products`-collectie, type `?string`. Blueprintveld `range` (entries, `max_items: 1`). Elke productentry heeft vanaf nu een URL `/aanbod/<range-slug>/<product-slug>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Content/ProductRouteTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ProductRouteTest extends TestCase
{
    public function test_every_product_points_at_exactly_one_range(): void
    {
        $products = Entry::query()->where('collection', 'products')->get();

        $this->assertGreaterThan(0, $products->count(), 'Er zijn geen producten om te controleren');

        foreach ($products as $product) {
            $range = $product->get('range');

            $this->assertNotEmpty($range, "Product {$product->slug()} heeft geen range");
            $this->assertCount(1, (array) $range, "Product {$product->slug()} wijst naar meer dan een range");
        }
    }

    public function test_the_computed_range_slug_resolves_to_the_range_its_slug(): void
    {
        $product = Entry::query()->where('collection', 'products')->where('slug', 'pergola-so')->first();

        $this->assertNotNull($product);
        $this->assertSame('pergolas', $product->augmentedValue('range_slug')->value());
    }

    public function test_the_url_nests_the_product_under_its_range(): void
    {
        $product = Entry::query()->where('collection', 'products')->where('slug', 'pergola-so')->first();

        $this->assertSame('/aanbod/pergolas/pergola-so', $product->url());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter ProductRouteTest`
Expected: FAIL — het `range`-veld bestaat niet, dus `get('range')` is leeg.

- [ ] **Step 3: Voeg het `range`-veld toe aan de blueprint**

In `resources/blueprints/collections/products/products.yaml`, binnen `tabs.main.sections[0].fields`, direct ná het `title`-veld:

```yaml
          -
            handle: range
            field:
              type: entries
              display: Range
              collections:
                - ranges
              max_items: 1
              localizable: true
              instructions: 'Bepaalt de URL van dit product: /aanbod/<range>/<product>.'
              required: true
              validate:
                - required
```

- [ ] **Step 4: Registreer de computed value**

In `app/Providers/AppServiceProvider.php`, voeg bovenaan toe:

```php
use Illuminate\Support\Arr;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
```

En in `boot()`, ná de `Sets::useIcons(...)`-regel:

```php
// Het `range`-veld is een entries-veld en levert een id, geen slug. De route
// heeft de slug nodig, dus die wordt hier afgeleid.
Collection::computed('products', 'range_slug', function ($entry) {
    $id = Arr::first(Arr::wrap($entry->get('range')));

    return $id ? Entry::find($id)?->slug() : null;
});
```

- [ ] **Step 5: Zet de route om**

In `content/collections/products.yaml`:

```yaml
title: Products
template: products/show
route: '/aanbod/{{ range_slug }}/{{ slug }}'
```

- [ ] **Step 6: Koppel de bestaande productentries aan een range**

De zes bestaande entries zijn verzonnen en worden in project 2 vervangen, maar moeten nu al een geldige range hebben zodat de route resolvet. Zoek de id van de range op en zet hem in de frontmatter.

Run om de range-ids te vinden:

```bash
grep -h "^id:" content/collections/ranges/nl/pergolas.md content/collections/ranges/nl/ramen-en-deuren.md
```

Voeg in elk van deze bestanden onder `title:` een `range:`-blok toe met het id van de bijbehorende range:

| Bestand | Range |
|---|---|
| `content/collections/products/nl/pergola-so.md` | `pergolas` |
| `content/collections/products/nl/pergola-co.md` | `pergolas` |
| `content/collections/products/nl/pergola-lo.md` | `pergolas` |
| `content/collections/products/nl/carport.md` | `pergolas` |
| `content/collections/products/nl/veranda.md` | `pergolas` |
| `content/collections/products/nl/terrasoverkapping-met-glasdak.md` | `pergolas` |

Vorm van het blok (met het echte id van `pergolas`):

```yaml
range:
  - 8c2e41a0-0001-4a1b-9c7d-3e5f6a7b8c01
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter ProductRouteTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Draai de volledige suite**

Run: `vendor/bin/phpunit -d memory_limit=1G`
Expected: PASS. Bestaande tests die een product-URL vastpinnen op `/producten/…` moeten mee omgezet worden naar `/aanbod/…`.

- [ ] **Step 9: Commit**

```bash
git add resources/blueprints/collections/products/products.yaml app/Providers/AppServiceProvider.php content/collections/products.yaml content/collections/products/ tests/Feature/Content/ProductRouteTest.php
git commit -m "feat: nest producten onder hun range in de URL"
```

---

## Task 3: Brochureveld op ranges en producten

**Files:**
- Modify: `resources/blueprints/collections/ranges/ranges.yaml`
- Modify: `resources/blueprints/collections/products/products.yaml`
- Test: `tests/Feature/Content/BrochureFieldTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: veldhandle `brochure` op beide blueprints — `type: assets`, `container: assets`, `folder: brochures`, `max_files: 1`. Task 4 leest dit veld.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Content/BrochureFieldTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Tests\TestCase;

class BrochureFieldTest extends TestCase
{
    public static function blueprintProvider(): array
    {
        return [
            'ranges' => ['collections.ranges.ranges'],
            'products' => ['collections.products.products'],
        ];
    }

    /**
     * @dataProvider blueprintProvider
     */
    public function test_it_has_a_single_pdf_brochure_field_in_the_brochures_folder(string $handle): void
    {
        $field = Blueprint::find($handle)->field('brochure');

        $this->assertNotNull($field, "Blueprint {$handle} heeft geen brochureveld");

        $config = $field->config();

        $this->assertSame('assets', $config['type']);
        $this->assertSame('assets', $config['container']);
        $this->assertSame('brochures', $config['folder']);
        $this->assertTrue($config['restrict']);
        $this->assertSame(1, $config['max_files']);
        $this->assertContains('mimes:pdf', $config['validate']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter BrochureFieldTest`
Expected: FAIL — `field('brochure')` is `null`.

- [ ] **Step 3: Voeg het veld toe aan beide blueprints**

Dit blok komt in `resources/blueprints/collections/ranges/ranges.yaml` ná het `order`-veld, en in `resources/blueprints/collections/products/products.yaml` ná `import: image` — in beide gevallen in `tabs.main.sections[0].fields`:

```yaml
          -
            handle: brochure
            field:
              type: assets
              display: Brochure
              container: assets
              folder: brochures
              restrict: true
              max_files: 1
              localizable: true
              instructions: 'Optioneel. Staat er een brochure, dan wordt de brochure-quicklink een directe download; anders verdwijnt die kaart.'
              validate:
                - 'mimes:pdf'
```

Geen aparte assetcontainer: `CompressUploadedAsset` filtert al op `image-compression.process_mimes`, dus een pdf gaat er ongemoeid doorheen.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter BrochureFieldTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/blueprints/collections/ranges/ranges.yaml resources/blueprints/collections/products/products.yaml tests/Feature/Content/BrochureFieldTest.php
git commit -m "feat: brochureveld op ranges en producten"
```

---

## Task 4: Brochure-quicklink

De kaart wordt een directe download als er een brochure hangt, en rendert niets als die ontbreekt.

**Files:**
- Modify: `resources/blueprints/collections/quicklinks/quicklinks.yaml`
- Modify: `resources/views/partials/quicklinkCard.antlers.html`
- Modify: `content/collections/quicklinks/nl/vraag-brochure-aan.md`
- Test: `tests/Feature/Sections/QuicklinkBrochureCardTest.php`

**Interfaces:**
- Consumes: veldhandle `brochure` uit Task 3.
- Produces: veldhandle `type` op quicklinks met waarden `default` | `brochure`. `quicklinkCard` verwacht een variabele `brochure` in scope; die wordt in Task 5 expliciet doorgegeven.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sections/QuicklinkBrochureCardTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class QuicklinkBrochureCardTest extends SectionTestCase
{
    private function brochureCard(): array
    {
        return [
            'title' => 'Vraag brochure aan',
            'text' => 'Alles over dit product in een pdf.',
            'type' => 'brochure',
            'link_style' => 'outline',
            'link' => [[
                'type' => 'url',
                'url' => 'example.com',
                'label' => 'Brochure aanvragen',
            ]],
        ];
    }

    public function test_a_default_card_is_untouched_by_the_brochure_logic(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', [
            'title' => 'Bezoek een showroom',
            'type' => 'default',
            'link_style' => 'outline',
            'link' => [[
                'type' => 'url',
                'url' => 'example.com',
                'label' => 'Plan een bezoek',
            ]],
        ]);

        $this->assertStringContainsString('Bezoek een showroom', $html);
        $this->assertStringContainsString('Plan een bezoek', $html);
        $this->assertStringNotContainsString('download', $html);
    }

    public function test_a_brochure_card_with_a_pdf_becomes_a_direct_download(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', array_merge($this->brochureCard(), [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]));

        $this->assertStringContainsString('/assets/brochures/pergola-so.pdf', $html);
        $this->assertStringContainsString('download', $html);
        $this->assertStringContainsString('Brochure aanvragen', $html);
    }

    public function test_a_brochure_card_without_a_pdf_renders_nothing(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', $this->brochureCard());

        $this->assertStringNotContainsString('quicklink-card', $html);
        $this->assertSame('', trim($html));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter QuicklinkBrochureCardTest`
Expected: FAIL — de kaart rendert altijd en kent `download` niet.

- [ ] **Step 3: Voeg het `type`-veld toe aan de quicklinks-blueprint**

In `resources/blueprints/collections/quicklinks/quicklinks.yaml`, in `tabs.main.sections[0].fields`, ná `link_style`:

```yaml
          -
            handle: type
            field:
              type: select
              display: Type
              default: default
              localizable: true
              instructions: 'Kies "Brochure" om deze kaart de brochure van de pagina te laten downloaden. Zonder brochure verdwijnt de kaart.'
              options:
                default: Standaard
                brochure: Brochure
```

- [ ] **Step 4: Herschrijf `quicklinkCard.antlers.html`**

De brochuretak is meer dan één waarde, dus de conditie staat bóven de markup en de kaart zelf blijft aftakkingsvrij:

```antlers
{{#
    `brochure` komt niet uit de quicklink maar uit de pagina eromheen en wordt
    expliciet doorgegeven door `quicklinks` en `pageQuicklinks`. Een brochurekaart
    zonder brochure rendert niets — dan is er niets te downloaden.
#}}
{{ is_brochure = type == 'brochure' }}
{{ if not is_brochure or brochure }}
    <div class="quicklink-card flex h-full flex-col rounded-md bg-light card-padding">
        {{ if image }}
            <div class="quicklink-media">
                {{ img :src="image" max_width="640" sizes="(min-width: 1024px) 30vw, 90vw" class="h-auto max-h-28 w-auto max-w-full object-contain lg:max-h-40 lg:-ml-2" }}
            </div>
        {{ /if }}
        <div class="flex grow flex-col gap-4 xl:gap-5">
            <h3>{{ title | entities }}</h3>
            {{ if text }}
                <p>{{ text | entities }}</p>
            {{ /if }}
            {{ if is_brochure }}
                <a href="{{ brochure:url }}" download class="{{ link_style == 'outline' ? 'btn btn--outline' : 'btn btn--primary' }}">
                    {{ link:0:label ?? 'Download brochure' }}
                </a>
            {{ elseif link }}
                {{ partial:link :style="link_style == 'outline' ? 'btn btn--outline' : 'btn btn--primary'" }}
            {{ /if }}
        </div>
    </div>
{{ /if }}
```

- [ ] **Step 5: Markeer de bestaande brochure-quicklink**

In `content/collections/quicklinks/nl/vraag-brochure-aan.md`, voeg aan de frontmatter toe:

```yaml
type: brochure
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter QuicklinkBrochureCardTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Draai de bestaande quicklinktests**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "PageQuicklinksTest|QuicklinksContentTest"`
Expected: PASS. `PageQuicklinksTest` geeft geen `type` mee, dus die kaarten blijven standaard.

- [ ] **Step 8: Commit**

```bash
git add resources/blueprints/collections/quicklinks/quicklinks.yaml resources/views/partials/quicklinkCard.antlers.html content/collections/quicklinks/ tests/Feature/Sections/QuicklinkBrochureCardTest.php
git commit -m "feat: brochure-quicklink wordt downloadknop en verbergt zich zonder pdf"
```

---

## Task 5: Brochure doorgeven en het kolomaantal

De kaart uit Task 4 heeft `brochure` in scope nodig. Dat moet expliciet, niet via de cascade — binnen `{{ collection:quicklinks }}` zou `{{ brochure }}` nu toevallig doorvallen naar de pagina, precies het impliciete gedrag waar de comment bovenaan `quicklinks.antlers.html` voor waarschuwt.

**Files:**
- Modify: `resources/views/partials/quicklinks.antlers.html`
- Modify: `resources/views/partials/pageQuicklinks.antlers.html`
- Modify: `resources/views/ranges/show.antlers.html`
- Modify: `resources/views/products/show.antlers.html`
- Test: `tests/Feature/Sections/QuicklinksSectionTest.php`
- Test: `tests/Feature/Content/QuicklinksContentTest.php` (aanvullen)

**Interfaces:**
- Consumes: `quicklinkCard` uit Task 4, veldhandle `brochure` uit Task 3.
- Produces: `{{ partial:quicklinks :brochure="brochure" }}` als aanroepvorm. Geen nieuwe handles.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sections/QuicklinksSectionTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class QuicklinksSectionTest extends SectionTestCase
{
    public function test_three_columns_when_a_brochure_is_present(): void
    {
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('lg:grid-cols-3', $html);
        $this->assertStringNotContainsString('lg:grid-cols-2', $html);
    }

    public function test_two_columns_when_there_is_no_brochure(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('lg:grid-cols-2', $html);
        $this->assertStringNotContainsString('lg:grid-cols-3', $html);
    }
}
```

Voeg aan `tests/Feature/Content/QuicklinksContentTest.php` deze test toe — het kolomaantal in Step 4 leunt erop dat precies één quicklink van het type `brochure` is:

```php
    public function test_exactly_one_quicklink_is_the_brochure_card(): void
    {
        $brochureCards = \Statamic\Facades\Entry::query()
            ->where('collection', 'quicklinks')
            ->get()
            ->filter(fn ($entry) => $entry->get('type') === 'brochure');

        $this->assertCount(1, $brochureCards, 'Het kolomaantal in quicklinks.antlers.html gaat uit van precies een brochurekaart');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "QuicklinksSectionTest|QuicklinksContentTest"`
Expected: FAIL — `lg:grid-cols-3` staat hardcoded, dus de tweede test faalt.

- [ ] **Step 3: Herschrijf de sectie**

In `resources/views/partials/quicklinks.antlers.html`, vervang de `<ul>` en zet de kolomkeuze erboven. Laat de bestaande comment bovenaan het bestand staan en vul hem aan:

```antlers
{{#
    Het kolomaantal hangt aan de brochure: zonder pdf verbergt de brochurekaart
    zich (zie quicklinkCard), dus blijven er twee van de drie over. Dat mag hier
    uit `brochure` afgeleid worden omdat precies een quicklink het type
    `brochure` draagt — vastgepind in QuicklinksContentTest.
#}}
{{ grid_columns = brochure ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }}

<ul class="quicklink-grid {{ grid_columns }}">
    {{ collection:quicklinks as="cards" }}
        {{ cards }}
            <li>{{ partial:quicklinkCard :brochure="brochure" }}</li>
        {{ /cards }}
    {{ /collection:quicklinks }}
</ul>
```

- [ ] **Step 4: Geef de brochure door in `pageQuicklinks`**

In `resources/views/partials/pageQuicklinks.antlers.html`, binnen de loop:

```antlers
                    {{ quicklinks }}
                        <li>{{ partial:quicklinkCard :brochure="brochure" }}</li>
                    {{ /quicklinks }}
```

- [ ] **Step 5: Roep de sectie aan met de brochure**

In `resources/views/ranges/show.antlers.html` en `resources/views/products/show.antlers.html`, vervang elke `{{ partial:quicklinks }}` door:

```antlers
{{ partial:quicklinks :brochure="brochure" }}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "QuicklinksSectionTest|QuicklinksContentTest|PageQuicklinksTest"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/quicklinks.antlers.html resources/views/partials/pageQuicklinks.antlers.html resources/views/ranges/show.antlers.html resources/views/products/show.antlers.html tests/Feature/Sections/QuicklinksSectionTest.php tests/Feature/Content/QuicklinksContentTest.php
git commit -m "feat: geef de brochure expliciet door en pas het kolomaantal aan"
```

---

## Task 6: Mediaschakelaar op `text_image`

Alleen de **optie**, niet de vormgeving van luloop. De sectie behoudt zijn huidige opmaak, klassen en positielogica.

**Files:**
- Modify: `resources/fieldsets/page_builder.yaml:204-235` (de `text_image`-set)
- Modify: `resources/views/partials/sections/textImage.antlers.html:22-26`
- Test: `tests/Feature/Sections/TextImageMediaTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: veldhandles `media` (`none` | `image` | `video`, standaard `image`) en `video` op de `text_image`-set.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sections/TextImageMediaTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class TextImageMediaTest extends SectionTestCase
{
    public function test_it_renders_a_video_when_the_media_switch_is_video(): void
    {
        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'video',
            'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<picture', $html);
    }

    public function test_it_renders_nothing_in_the_media_column_when_the_switch_is_none(): void
    {
        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'none',
            'image' => 'dummy-images/test-img-1.jpg',
        ]);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter TextImageMediaTest`
Expected: FAIL — er is geen videotak; bij `media: none` rendert de afbeelding nog steeds.

- [ ] **Step 3: Voeg de velden toe aan de set**

In `resources/fieldsets/page_builder.yaml`, in de `text_image`-set, direct ná `import: image`:

```yaml
                -
                  handle: media
                  field:
                    type: button_group
                    display: Media
                    default: image
                    localizable: true
                    options:
                      none: Geen
                      image: Afbeelding
                      video: Video
                -
                  handle: video
                  field:
                    type: video
                    display: Video
                    localizable: true
                    if:
                      media: 'equals video'
```

Zet daarnaast op het bestaande `import: image` een conditie door het te vervangen door een expliciet veld dat alleen bij `media: image` verschijnt:

```yaml
                -
                  handle: image
                  field:
                    type: assets
                    display: Image
                    container: assets
                    max_files: 1
                    localizable: true
                    if:
                      media: 'equals image'
```

- [ ] **Step 4: Voeg de videotak toe aan de template**

In `resources/views/partials/sections/textImage.antlers.html`, vervang regels 22–26 door:

```antlers
            {{ if media == 'video' and video }}
                <div class="{{ image_column_class }}">
                    {{ partial:video }}
                </div>
            {{ elseif media != 'none' and image }}
                <div class="{{ image_column_class }}">
                    {{ img :src="image" ratio="4/3" sm:ratio="5/4" max_width="1600" :sizes="image_sizes" class="rounded-sm" }}
                </div>
            {{ /if }}
```

`media != 'none'` in plaats van `media == 'image'`, zodat bestaande secties zonder `media`-waarde hun afbeelding blijven tonen.

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter TextImageMediaTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Controleer dat bestaande secties niet breken**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "PageBuilder|TextImage"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add resources/fieldsets/page_builder.yaml resources/views/partials/sections/textImage.antlers.html tests/Feature/Sections/TextImageMediaTest.php
git commit -m "feat: media-schakelaar tussen afbeelding en video op text_image"
```

---

## Task 7: Embed-set en de leningsimulator in de footer

**Files:**
- Modify: `resources/fieldsets/page_builder.yaml`
- Create: `resources/views/partials/sections/embed.antlers.html`
- Create: `content/collections/pages/nl/simuleer-je-lening.md`
- Create: `content/navigation/footer.yaml`
- Create: `content/trees/navigation/footer.yaml`
- Modify: `resources/views/partials/footer.antlers.html:25-36`
- Test: `tests/Feature/Sections/EmbedSectionTest.php`
- Test: `tests/Feature/Sections/FooterTest.php` (aanvullen)

**Interfaces:**
- Consumes: sitehandle `nl` uit Task 1.
- Produces: page-builder-set `embed` met handles `title`, `url`, `height`. Navigatiehandle `footer`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sections/EmbedSectionTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class EmbedSectionTest extends SectionTestCase
{
    public function test_it_renders_an_iframe_with_the_given_url_and_height(): void
    {
        $html = $this->render('{{ partial:sections/embed }}', [
            'title' => 'Simuleer je lening',
            'url' => 'https://www.kbc.be/co2-p-wgt/visual-widget/resources/0001/nl/',
            'height' => 900,
        ]);

        $this->assertStringContainsString('data-section="embed"', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('https://www.kbc.be/co2-p-wgt/visual-widget/resources/0001/nl/', $html);
        $this->assertStringContainsString('height="900"', $html);
        $this->assertStringContainsString('Simuleer je lening', $html);
    }

    public function test_it_renders_nothing_without_a_url(): void
    {
        $html = $this->render('{{ partial:sections/embed }}', ['title' => 'Leeg']);

        $this->assertSame('', trim($html));
    }
}
```

Voeg aan `tests/Feature/Sections/FooterTest.php` toe:

```php
    public function test_the_loan_simulator_sits_in_the_footer(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertStringContainsString('Simuleer je lening', $html);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "EmbedSectionTest|FooterTest"`
Expected: FAIL — `sections/embed` bestaat niet.

- [ ] **Step 3: Voeg de `embed`-set toe**

In `resources/fieldsets/page_builder.yaml`, als nieuwe set binnen `sets.new_set_group.sets`, ná `text_image`:

```yaml
            embed:
              display: Embed
              fields:
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                    localizable: true
                -
                  handle: url
                  field:
                    type: text
                    display: URL
                    localizable: true
                    instructions: 'Volledige https-URL van de externe widget.'
                    required: true
                    validate:
                      - required
                      - 'url'
                -
                  handle: height
                  field:
                    type: integer
                    display: Hoogte
                    default: 900
                    instructions: 'Hoogte van het venster in pixels.'
```

- [ ] **Step 4: Schrijf de sectietemplate**

Create `resources/views/partials/sections/embed.antlers.html`:

```antlers
{{#
    Externe widget in een iframe. `loading="lazy"` omdat de embed nooit boven de
    vouw staat, en een `title` omdat een iframe zonder toegankelijke naam voor
    schermlezers een naamloos document is.
#}}
{{ frame_title = title ?? 'Externe widget' }}
{{ if url }}
    <section class="section section--default" data-section="embed">
        <div class="container">
            <div class="section-y-gap">
                {{ if title }}
                    <h2 class="text-center">{{ title | entities }}</h2>
                {{ /if }}

                <iframe
                    src="{{ url }}"
                    title="{{ frame_title | entities }}"
                    height="{{ height ?? 900 }}"
                    loading="lazy"
                    class="w-full rounded-md border-0"></iframe>
            </div>
        </div>
    </section>
{{ /if }}
```

- [ ] **Step 5: Maak de pagina-entry**

Create `content/collections/pages/nl/simuleer-je-lening.md`:

```yaml
---
id: 4f1c9d20-0001-4b3e-9a5c-2d7e8f091a01
blueprint: page
title: 'Simuleer je lening'
text: 'Benieuwd wat je verbouwing maandelijks kost? Simuleer vrijblijvend je lening en vraag ze meteen aan.'
seo_noindex: false
page_builder:
  -
    id: leningsimulator
    type: embed
    title: 'Simuleer je lening'
    url: 'https://www.kbc.be/co2-p-wgt/visual-widget/resources/0001/nl/?creditAmount=10000&intermediaryName=Winsol&intermediaryTypeCode=0287&creditPurposeCode=60293&hideRequestLoanButton=false&isCreditAmountEditable=true&isLogoShown=true#/loa/simulation'
    height: 900
---
```

- [ ] **Step 6: Maak de footernavigatie**

Create `content/navigation/footer.yaml`:

```yaml
title: Footer
collections:
  - pages
```

Create `content/trees/navigation/footer.yaml` (gebruik het id uit Step 5):

```yaml
tree:
  -
    id: 4f1c9d20-0001-4b3e-9a5c-2d7e8f091a01
```

Een eigen navigatie, geen uitbreiding van `main`: de leningsimulator hoort in de footer en níét in de header, dus hij kan niet in dezelfde boom.

- [ ] **Step 7: Render de footernavigatie**

In `resources/views/partials/footer.antlers.html`, binnen de bedrijfskolom, direct ná `{{ /nav:main }}` en vóór `</ul>`:

```antlers
                        {{ nav:footer }}
                            <li>
                                <a href="{{ entry.url }}" class="text-white/60 transition-opacity hover:opacity-80">
                                    {{ title }}
                                </a>
                            </li>
                        {{ /nav:footer }}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "EmbedSectionTest|FooterTest"`
Expected: PASS

- [ ] **Step 9: Controleer dat de simulator niet in de header staat**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "NavigationTest|MegaMenuTest"`
Expected: PASS — beide lezen `nav:main`, waarin de simulator niet zit.

- [ ] **Step 10: Commit**

```bash
git add resources/fieldsets/page_builder.yaml resources/views/partials/sections/embed.antlers.html resources/views/partials/footer.antlers.html content/collections/pages/ content/navigation/ content/trees/navigation/ tests/Feature/Sections/EmbedSectionTest.php tests/Feature/Sections/FooterTest.php
git commit -m "feat: embed-set met de KBC-leningsimulator in de footer"
```

---

## Task 8: `WatermarkDetector`

Pure beeldlogica, los van Statamic. Drie commando's gebruiken hem, dus hij staat apart en wordt als unit getest.

**Files:**
- Create: `app/Services/WatermarkDetector.php`
- Create: `app/Services/WatermarkResult.php`
- Test: `tests/Unit/WatermarkDetectorTest.php`
- Test fixtures: `tests/fixtures/images/watermarked.jpg`, `tests/fixtures/images/clean.jpg`

**Interfaces:**
- Consumes: niets.
- Produces:
  - `WatermarkResult` — readonly, `bool $hasWatermark`, `float $cornerWhiteFraction`, `?array $box` met sleutels `x`, `y`, `width`, `height` (pixels, `null` als er geen watermerk is).
  - `WatermarkDetector::detect(string $bytes): WatermarkResult`.

- [ ] **Step 1: Zet de fixtures klaar**

```bash
cp "/Users/arnaud/Documents/winsol/afbeeldingen/terrasoverkappingen/LR 2/Winsol_2019_Mol_Pergola SO! (23).jpg" tests/fixtures/images/watermarked.jpg
cp "/Users/arnaud/Documents/winsol/afbeeldingen/terrasoverkappingen/web/Realisatie-Pergola-SO-terrasoverkapping-Drongen30.jpg" tests/fixtures/images/clean.jpg
```

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/WatermarkDetectorTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\WatermarkDetector;
use Tests\TestCase;

class WatermarkDetectorTest extends TestCase
{
    private function detect(string $fixture): \App\Services\WatermarkResult
    {
        return (new WatermarkDetector)->detect(
            file_get_contents(base_path("tests/fixtures/images/{$fixture}"))
        );
    }

    public function test_it_finds_the_winsol_watermark(): void
    {
        $result = $this->detect('watermarked.jpg');

        $this->assertTrue($result->hasWatermark);
        $this->assertGreaterThan(0.08, $result->cornerWhiteFraction);
    }

    public function test_it_leaves_a_clean_photo_alone(): void
    {
        $result = $this->detect('clean.jpg');

        $this->assertFalse($result->hasWatermark);
        $this->assertNull($result->box);
    }

    public function test_the_box_sits_in_the_bottom_right_quadrant(): void
    {
        $result = $this->detect('watermarked.jpg');
        [$width, $height] = getimagesize(base_path('tests/fixtures/images/watermarked.jpg'));

        $this->assertGreaterThan($width * 0.5, $result->box['x']);
        $this->assertGreaterThan($height * 0.5, $result->box['y']);
        $this->assertLessThan($height * 0.3, $result->box['height']);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter WatermarkDetectorTest`
Expected: FAIL — `Class "App\Services\WatermarkDetector" not found`.

- [ ] **Step 4: Schrijf `WatermarkResult`**

Create `app/Services/WatermarkResult.php`:

```php
<?php

namespace App\Services;

readonly class WatermarkResult
{
    /**
     * @param  array{x: int, y: int, width: int, height: int}|null  $box
     */
    public function __construct(
        public bool $hasWatermark,
        public float $cornerWhiteFraction,
        public ?array $box = null,
    ) {}
}
```

- [ ] **Step 5: Schrijf `WatermarkDetector`**

Create `app/Services/WatermarkDetector.php`:

```php
<?php

namespace App\Services;

class WatermarkDetector
{
    /**
     * Het Winsol-woordmerk staat wit en op een vaste plek rechtsonder. De
     * witfractie daar wordt afgezet tegen dezelfde zone linksonder: dat vangt
     * foto's op met een lichte lucht of witte gevel in die hoek, die anders
     * vals positief zouden zijn.
     */
    private const CORNER_X = 0.74;

    private const CORNER_Y = 0.845;

    private const WHITE_THRESHOLD = 238;

    private const MIN_FRACTION = 0.08;

    private const MIN_RATIO = 4.0;

    public function detect(string $bytes): WatermarkResult
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return new WatermarkResult(false, 0.0);
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            $cornerX = (int) ($width * self::CORNER_X);
            $cornerY = (int) ($height * self::CORNER_Y);
            $controlWidth = $width - $cornerX;

            $corner = $this->whitePixels($image, $cornerX, $cornerY, $width, $height);
            $control = $this->whitePixels($image, 0, $cornerY, $controlWidth, $height);

            $area = max(1, ($width - $cornerX) * ($height - $cornerY));
            $cornerFraction = $corner['count'] / $area;
            $controlFraction = $control['count'] / $area;

            $has = $cornerFraction >= self::MIN_FRACTION
                && $cornerFraction >= self::MIN_RATIO * $controlFraction;

            return new WatermarkResult(
                hasWatermark: $has,
                cornerWhiteFraction: $cornerFraction,
                box: $has ? $corner['box'] : null,
            );
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @return array{count: int, box: array{x: int, y: int, width: int, height: int}}
     */
    private function whitePixels(\GdImage $image, int $fromX, int $fromY, int $toX, int $toY): array
    {
        $count = 0;
        $minX = $toX;
        $minY = $toY;
        $maxX = $fromX;
        $maxY = $fromY;

        for ($y = $fromY; $y < $toY; $y++) {
            for ($x = $fromX; $x < $toX; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $luma = (int) (
                    0.299 * (($rgb >> 16) & 0xFF)
                    + 0.587 * (($rgb >> 8) & 0xFF)
                    + 0.114 * ($rgb & 0xFF)
                );

                if ($luma <= self::WHITE_THRESHOLD) {
                    continue;
                }

                $count++;
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        return [
            'count' => $count,
            'box' => [
                'x' => $minX,
                'y' => $minY,
                'width' => max(0, $maxX - $minX),
                'height' => max(0, $maxY - $minY),
            ],
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter WatermarkDetectorTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Services/WatermarkDetector.php app/Services/WatermarkResult.php tests/Unit/WatermarkDetectorTest.php tests/fixtures/images/
git commit -m "feat: watermerkdetector met bounding box"
```

---

## Task 9: `winsol:import-images`

**Files:**
- Create: `app/Console/Commands/ImportImages.php`
- Create: `tests/Concerns/CreatesTemporaryContent.php`
- Modify: `resources/blueprints/assets/assets.yaml`
- Test: `tests/Feature/Commands/ImportImagesTest.php`

**Interfaces:**
- Consumes: `WatermarkDetector::detect()` uit Task 8.
- Produces:
  - assets in container `assets`, map `<range>/`, met assetdata `watermark` (bool) en `watermark_box` (string `"x,y,w,h"` of leeg). Task 10 en 11 lezen deze twee sleutels.
  - trait `Tests\Concerns\CreatesTemporaryContent` met `fakeAssetDisk(): void`, `temporaryEntry(string $collection, string $slug, array $data): Entry` en `deleteTemporaryEntries(): void`. Task 10 en 11 gebruiken dezelfde trait.

- [ ] **Step 1: Schrijf de opruimtrait**

De assets-container staat op de `r2`-disk en `TestCase` rolt geen content terug. Zonder deze trait zouden de commandotests echte bestanden naar R2 schrijven en entries in `content/` achterlaten. `AssetUploadCompressionTest` doet het al zo voor assets; entries komen erbij.

Create `tests/Concerns/CreatesTemporaryContent.php`:

```php
<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

trait CreatesTemporaryContent
{
    /** @var list<string> */
    private array $temporaryEntryIds = [];

    /**
     * Isoleert de bestanden van de assets-container. Haal de container daarna
     * met `find()` op en nooit met `make()->save()` — dat schrijft
     * `content/assets/{handle}.yaml` terug naar de werkkopie.
     */
    protected function fakeAssetDisk(): void
    {
        Storage::fake('r2');
    }

    protected function temporaryEntry(string $collection, string $slug, array $data): EntryContract
    {
        $entry = Entry::make()
            ->collection($collection)
            ->locale('nl')
            ->slug($slug)
            ->data($data);

        $entry->save();

        $this->temporaryEntryIds[] = $entry->id();

        return $entry;
    }

    protected function deleteTemporaryEntries(): void
    {
        foreach ($this->temporaryEntryIds as $id) {
            Entry::find($id)?->delete();
        }

        $this->temporaryEntryIds = [];
    }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Commands/ImportImagesTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Statamic\Facades\AssetContainer;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class ImportImagesTest extends TestCase
{
    use CreatesTemporaryContent;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeAssetDisk();

        $this->source = storage_path('framework/testing/import-source');
        File::ensureDirectoryExists($this->source);
        File::copy(base_path('tests/fixtures/images/watermarked.jpg'), $this->source.'/watermarked.jpg');
        File::copy(base_path('tests/fixtures/images/clean.jpg'), $this->source.'/clean.jpg');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->source);
        $this->deleteTemporaryEntries();

        parent::tearDown();
    }

    public function test_it_flags_the_watermarked_image_and_leaves_the_clean_one_unflagged(): void
    {
        $this->artisan('winsol:import-images', [
            'source' => $this->source,
            'folder' => 'testrange',
        ])->assertExitCode(0);

        $container = AssetContainer::find('assets');

        $watermarked = $container->asset('testrange/watermarked.jpg');
        $clean = $container->asset('testrange/clean.jpg');

        $this->assertNotNull($watermarked, 'De watermerkfoto is niet geimporteerd');
        $this->assertNotNull($clean, 'De schone foto is niet geimporteerd');

        $this->assertTrue($watermarked->get('watermark'));
        $this->assertNotEmpty($watermarked->get('watermark_box'));

        $this->assertFalse($clean->get('watermark'));
    }

    public function test_it_skips_files_that_are_already_there(): void
    {
        $this->artisan('winsol:import-images', ['source' => $this->source, 'folder' => 'testrange']);

        $this->artisan('winsol:import-images', ['source' => $this->source, 'folder' => 'testrange'])
            ->expectsOutputToContain('2 overgeslagen')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter ImportImagesTest`
Expected: FAIL — het commando `winsol:import-images` bestaat niet.

- [ ] **Step 4: Voeg de velden toe aan de assets-blueprint**

In `resources/blueprints/assets/assets.yaml`:

```yaml
title: Asset
fields:
  -
    handle: alt
    field:
      display: 'Alt Text'
      type: text
      instructions: 'Description of the image'
  -
    handle: watermark
    field:
      display: Watermerk
      type: toggle
      instructions: 'Automatisch gezet bij import. Wordt door winsol:clean-watermarks weer uitgezet zodra het logo weggesneden is.'
  -
    handle: watermark_box
    field:
      display: 'Watermerkvlak'
      type: text
      instructions: 'x,y,breedte,hoogte in pixels. Automatisch gezet bij import.'
```

- [ ] **Step 5: Schrijf het commando**

Create `app/Console/Commands/ImportImages.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\WatermarkDetector;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Statamic\Facades\AssetContainer;
use Symfony\Component\Finder\Finder;

class ImportImages extends Command
{
    protected $signature = 'winsol:import-images
        {source : Map met de foto\'s die geimporteerd moeten worden}
        {folder : Doelmap binnen de assets-container, bijvoorbeeld de range-slug}';

    protected $description = 'Importeert foto\'s naar de assets-container en markeert watermerken';

    public function handle(WatermarkDetector $detector): int
    {
        $source = rtrim($this->argument('source'), '/');
        $folder = trim($this->argument('folder'), '/');

        if (! is_dir($source)) {
            $this->error("Bronmap bestaat niet: {$source}");

            return self::FAILURE;
        }

        $container = AssetContainer::find('assets');

        $imported = 0;
        $skipped = 0;
        $flagged = 0;

        // Uploaden via de container in plaats van rechtstreeks naar de disk: dat
        // vuurt AssetUploaded, waardoor CompressUploadedAsset zijn werk doet.
        // Een kaal Storage::put zou ongecomprimeerde originelen op R2 zetten.
        foreach (Finder::create()->files()->in($source)->name('/\.(jpe?g|png)$/i') as $file) {
            $path = $folder.'/'.$file->getFilename();

            if ($container->asset($path)) {
                $skipped++;

                continue;
            }

            $asset = $container->makeAsset($path)->upload(
                new UploadedFile($file->getRealPath(), $file->getFilename(), null, null, true)
            );

            $result = $detector->detect($asset->disk()->get($asset->path()));

            $asset->set('watermark', $result->hasWatermark);
            $asset->set('watermark_box', $result->box
                ? implode(',', [$result->box['x'], $result->box['y'], $result->box['width'], $result->box['height']])
                : '');
            $asset->save();

            $imported++;
            $flagged += $result->hasWatermark ? 1 : 0;
        }

        $this->info("{$imported} geimporteerd, {$skipped} overgeslagen, {$flagged} met watermerk.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter ImportImagesTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/ImportImages.php resources/blueprints/assets/assets.yaml tests/Feature/Commands/ImportImagesTest.php
git commit -m "feat: winsol:import-images met watermerkmarkering"
```

---

## Task 10: `winsol:clean-watermarks`

Snijdt het watermerk weg bij de assets die **werkelijk door entries gebruikt worden** — niet bij de hele container.

**Files:**
- Create: `app/Console/Commands/CleanWatermarks.php`
- Create: `app/Services/UsedAssetFinder.php`
- Test: `tests/Feature/Commands/CleanWatermarksTest.php`

**Interfaces:**
- Consumes: assetdata `watermark` en `watermark_box` uit Task 9.
- Produces: `UsedAssetFinder::paths(): Collection<string>` — assetpaden waar minstens één entryveld naar wijst. Task 11 gebruikt dezelfde klasse.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Commands/CleanWatermarksTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Statamic\Facades\AssetContainer;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class CleanWatermarksTest extends TestCase
{
    use CreatesTemporaryContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeAssetDisk();
    }

    protected function tearDown(): void
    {
        $this->deleteTemporaryEntries();

        parent::tearDown();
    }

    private function importFixture(string $fixture, string $name): void
    {
        $dir = storage_path('framework/testing/clean-source');
        File::ensureDirectoryExists($dir);
        File::copy(base_path("tests/fixtures/images/{$fixture}"), "{$dir}/{$name}");

        $this->artisan('winsol:import-images', ['source' => $dir, 'folder' => 'testrange']);

        File::deleteDirectory($dir);
    }

    /**
     * Statamic's Asset kent geen fresh(); opnieuw ophalen gaat via de container.
     */
    private function asset(string $path): \Statamic\Assets\Asset
    {
        return AssetContainer::find('assets')->asset($path);
    }

    private function useInEntry(string $path): void
    {
        $this->temporaryEntry('products', 'testproduct', [
            'title' => 'Testproduct',
            'image' => $path,
        ]);
    }

    public function test_it_only_touches_watermarked_assets_that_an_entry_uses(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->importFixture('watermarked.jpg', 'unused.jpg');
        $this->useInEntry('testrange/used.jpg');

        $heightBefore = $this->asset('testrange/used.jpg')->height();

        $this->artisan('winsol:clean-watermarks')
            ->expectsOutputToContain('1 bijgesneden')
            ->assertExitCode(0);

        $this->assertLessThan($heightBefore, $this->asset('testrange/used.jpg')->height(), 'De gebruikte foto is niet bijgesneden');
        $this->assertFalse($this->asset('testrange/used.jpg')->get('watermark'), 'De vlag is niet omgezet');

        $this->assertTrue($this->asset('testrange/unused.jpg')->get('watermark'), 'De ongebruikte foto had ongemoeid moeten blijven');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $heightBefore = $this->asset('testrange/used.jpg')->height();

        $this->artisan('winsol:clean-watermarks', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($heightBefore, $this->asset('testrange/used.jpg')->height());
        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }

    public function test_list_prints_the_filenames_without_changing_anything(): void
    {
        $this->importFixture('watermarked.jpg', 'used.jpg');
        $this->useInEntry('testrange/used.jpg');

        $this->artisan('winsol:clean-watermarks', ['--list' => true])
            ->expectsOutputToContain('testrange/used.jpg')
            ->assertExitCode(0);

        $this->assertTrue($this->asset('testrange/used.jpg')->get('watermark'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter CleanWatermarksTest`
Expected: FAIL — het commando bestaat niet.

- [ ] **Step 3: Schrijf `UsedAssetFinder`**

Create `app/Services/UsedAssetFinder.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Statamic\Facades\Entry;

class UsedAssetFinder
{
    /**
     * Alle assetpaden waar minstens een entryveld naar wijst.
     *
     * De waarden worden uit de ruwe data gehaald in plaats van uit de
     * augmented velden: een assetsveld bewaart daar simpelweg zijn pad, en zo
     * hoeft er niet per blueprint uitgezocht te worden welk veld een asset is.
     *
     * @return Collection<int, string>
     */
    public function paths(): Collection
    {
        return Entry::query()->get()
            ->flatMap(fn ($entry) => $this->extract($entry->data()->all()))
            ->unique()
            ->values();
    }

    private function extract(mixed $value): array
    {
        if (is_string($value)) {
            return preg_match('/\.(jpe?g|png|webp|pdf)$/i', $value) ? [$value] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)->flatMap(fn ($item) => $this->extract($item))->all();
    }
}
```

- [ ] **Step 4: Schrijf het commando**

Create `app/Console/Commands/CleanWatermarks.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\UsedAssetFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\AssetContainer;

class CleanWatermarks extends Command
{
    protected $signature = 'winsol:clean-watermarks
        {--dry-run : Toont wat er zou gebeuren, zonder iets te wijzigen}
        {--list : Schrijft alleen de bestandsnamen uit, voor een aanvraag bij Winsol}';

    protected $description = 'Snijdt het Winsol-watermerk weg bij de foto\'s die entries werkelijk gebruiken';

    public function handle(UsedAssetFinder $finder): int
    {
        $container = AssetContainer::find('assets');

        $targets = $finder->paths()
            ->map(fn (string $path) => $container->asset($path))
            ->filter()
            ->filter(fn ($asset) => (bool) $asset->get('watermark'));

        if ($this->option('list')) {
            $targets->each(fn ($asset) => $this->line($asset->path()));
            $this->info("{$targets->count()} foto's met watermerk in gebruik.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $targets->each(fn ($asset) => $this->line("zou bijsnijden: {$asset->path()}"));
            $this->info("{$targets->count()} zouden bijgesneden worden.");

            return self::SUCCESS;
        }

        $cropped = 0;

        foreach ($targets as $asset) {
            $box = array_map('intval', explode(',', (string) $asset->get('watermark_box')));

            if (count($box) !== 4) {
                $this->warn("Geen bruikbaar watermerkvlak, overgeslagen: {$asset->path()}");

                continue;
            }

            [, $boxY] = $box;

            $image = @imagecreatefromstring($asset->disk()->get($asset->path()));

            if ($image === false) {
                $this->warn("Kon niet lezen, overgeslagen: {$asset->path()}");

                continue;
            }

            // Snij tot net boven het watermerk. Een marge van vier pixels vangt
            // de antialiasing rond de letters op, die net onder de witdrempel
            // valt en anders als grijze rand achterblijft.
            $keepHeight = max(1, $boxY - 4);
            $croppedImage = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => imagesx($image), 'height' => $keepHeight]);
            imagedestroy($image);

            if ($croppedImage === false) {
                $this->warn("Bijsnijden mislukt, overgeslagen: {$asset->path()}");

                continue;
            }

            // Terugschrijven in het formaat van het bestand zelf: een png-pad
            // met jpeg-bytes erin zou Glide en elke browser misleiden.
            ob_start();

            if (strtolower($asset->extension()) === 'png') {
                imagepng($croppedImage);
            } else {
                imagejpeg($croppedImage, null, (int) config('image-compression.jpeg_quality'));
            }

            $bytes = ob_get_clean();
            imagedestroy($croppedImage);

            $asset->disk()->put($asset->path(), $bytes);

            $asset->set('watermark', false);
            $asset->set('watermark_box', '');
            Cache::forget($asset->metaCacheKey());
            $asset->writeMeta($asset->generateMeta());
            $asset->save();

            $cropped++;
        }

        $this->call('statamic:glide:clear');

        $this->info("{$cropped} bijgesneden.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter CleanWatermarksTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/CleanWatermarks.php app/Services/UsedAssetFinder.php tests/Feature/Commands/CleanWatermarksTest.php
git commit -m "feat: winsol:clean-watermarks snijdt alleen gebruikte foto's bij"
```

---

## Task 11: `winsol:image-gaps`

De boodschappenlijst én de livegangcontrole: bij oplevering moet de uitvoer leeg zijn.

**Files:**
- Create: `app/Console/Commands/ImageGaps.php`
- Test: `tests/Feature/Commands/ImageGapsTest.php`

**Interfaces:**
- Consumes: trait `Tests\Concerns\CreatesTemporaryContent` uit Task 9. **Niet** `UsedAssetFinder`: die geeft enkel paden terug, terwijl dit rapport ook de entry en het veld moet noemen — dus loopt dit commando zelf over de entries.
- Produces: exitcode 1 zolang er placeholders in gebruik zijn, 0 als er geen enkele meer is. Daarmee bruikbaar als poort in een opleveringsscript.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Commands/ImageGapsTest.php`:

```php
<?php

namespace Tests\Feature\Commands;

use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class ImageGapsTest extends TestCase
{
    use CreatesTemporaryContent;

    protected function tearDown(): void
    {
        $this->deleteTemporaryEntries();

        parent::tearDown();
    }

    public function test_it_reports_an_entry_that_still_uses_a_placeholder(): void
    {
        $this->temporaryEntry('products', 'nog-geen-beeld', [
            'title' => 'Nog geen beeld',
            'image' => 'placeholder/terras.jpg',
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('nog-geen-beeld')
            ->expectsOutputToContain('placeholder/terras.jpg')
            ->assertExitCode(1);
    }

    public function test_it_exits_clean_when_nothing_points_at_a_placeholder(): void
    {
        $this->temporaryEntry('products', 'beeld-in-orde', [
            'title' => 'Beeld in orde',
            'image' => 'pergolas/echte-foto.jpg',
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('Geen beeldgaten')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter ImageGapsTest`
Expected: FAIL — het commando bestaat niet.

- [ ] **Step 3: Schrijf het commando**

Create `app/Console/Commands/ImageGaps.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class ImageGaps extends Command
{
    protected $signature = 'winsol:image-gaps';

    protected $description = 'Somt elk entryveld op dat nog naar een placeholder wijst';

    private const PLACEHOLDER_PREFIX = 'placeholder/';

    public function handle(): int
    {
        $rows = [];

        foreach (Entry::query()->get() as $entry) {
            foreach ($entry->data()->all() as $handle => $value) {
                foreach ($this->placeholders($value) as $path) {
                    $rows[] = [$entry->collectionHandle(), $entry->slug(), $handle, $path];
                }
            }
        }

        if ($rows === []) {
            $this->info('Geen beeldgaten.');

            return self::SUCCESS;
        }

        $this->table(['Collectie', 'Entry', 'Veld', 'Placeholder'], $rows);
        $this->warn(count($rows).' beeldgaten open.');

        return self::FAILURE;
    }

    private function placeholders(mixed $value): array
    {
        if (is_string($value)) {
            return str_starts_with($value, self::PLACEHOLDER_PREFIX) ? [$value] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)->flatMap(fn ($item) => $this->placeholders($item))->all();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter ImageGapsTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Draai de volledige suite**

Run: `vendor/bin/phpunit -d memory_limit=1G`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ImageGaps.php tests/Feature/Commands/ImageGapsTest.php
git commit -m "feat: winsol:image-gaps rapporteert openstaande placeholders"
```

---

## Volgorde en afhankelijkheden

```
Task 1 (multisite)
  └─ Task 2 (route) ─┐
     Task 3 (brochureveld) ─ Task 4 (brochurekaart) ─ Task 5 (doorgeven + kolommen)
     Task 6 (mediaschakelaar)
     Task 7 (embed + footer)
     Task 8 (detector) ─ Task 9 (import) ─ Task 10 (clean) ─ Task 11 (gaps)
```

Task 1 gaat eerst — hij verplaatst elk contentbestand. Task 2 tot 7 zijn onderling onafhankelijk behalve de keten 3→4→5. Task 8 tot 11 vormen een eigen keten.

**Alleen Task 1, 2, 3 en 9 zijn nodig vóór batch 1 van project 2.** Task 10 en 11 zijn pas nodig bij oplevering; ze mogen na de eerste contentbatches komen als dat beter uitkomt.

---

## Afwijkingen tijdens de uitvoering

De codeblokken hierboven zijn het ontwerp van vóór de uitvoering en zijn bewust niet herschreven — ze laten zien wat er bedacht was. Onderstaande punten zijn wat er uiteindelijk in de code staat en waarom het afwijkt. Bij twijfel geldt de code, niet het plan.

### Task 3, 4 en 5 — de brochure

- **Het `brochure`-veld staat alleen op `products.yaml`, niet op `ranges.yaml`.** Task 3 zette het op allebei; Task 5 haalde `{{ partial:quicklinks }}` van `ranges/show` af, waarna het rangeveld geen enkele consument meer had terwijl de instructietekst wél een knop beloofde. De eigenaar heeft besloten dat alleen producten een brochure krijgen — de dertien beschikbare pdf's zijn vrijwel allemaal productniveau. Een terugval van het product naar de brochure van zijn range is afgewezen. `BrochureFieldTest` pint beide kanten vast: de configuratie op products, en de afwezigheid op ranges.

- **`download` werd `target="_blank" rel="noopener"`.** De brochures staan op R2, en dat is een andere origin dan de site: een browser negeert `download` cross-origin. Het attribuut beloofde dus gedrag dat niet gebeurt. De knop opent de brochure nu in een nieuw tabblad; de test die op de string `download` toetste is omgedraaid.
- **De quicklinks zijn van `ranges/show` verwijderd.** Het plan gaf ze daar `:brochure="brochure"` mee; in de uiteindelijke opbouw staan er op de rangepagina geen quicklinks meer. De doorgifte zelf staat wel in `quicklinks.antlers.html` en `pageQuicklinks.antlers.html`.

### Task 9 — `winsol:import-images`

- **Sanering in plaats van een kale bestaat-al-check.** Statamic maakt bij het uploaden zelf `IMG_0001.JPG` tot `img_0001.jpg`. Toetste de check op de ruwe naam, dan vond een tweede run niets terug en plakte Statamic er een timestamp-suffix achter — met als gevolg dat elke run de hele bronmap opnieuw zou uploaden. `sanitizedPath()` bootst daarom `AssetUploader::uploadPath()` na, met diens eigen `getSafeFilename()`.
- **Botsingsdetectie binnen één run.** `Finder::in()` recurseert, dus twee bestanden met dezelfde naam in verschillende submappen komen op hetzelfde doelpad uit. Dat telt nu als botsing met exitcode 1, in plaats van dat de tweede stil als "overgeslagen" wegvalt.
- **Uploaden via een wegwerpkopie in `sys_get_temp_dir()`.** `AssetUploader` *verplaatst* zijn bronbestand bij een console-upload; wijst `UploadedFile` naar het echte pad in de bronmap, dan is het origineel na de import weg. Dit is de gevaarlijkste val in dit werk en staat daarom ook in `README.md`.
- **`source_filename` op de assets-blueprint.** De sanering is niet omkeerbaar (`Réalisation Été - Screens 04.JPG` wordt `realisation-ete--screens-04.jpg`), waardoor `--list` van Task 10 een naam opleverde die bij Winsol niet meer terug te zoeken was. De oorspronkelijke naam wordt nu bij de import bewaard.

### Task 10 — `winsol:clean-watermarks`

- **Intervention Image in plaats van kale GD.** Een `imagejpeg()` op een png-pad zou jpeg-bytes onder een png-extensie schrijven; Intervention kiest de encoder per extensie en behoudt het alphakanaal van een png.
- **Een bevestiging vóór de bulkactie.** De foto's op R2 worden onomkeerbaar overschreven zonder terugvaloptie. Zonder interactieve terminal weigert het commando te draaien tenzij `--force` meegegeven wordt; `confirm()` zou daar anders stilzwijgend een default teruggeven.
- **Clamps op het watermerkvlak.** Een box die boven de onderste ~25% van de foto begint, of die na begrenzing niets meer zou afsnijden, wordt overgeslagen. Zonder die grenzen sneed een onzinnig of verouderd vlak de foto tot één pixel terug, of zette het de vlag op "schoon" terwijl het watermerk nog zichtbaar was.
- **`UsedAssetFinder` levert ook assets, niet alleen paden.** Hij strookt het `asset::<container>::`-voorvoegsel van bard-nodes af, kijkt naast entries ook naar globals en taxonomietermen, en valt bij het opzoeken terug op een hoofdletterongevoelige match. Zonder die drie zag het commando een gewatermerkte foto niet die `winsol:image-gaps` wél telde.

### Task 11 — `winsol:image-gaps`

- **`$this->line()` in plaats van `$this->table()`.** `table()` wikkelt cellen op de gedetecteerde terminalbreedte, en die valt in een niet-interactieve run terug op 0 — precies de situatie waarvoor dit commando bedoeld is. Elk pad brak dan over meerdere regels en werd onbruikbaar als boodschappenlijst.
- **Een lijst voorvoegsels in plaats van één `placeholder/`.** De echte content gebruikt `dummy-images/` plus een paar losse bestanden zonder map. Met alleen `placeholder/` meldde de poort een schone site terwijl elke pagina nog dummybeeld toonde.
- **Ook de watermerkvlag.** `winsol:clean-watermarks` sluit ook met exitcode 0 af wanneer élke foto overgeslagen werd wegens een onbruikbaar vlak. De poort meldt daarom nu twee gescheiden lijsten — ontbrekend beeld en nog gewatermerkt beeld vragen om verschillende actie.
- **Gedeelde scan via `App\Services\ContentValueScanner`.** Het plan liet dit commando bewust zelf over de entries lopen. Dat leverde twee scans die dezelfde vraag anders beantwoordden; de gedeelde scanner levert nu bron, entry, veldpad én waarde, zodat `UsedAssetFinder` er evengoed op kan draaien.

### Task 2 — de productroute

`range_slug` levert bij een niet-oplosbare range niet langer `null` maar de sentinel `AppServiceProvider::UNRESOLVED_RANGE_SLUG`. Een lege waarde liet het middelste routesegment wegvallen, waarna `/aanbod/{{ range_slug }}/{{ slug }}` samenviel met de rangeroute `/aanbod/{slug}` en een product stil een HTTP 200 met de rangepagina teruggaf.

### Testinfrastructuur (staat nergens in het plan)

- **`tests/bootstrap.php`.** Vóór er één test draait wordt de testcache gewist en de Stache in een apart proces gewarmd. Zonder die warmup bouwt de eerste koude entry- of asset-query het `filter`-veld van een nog lopende Traverser-cyclus over, waarna een halve bestandenlijst verdwijnt — goed voor tientallen misleidende failures die niets met de oorzaak te maken hebben.
- **De `file_testing`-cachestore (`phpunit.xml`, `config/cache.php`).** Een eigen store op een eigen map, los van de `file`-store van de draaiende app. Anders wist elke testrun de runtime-cache van de app, en lekken `Storage::fake()`-assets uit een testrun de echte Stache in.
- **`fakeAssetDisk()` bewaart en herstelt `asset-list-contents-assets`.** Kaal vergeten van die sleutel forceert een herberekening tegen de actieve fake-disk, die daarna via `remember()` in de gedeelde store belandt en de rest van de suite corrumpeert.
- **`temporaryEntry()` ruimt op via `beforeApplicationDestroyed()`.** Een `tearDown()` in de testklasse wordt overgeslagen zodra een test er geen definieert of vroegtijdig afbreekt, en het residu blijft dan in de getrackte `content/`-map achter.
