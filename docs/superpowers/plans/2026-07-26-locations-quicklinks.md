# Locations- en quicklinks-component Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Twee zelfstandige Antlers-partials bouwen — `{{ partial:locations }}` (drie `/contact`-kaartjes naast een Leaflet-kaart met hover-zoom) en `{{ partial:quicklinks }}` (drie CTA-kaarten uit de collectie) — inclusief data, CSS, JS en tests.

**Architecture:** Beide partials nemen geen argumenten en lezen hun eigen collectie, zodat een include overal identiek is. Leaflet komt binnen via een dynamische import achter een IntersectionObserver, zodat pagina's zonder kaart niets betalen. De coördinaten hangen als `data-`attributen aan de kaartjes zelf, niet in een aparte JSON-blob.

**Tech Stack:** Statamic 6 / Laravel 12 / PHP 8.2, Antlers, Tailwind 4 (`@utility`-based), Vite 7, Leaflet 1.9, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-26-locations-quicklinks-design.md`

## Global Constraints

- **Geen enkele paginatemplate wijzigt.** De partials worden in deze opdracht nergens geïncludeerd. Alleen de bestanden in de tasks hieronder mogen aangeraakt worden.
- **Tailwind-klassen altijd volledig uitgeschreven.** Nooit een klassenaam samenstellen uit een variabele (`class="btn btn--{{ style }}"` is verboden) — Tailwind's scanner vindt runtime-samengestelde namen niet. Zie `sectionHeader.antlers.html` en `gridCta.antlers.html` voor de bestaande documentatie van deze valkuil.
- **Nooit `{{ var = ... }}` in een partial** voor iets dat per aanroep verschilt: Antlers-toewijzingen schrijven in de gedeelde render-cascade en lekken naar latere partials in dezelfde render. Lees `foo ?? 'default'` inline.
- **Hardcoded copy, letterlijk over te nemen:**
  - locations overline: `Bezoek ons`
  - locations titel: `Liever eerst zien en voelen?`
  - quicklinks titel: `Zet de volgende stap`
- **Alle nieuwe CSS-bestanden** krijgen een `@import` onderaan het `/* Components */`-blok in `resources/css/site.css`.
- **Commit-berichten in het Engels**, conventional-commit-prefix, en eindigen op:
  ```
  Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
  ```
- **Testcommando:** `php artisan test --filter=<TestClass>`

## Afwijkingen van de spec

Drie dingen die tijdens het uitwerken van dit plan naar boven kwamen en die de spec niet noemt. Ze zijn hier bewust opgenomen; de spec blijft verder leidend.

1. **De collecties worden `orderable`.** `{{ collection:locations }}` sorteert zonder structuur op `title`, wat "Aartselaar, Dilbeek, Sint-Pieters-Leeuw" oplevert in plaats van de designvolgorde. In Statamic 6 is een platte geordende collectie `structure: { max_depth: 1 }` plus een boombestand in `content/trees/collections/`. Dat geeft de redacteur meteen drag-and-drop-volgorde in de CP — wenselijk voor quicklinks, waar de volgorde bepaalt welke CTA de primaire is.
2. **De locatiekaart wordt een eigen partial** (`partials/locationCard.antlers.html`), zoals `rangeCard` en `projectCard` dat al zijn. Zonder die splitsing is de failure mode "locatie zonder coördinaten" niet te testen, want de collectie-entries zijn de fixtures en er hoort geen vierde neplocatie in de content te staan.
3. **De tegel-attributie staat buiten de `aria-hidden`-container.** Leaflet's eigen `attributionControl` rendert links *binnen* de kaart; die zouden dan focusbaar zijn maar verborgen voor screenreaders, wat een a11y-fout is. De attributie wordt daarom `false` gezet in JS en als een gewone regel onder de kaart in de partial gerenderd. Ze blijft verplicht — CARTO en OpenStreetMap eisen ze.

---

### Task 1: Locations-data

De collectie orderable maken, coördinaatvelden toevoegen en de drie vestigingen als entries aanmaken.

**Files:**
- Modify: `content/collections/locations.yaml`
- Modify: `resources/blueprints/collections/locations/locations.yaml`
- Create: `content/collections/locations/winsol-dilbeek.md`
- Create: `content/collections/locations/winsol-sint-pieters-leeuw.md`
- Create: `content/collections/locations/winsol-aartselaar.md`
- Create: `content/trees/collections/locations.yaml`
- Test: `tests/Feature/Content/LocationsContentTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: drie entries in de `locations`-collectie met de velden `name`, `street`, `number`, `postal_code`, `city`, `latitude` (float), `longitude` (float). Vaste volgorde: Dilbeek, Sint-Pieters-Leeuw, Aartselaar. Slugs: `winsol-dilbeek`, `winsol-sint-pieters-leeuw`, `winsol-aartselaar`. Task 2 rendert deze entries; Task 3 leest hun coördinaten uit de HTML.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Content/LocationsContentTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class LocationsContentTest extends TestCase
{
    public function test_every_location_exists_with_its_address_and_coordinates(): void
    {
        $expected = [
            'winsol-dilbeek' => [
                'name' => 'Winsol Dilbeek',
                'street' => 'Ninoofsesteenweg',
                'postal_code' => '1700',
                'city' => 'Dilbeek',
                'latitude' => 50.8631,
                'longitude' => 4.2564,
            ],
            'winsol-sint-pieters-leeuw' => [
                'name' => 'Winsol Sint-Pieters-Leeuw',
                'street' => 'Bergensesteenweg',
                'postal_code' => '1600',
                'city' => 'Sint-Pieters-Leeuw',
                'latitude' => 50.7789,
                'longitude' => 4.2432,
            ],
            'winsol-aartselaar' => [
                'name' => 'Winsol Aartselaar',
                'street' => 'Antwerpsesteenweg',
                'postal_code' => '2630',
                'city' => 'Aartselaar',
                'latitude' => 51.1342,
                'longitude' => 4.3831,
            ],
        ];

        foreach ($expected as $slug => $fields) {
            $entry = Entry::query()->where('collection', 'locations')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Locatie {$slug} ontbreekt");

            foreach ($fields as $handle => $value) {
                $this->assertSame($value, $entry->get($handle), "Veld {$handle} van {$slug} klopt niet");
            }

            // Het huisnummer is in het design een placeholder (`000`). Het staat
            // hier als string, niet als getal, zodat een leidende nul later niet
            // wegvalt bij het invullen van het echte nummer.
            $this->assertSame('000', $entry->get('number'), "Huisnummer van {$slug} klopt niet");
        }
    }

    public function test_the_locations_are_ordered_as_designed(): void
    {
        $slugs = Entry::query()
            ->where('collection', 'locations')
            ->orderBy('order')
            ->get()
            ->map->slug()
            ->all();

        $this->assertSame(
            ['winsol-dilbeek', 'winsol-sint-pieters-leeuw', 'winsol-aartselaar'],
            $slugs,
            'De volgorde uit het design (Dilbeek, Sint-Pieters-Leeuw, Aartselaar) klopt niet'
        );
    }

    public function test_the_blueprint_exposes_both_coordinate_fields_as_optional_floats(): void
    {
        $blueprint = Entry::query()->where('collection', 'locations')->first()->blueprint();

        foreach (['latitude', 'longitude'] as $handle) {
            $field = $blueprint->field($handle);

            $this->assertNotNull($field, "Veld {$handle} ontbreekt in de blueprint");
            $this->assertSame('float', $field->type(), "Veld {$handle} hoort een float te zijn");

            // Niet required: een locatie zonder coordinaten hoort in de lijst te
            // blijven staan, en required zou het opslaan blokkeren op precies het
            // moment dat een redacteur een nieuwe vestiging aanmaakt.
            $this->assertFalse($field->isRequired(), "Veld {$handle} hoort optioneel te zijn");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LocationsContentTest`
Expected: FAIL — er zijn nog geen entries, dus `assertNotNull($entry, "Locatie winsol-dilbeek ontbreekt")` faalt.

- [ ] **Step 3: Make the collection orderable**

Replace `content/collections/locations.yaml` with:

```yaml
title: Locations
title_format: '{{ name }}'
structure:
  max_depth: 1
```

- [ ] **Step 4: Add the coordinate fields to the blueprint**

In `resources/blueprints/collections/locations/locations.yaml`, insert these two field-entries in the `main` tab, direct ná het `city`-veld en vóór `opening_hours`. Let op de inspringing: ze staan op hetzelfde niveau als de bestaande `- handle: city`-entry.

```yaml
          -
            handle: latitude
            field:
              type: float
              display: Latitude
              instructions: 'Uit Google Maps, bv. 50.8631'
          -
            handle: longitude
            field:
              type: float
              display: Longitude
              instructions: 'Uit Google Maps, bv. 4.2564'
```

Bewust géén `required` en géén `validate` — zie de comment in de test.

- [ ] **Step 5: Create the three entries**

Create `content/collections/locations/winsol-dilbeek.md`:

```markdown
---
id: d5e6f7a8-0001-4b2c-9d3e-4f5a6b7c8d01
name: 'Winsol Dilbeek'
street: Ninoofsesteenweg
number: '000'
postal_code: '1700'
city: Dilbeek
latitude: 50.8631
longitude: 4.2564
---
```

Create `content/collections/locations/winsol-sint-pieters-leeuw.md`:

```markdown
---
id: d5e6f7a8-0001-4b2c-9d3e-4f5a6b7c8d02
name: 'Winsol Sint-Pieters-Leeuw'
street: Bergensesteenweg
number: '000'
postal_code: '1600'
city: 'Sint-Pieters-Leeuw'
latitude: 50.7789
longitude: 4.2432
---
```

Create `content/collections/locations/winsol-aartselaar.md`:

```markdown
---
id: d5e6f7a8-0001-4b2c-9d3e-4f5a6b7c8d03
name: 'Winsol Aartselaar'
street: Antwerpsesteenweg
number: '000'
postal_code: '2630'
city: Aartselaar
latitude: 51.1342
longitude: 4.3831
---
```

Er staat bewust geen `title` in: de collectie heeft `title_format: '{{ name }}'`, dus de titel wordt afgeleid van `name`. De huisnummers zijn `000` omdat het design ze zo toont — dat is een onmiskenbare placeholder, terwijl een verzonnen nummer een echt gebouw zou kunnen aanwijzen.

- [ ] **Step 6: Create the order tree**

Create `content/trees/collections/locations.yaml`:

```yaml
tree:
  -
    entry: d5e6f7a8-0001-4b2c-9d3e-4f5a6b7c8d01
  -
    entry: d5e6f7a8-0001-4b2c-9d3e-4f5a6b7c8d02
  -
    entry: d5e6f7a8-0001-4b2c-9d3e-4f5a6b7c8d03
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=LocationsContentTest`
Expected: PASS — 3 tests.

Faalt `test_the_locations_are_ordered_as_designed` met een lege lijst, dan is de statische cache van Statamic nog warm: draai `php please cache:clear` en probeer opnieuw.

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — de blueprint- en collectiewijziging mag geen bestaande test raken.

- [ ] **Step 9: Commit**

```bash
git add content/collections/locations.yaml content/collections/locations/ content/trees/collections/locations.yaml resources/blueprints/collections/locations/locations.yaml tests/Feature/Content/LocationsContentTest.php
git commit -m "$(cat <<'EOF'
feat(locations): add the three branches with their coordinates

Leaflet needs lat/lng, and the blueprint only carried street-level
address fields. They are plain optional floats rather than a runtime
geocoding call: three addresses that change once a decade do not justify
an external API dependency, and required fields would block saving a new
branch at exactly the moment an editor creates one.

The collection becomes orderable so the tag returns the designed order
(Dilbeek, Sint-Pieters-Leeuw, Aartselaar) instead of sorting by title.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Locations-partial

De markup: drie kaartjes naast een lege kaartcontainer. Nog geen Leaflet — dit levert een werkende, testbare component op waar de kaartcontainer een leeg vlak is.

**Files:**
- Create: `resources/views/partials/locationCard.antlers.html`
- Create: `resources/views/partials/locations.antlers.html`
- Create: `resources/css/components/locations.css`
- Modify: `resources/css/site.css`
- Test: `tests/Feature/Sections/LocationsTest.php`

**Interfaces:**
- Consumes: de drie entries uit Task 1.
- Produces: het DOM-contract waar Task 3 op steunt — `[data-section="locations"]` als sectiewrapper, `[data-locations-map]` als kaartcontainer, `[data-location-lat]` / `[data-location-lng]` op elk kaartje, en `<template data-map-pin>` met de inline SVG.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sections/LocationsTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class LocationsTest extends SectionTestCase
{
    public function test_it_renders_a_card_per_location_linking_to_contact(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('data-section="locations"', $html);
        $this->assertSame(3, substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
    }

    public function test_it_renders_the_hardcoded_heading(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('Bezoek ons', $html);
        $this->assertStringContainsString('Liever eerst zien en voelen?', $html);
    }

    public function test_it_composes_the_address_from_the_separate_fields(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('Ninoofsesteenweg 000, 1700 Dilbeek', $html);
        $this->assertStringContainsString('Antwerpsesteenweg 000, 2630 Aartselaar', $html);
    }

    public function test_it_lists_the_locations_in_their_designed_order(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $dilbeek = strpos($html, 'Winsol Dilbeek');
        $leeuw = strpos($html, 'Winsol Sint-Pieters-Leeuw');
        $aartselaar = strpos($html, 'Winsol Aartselaar');

        $this->assertLessThan($leeuw, $dilbeek, 'Dilbeek hoort eerst te staan');
        $this->assertLessThan($aartselaar, $leeuw, 'Sint-Pieters-Leeuw hoort tweede te staan');
    }

    public function test_every_card_carries_its_coordinates_for_the_map(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertSame(3, substr_count($html, 'data-location-lat='));
        $this->assertSame(3, substr_count($html, 'data-location-lng='));
        $this->assertStringContainsString('data-location-lat="50.8631"', $html);
        $this->assertStringContainsString('data-location-lng="4.2564"', $html);
    }

    public function test_a_location_without_coordinates_still_gets_a_card(): void
    {
        // De collectie-entries zijn de fixtures van de andere tests, dus deze
        // failure mode wordt op de losse kaart-partial getest in plaats van er
        // een vierde neplocatie voor in de content te zetten.
        $html = $this->render('{{ partial:locationCard }}', [
            'name' => 'Winsol Zonder Punt',
            'street' => 'Teststraat',
            'number' => '1',
            'postal_code' => '9000',
            'city' => 'Gent',
        ]);

        $this->assertStringContainsString('href="/contact"', $html);
        $this->assertStringContainsString('Teststraat 1, 9000 Gent', $html);
        $this->assertStringNotContainsString('data-location-lat', $html);
        $this->assertStringNotContainsString('data-location-lng', $html);
    }

    public function test_the_map_follows_the_cards_in_the_dom_and_is_hidden_from_assistive_tech(): void
    {
        $html = $this->render('{{ partial:locations }}');

        // Onder lg stapelt het grid in DOM-volgorde, dus dit is wat de gekozen
        // mobiele volgorde (kaartjes boven, kaart eronder) vastpint.
        $this->assertLessThan(
            strpos($html, 'data-locations-map'),
            strpos($html, 'Winsol Aartselaar'),
            'De kaart hoort onder de kaartjes te staan'
        );

        $this->assertStringContainsString('data-locations-map aria-hidden="true"', $html);
    }

    public function test_it_ships_the_pin_svg_once_for_the_map_to_clone(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertSame(1, substr_count($html, 'data-map-pin'));
        $this->assertStringContainsString('M3.65793 3.77467', $html);
    }

    public function test_it_credits_the_tile_providers_outside_the_hidden_map(): void
    {
        $html = $this->render('{{ partial:locations }}');

        $this->assertStringContainsString('openstreetmap.org/copyright', $html);
        $this->assertStringContainsString('carto.com/attributions', $html);

        // Buiten de aria-hidden container: focusbare links binnen aria-hidden
        // zijn onbereikbaar voor screenreaders maar wel bereikbaar met tab.
        $this->assertLessThan(
            strpos($html, 'openstreetmap.org/copyright'),
            strpos($html, 'data-locations-map'),
            'De attributie hoort na (en buiten) de kaartcontainer te staan'
        );
    }

    public function test_it_does_not_inherit_page_fields_into_its_own_heading(): void
    {
        // sectionHeader leest title/text/link uit de cascade. De partial wordt
        // op willekeurige templates geincludeerd, dus een pagina met een eigen
        // `text`- of `link`-veld mag daar niet in lekken.
        $html = $this->render('{{ partial:locations }}', [
            'text' => 'LEKKAGE-TEKST',
            'link' => [['type' => 'url', 'url' => 'example.com', 'label' => 'LEKKAGE-LINK']],
        ]);

        $this->assertStringNotContainsString('LEKKAGE-TEKST', $html);
        $this->assertStringNotContainsString('LEKKAGE-LINK', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LocationsTest`
Expected: FAIL — de partial bestaat niet, dus Antlers gooit een view-not-found.

- [ ] **Step 3: Write the location card partial**

Create `resources/views/partials/locationCard.antlers.html`:

```antlers
{{#
    Eén bron voor het locatiekaartje: `locations` rendert hem per entry uit
    de collectie, de test rendert hem los om de coordinaatloze variant te
    controleren. Het kaartje is zelf de link, dus de pijl is een `<span>` —
    een `<button>` of tweede `<a>` erin zou een link in een link zijn.

    `data-location-lat`/`-lng` staan er alleen als beide velden gevuld zijn:
    een halve coordinaat is geen positie, en de JS filtert liever op
    afwezigheid dan op NaN.

    De pijl uit het design wijst diagonaal naar rechtsboven; `arrow.svg`
    wijst horizontaal, vandaar de `-rotate-45`. Geen tweede pijlbestand.
#}}
<a href="/contact"
    class="location-card flex items-center justify-between gap-5 rounded-md bg-light card-padding"
    {{ if latitude and longitude }}data-location-lat="{{ latitude }}" data-location-lng="{{ longitude }}"{{ /if }}>
    <span class="flex flex-col gap-1">
        <span class="location-card__name">{{ name }}</span>
        <span class="location-card__address">{{ street }} {{ number }}, {{ postal_code }} {{ city }}</span>
    </span>

    <span class="location-card__arrow flex size-9 shrink-0 items-center justify-center rounded-full bg-white">
        {{ svg src="arrow" aria-hidden="true" class="w-3.5 -rotate-45" }}
    </span>
</a>
```

- [ ] **Step 4: Write the locations partial**

Create `resources/views/partials/locations.antlers.html`:

```antlers
{{#
    Vaste component, geen page-builder sectie: hij neemt geen argumenten en
    leest zijn eigen collectie, zodat `{{ partial:locations }}` overal
    hetzelfde oplevert.

    `text=""` en `link=""` worden expliciet meegegeven aan sectionHeader.
    Die leest title/text/link uit de render-cascade, en deze partial wordt
    op willekeurige templates geincludeerd — zonder die twee zou een pagina
    met een eigen `text`- of `link`-veld dat in deze kop zien verschijnen.

    De kaart staat na de lijst in de DOM. Onder `lg` stapelt het grid
    daardoor vanzelf naar kaartjes-boven-kaart, zonder `order`-omkering.

    De attributie staat buiten de `aria-hidden` container en Leaflet's eigen
    attributionControl staat uit (locations-map.js): links binnen een
    aria-hidden element zijn wel focusbaar maar niet aankondigbaar.
#}}
<section class="section section--default" data-section="locations">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader is_centered="true" overline="Bezoek ons" title="Liever eerst zien en voelen?" text="" link="" }}

            <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                <ul class="flex flex-col gap-6 lg:gap-8">
                    {{ collection:locations }}
                        <li>
                            {{ partial:locationCard }}
                        </li>
                    {{ /collection:locations }}
                </ul>

                <div class="locations-map">
                    <div class="locations-map__canvas rounded-md bg-light" data-locations-map aria-hidden="true"></div>

                    <p class="locations-map__attribution">
                        &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>-bijdragers
                        &copy; <a href="https://carto.com/attributions" target="_blank" rel="noopener">CARTO</a>
                    </p>
                </div>
            </div>

            <template data-map-pin hidden>{{ svg src="map-pin" }}</template>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Write the component CSS**

Create `resources/css/components/locations.css`:

```css
/*
 * Figma dgMxUtoYzYrR5FRuwPzQBn, node 293:3935 (desktop 1744): twee kolommen
 * van 816 met 32 gap, kaartjes 816 x 147 met 40 padding, kaart 816 x 506.
 * De verhouding staat als aspect-ratio zodat de kaartcontainer zijn hoogte
 * heeft voordat Leaflet geladen is — anders springt de layout op het moment
 * dat de tegels binnenkomen, en heeft Leaflet geen definite height om in te
 * renderen.
 */
.location-card {
    @apply shadow-md/0 transition-shadow duration-200 ease-out;
}

.location-card:hover,
.location-card:focus-visible {
    @apply shadow-lg shadow-black/10;
}

.location-card__name {
    @apply text-xl font-semibold;
}

.location-card__address {
    @apply text-base;
}

.locations-map {
    @apply flex flex-col gap-2;
}

.locations-map__canvas {
    aspect-ratio: 4 / 3;
}

@media (min-width: 1024px) {
    .locations-map__canvas {
        aspect-ratio: 816 / 506;
    }
}

.locations-map__attribution {
    @apply text-xs text-black/50;
}

.locations-map__attribution a {
    @apply underline;
}
```

- [ ] **Step 6: Register the stylesheet**

In `resources/css/site.css`, add to the end of the `/* Components */` block:

```css
@import './components/locations.css';
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=LocationsTest`
Expected: PASS — 10 tests.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/views/partials/locationCard.antlers.html resources/views/partials/locations.antlers.html resources/css/components/locations.css resources/css/site.css tests/Feature/Sections/LocationsTest.php
git commit -m "$(cat <<'EOF'
feat(locations): render the branch cards beside an empty map slot

The card is its own partial, matching rangeCard and projectCard, so the
no-coordinates case can be tested without seeding a fourth fake branch
into the content the other tests assert against.

sectionHeader reads title/text/link from the render cascade and this
partial gets included on arbitrary templates, so text and link are
explicitly blanked — otherwise a page carrying either field would see it
surface inside this heading.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Leaflet-kaart

De kaart tot leven brengen: dynamische import achter een IntersectionObserver, pins uit de `<template>`, hover-zoom op de kaartjes.

**Files:**
- Modify: `package.json`
- Create: `resources/js/components/locations-map.js`
- Modify: `resources/js/site.js`
- Modify: `resources/css/components/locations.css`

**Interfaces:**
- Consumes: het DOM-contract uit Task 2 (`[data-section="locations"]`, `[data-locations-map]`, `[data-location-lat]`/`[data-location-lng]`, `<template data-map-pin>`).
- Produces: niets waar latere tasks op steunen.

Deze task heeft **geen automatische test**. Er is geen JS-testopstelling in dit project (geen vitest, geen Playwright), en er één optuigen voor twee event-listeners kost meer onderhoud dan het oplevert. Step 6 is daarom een expliciete handmatige checklist. Dit is een bewuste keuze, niet een gat.

- [ ] **Step 1: Install Leaflet**

```bash
npm install leaflet@^1.9.4
```

- [ ] **Step 2: Write the map component**

Create `resources/js/components/locations-map.js`:

```js
/**
 * Leaflet komt binnen via een dynamische import achter een
 * IntersectionObserver, zoals sliders.js dat met Swiper doet: pagina's
 * zonder kaart betalen niets, en pagina's met kaart betalen pas bij het
 * scrollen. Vite splitst leaflet + leaflet.css in een eigen chunk.
 *
 * De coordinaten worden van de kaartjes zelf gelezen. Een aparte
 * data-locations='[...]'-blob op de container zou een tweede bron van
 * waarheid zijn die uit sync kan lopen met de gerenderde lijst.
 */

const SECTION_SELECTOR = '[data-section="locations"]'
const MAP_SELECTOR = '[data-locations-map]'
const CARD_SELECTOR = '[data-location-lat][data-location-lng]'
const PIN_SELECTOR = '[data-map-pin]'

const FOCUS_ZOOM = 13
const BOUNDS_PADDING = [40, 40]

const TILE_URL = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png'
const TILE_SUBDOMAINS = 'abcd'
const TILE_MAX_ZOOM = 20

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches

const canHover = () =>
    window.matchMedia('(hover: hover) and (pointer: fine)').matches

/**
 * Een kaartje zonder (geldige) coordinaten levert geen pin op maar houdt wel
 * zijn plek in de lijst — het blijft een werkende link naar /contact.
 */
function readLocations(section) {
    return Array.from(section.querySelectorAll(CARD_SELECTOR))
        .map((card) => ({
            card,
            lat: parseFloat(card.dataset.locationLat),
            lng: parseFloat(card.dataset.locationLng),
        }))
        .filter(({ lat, lng }) => Number.isFinite(lat) && Number.isFinite(lng))
}

async function createMap(section, container, locations) {
    // Leaflet 1.x is UMD, 2.x is ESM-only. Deze interop werkt voor allebei.
    const leaflet = await import('leaflet')
    const L = leaflet.default ?? leaflet
    await import('leaflet/dist/leaflet.css')

    const template = section.querySelector(PIN_SELECTOR)

    const icon = L.divIcon({
        html: template ? template.innerHTML : '',
        // Overschrijft leaflet-div-icon; de reset staat in locations.css.
        className: 'locations-map__pin',
        iconSize: [25, 33],
        iconAnchor: [12.5, 33],
    })

    // Alle interactie uit: het design toont geen zoomknoppen en de kaart is
    // illustratie. Dat voorkomt ook dat de pagina niet meer scrollt zodra de
    // muis boven de kaart hangt. De attributie wordt in de partial gerenderd,
    // buiten de aria-hidden container, dus Leaflet's eigen control blijft uit.
    const map = L.map(container, {
        attributionControl: false,
        zoomControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false,
    })

    L.tileLayer(TILE_URL, {
        subdomains: TILE_SUBDOMAINS,
        maxZoom: TILE_MAX_ZOOM,
    }).addTo(map)

    locations.forEach(({ lat, lng }) => {
        L.marker([lat, lng], { icon, keyboard: false, interactive: false }).addTo(map)
    })

    // fitBounds over de echte pins, niet het hardcoded centrum uit Figma: zo
    // blijft het beeld kloppen als er een vestiging bijkomt of verhuist.
    const bounds = L.latLngBounds(locations.map(({ lat, lng }) => [lat, lng]))
    map.fitBounds(bounds, { padding: BOUNDS_PADDING })

    if (!canHover()) return map

    const reset = () => {
        if (prefersReducedMotion()) {
            map.fitBounds(bounds, { padding: BOUNDS_PADDING })
        } else {
            map.flyToBounds(bounds, { padding: BOUNDS_PADDING })
        }
    }

    locations.forEach(({ card, lat, lng }) => {
        const focus = () => {
            if (prefersReducedMotion()) {
                map.setView([lat, lng], FOCUS_ZOOM)
            } else {
                map.flyTo([lat, lng], FOCUS_ZOOM)
            }
        }

        // focusin/focusout naast de muis-events, zodat tab-navigatie hetzelfde
        // doet als hoveren.
        card.addEventListener('mouseenter', focus)
        card.addEventListener('focusin', focus)
        card.addEventListener('mouseleave', reset)
        card.addEventListener('focusout', reset)
    })

    return map
}

function register(section) {
    const container = section.querySelector(MAP_SELECTOR)
    if (!container) return

    const locations = readLocations(section)
    if (locations.length === 0) return

    const observer = new IntersectionObserver(
        (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return

            observer.disconnect()
            createMap(section, container, locations)
        },
        { rootMargin: '200px' }
    )

    observer.observe(container)
}

document.querySelectorAll(SECTION_SELECTOR).forEach(register)
```

- [ ] **Step 3: Register the module**

In `resources/js/site.js`, add to the block of component imports at the bottom, after the existing `import "./components/sliders";`:

```js
import "./components/locations-map";
```

- [ ] **Step 4: Reset Leaflet's default pin styling**

Append to `resources/css/components/locations.css`:

```css
/*
 * Leaflet's eigen .leaflet-div-icon geeft elke divIcon een witte achtergrond
 * en een grijze rand. leaflet.css komt uit een dynamisch geladen chunk, dus
 * de laadvolgorde ten opzichte van site.css ligt niet vast — vandaar de
 * dubbele selector, die wint op specificiteit in plaats van op volgorde.
 */
.leaflet-div-icon.locations-map__pin {
    @apply border-0 bg-transparent;
}

/* De kaart is illustratie: geen focusring, geen tekstcursor. */
.locations-map__canvas .leaflet-container {
    @apply cursor-default outline-none;
}
```

- [ ] **Step 5: Build to verify the chunk splits**

Run: `npm run build`
Expected: build slaagt, en de output noemt een aparte chunk met `leaflet` in de naam. Staat Leaflet in de hoofd-`site.js`-bundel, dan is de dynamische import per ongeluk statisch geworden — controleer dat er nergens een top-level `import 'leaflet'` staat.

- [ ] **Step 6: Verify by hand in the browser**

Zet `{{ partial:locations }}` **tijdelijk** onderaan `resources/views/contact.antlers.html`, draai `npm run dev`, open `/contact` en loop deze lijst af:

- de kaart laadt pas bij het scrollen ernaartoe (zichtbaar in het netwerk-tabblad)
- de drie gele pins staan op Dilbeek, Sint-Pieters-Leeuw en Aartselaar, zonder witte vierkante achtergrond
- hoveren over een kaartje zoomt naar die locatie; de muis weghalen zoomt terug naar alle drie
- tabben naar een kaartje doet hetzelfde als hoveren
- scrollen met de muis boven de kaart scrollt de pagina, niet de kaart
- met "beweging beperken" aan in het systeem springt de kaart in plaats van te animeren
- op een smal venster staat de kaart onder de kaartjes en gebeurt er niets bij aanraken

**Draai de tijdelijke include daarna terug** — geen enkele paginatemplate mag wijzigen (zie Global Constraints). Controleer met `git status` dat `resources/views/contact.antlers.html` schoon is voordat je commit.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json resources/js/components/locations-map.js resources/js/site.js resources/css/components/locations.css
git commit -m "$(cat <<'EOF'
feat(locations): bring the map to life with Leaflet

Loaded through a dynamic import behind an IntersectionObserver, the way
sliders.js loads Swiper: pages without a map pay nothing, and pages with
one pay only once the reader scrolls to it.

All map interaction is off. The design shows no zoom controls and the map
is illustration, so hover-zoom is the only motion — which also stops the
map from swallowing the page scroll when the pointer crosses it.

Hover is gated behind (hover: hover) and paired with focusin/focusout so
keyboard navigation gets the same behaviour, and prefers-reduced-motion
swaps the fly animations for jumps.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Quicklinks-data

De drie quicklinks als entries, de collectie orderable, en het dode veld op de contact-blueprint opruimen.

**Files:**
- Modify: `content/collections/quicklinks.yaml`
- Modify: `resources/blueprints/collections/pages/contact.yaml`
- Create: `content/collections/quicklinks/vraag-offerte-aan.md`
- Create: `content/collections/quicklinks/vraag-brochure-aan.md`
- Create: `content/collections/quicklinks/bezoek-een-showroom.md`
- Create: `content/trees/collections/quicklinks.yaml`
- Test: `tests/Feature/Content/QuicklinksContentTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: drie entries in de `quicklinks`-collectie met de velden `title`, `text`, `link` (grid van één rij: `type`, `entry`, `label`, `new_tab`) en `link_style` (`primary` of `outline`). Vaste volgorde: offerte, brochure, showroom. Task 5 rendert deze entries.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Content/QuicklinksContentTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class QuicklinksContentTest extends TestCase
{
    public function test_every_quicklink_exists_with_its_copy_and_button_style(): void
    {
        $expected = [
            'vraag-offerte-aan' => [
                'title' => 'Vraag offerte aan',
                'label' => 'Vraag offerte aan',
                'link_style' => 'primary',
            ],
            'vraag-brochure-aan' => [
                'title' => 'Vraag brochure aan',
                'label' => 'Brochure aanvragen',
                'link_style' => 'outline',
            ],
            'bezoek-een-showroom' => [
                'title' => 'Bezoek een showroom',
                'label' => 'Plan een bezoek',
                'link_style' => 'outline',
            ],
        ];

        foreach ($expected as $slug => $fields) {
            $entry = Entry::query()->where('collection', 'quicklinks')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Quicklink {$slug} ontbreekt");
            $this->assertSame($fields['title'], $entry->get('title'));
            $this->assertSame($fields['link_style'], $entry->get('link_style'));
            $this->assertNotEmpty($entry->get('text'), "Quicklink {$slug} heeft geen tekst");

            $link = $entry->get('link');
            $this->assertIsArray($link);
            $this->assertCount(1, $link, "Quicklink {$slug} hoort precies een link te hebben");
            $this->assertSame($fields['label'], $link[0]['label']);
            $this->assertSame('entry', $link[0]['type']);
        }
    }

    public function test_every_quicklink_points_at_an_entry_that_actually_exists(): void
    {
        $entries = Entry::query()->where('collection', 'quicklinks')->get();

        $this->assertCount(3, $entries);

        foreach ($entries as $entry) {
            $targetId = $entry->get('link')[0]['entry'][0];

            $this->assertNotNull(
                Entry::find($targetId),
                "De knop van {$entry->slug()} wijst naar een niet-bestaande entry"
            );
        }
    }

    public function test_the_quicklinks_are_ordered_as_designed(): void
    {
        $slugs = Entry::query()
            ->where('collection', 'quicklinks')
            ->orderBy('order')
            ->get()
            ->map->slug()
            ->all();

        $this->assertSame(
            ['vraag-offerte-aan', 'vraag-brochure-aan', 'bezoek-een-showroom'],
            $slugs,
            'De volgorde uit het design (offerte, brochure, showroom) klopt niet'
        );
    }

    public function test_the_contact_blueprint_no_longer_carries_a_dead_quicklinks_field(): void
    {
        // De component leest altijd de hele collectie, dus dit veld deed niets
        // meer en hoort niet als loze knop in de CP te blijven staan.
        $blueprint = Entry::query()->where('collection', 'pages')->where('slug', 'contact')->first()->blueprint();

        $this->assertNull($blueprint->field('quicklinks'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuicklinksContentTest`
Expected: FAIL — `assertNotNull($entry, "Quicklink vraag-offerte-aan ontbreekt")` faalt.

- [ ] **Step 3: Make the collection orderable**

Replace `content/collections/quicklinks.yaml` with:

```yaml
title: Quicklinks
structure:
  max_depth: 1
```

- [ ] **Step 4: Remove the dead field from the contact blueprint**

In `resources/blueprints/collections/pages/contact.yaml`, delete this whole section-entry uit de `main`-tab (het `sections`-item dat alleen het `quicklinks`-veld bevat):

```yaml
      -
        fields:
          -
            handle: quicklinks
            field:
              type: entries
              collections:
                - quicklinks
              display: Quicklinks
```

De omliggende secties (`page_intro` erboven, `page_builder` eronder) blijven ongewijzigd.

- [ ] **Step 5: Create the three entries**

Alle drie de knoppen wijzen voorlopig naar de contact-pagina (`f0ee3161-1534-4986-9ef1-a92fccfba619`). Er bestaat nog geen offerte- of brochurepagina; contact is het adres waar deze drie aanvragen vandaag terechtkomen.

Create `content/collections/quicklinks/vraag-offerte-aan.md`:

```markdown
---
id: e6f7a8b9-0002-4c3d-9e4f-5a6b7c8d9e01
title: 'Vraag offerte aan'
text: 'Met Pergola SO! voorinvuld. Vrijblijvend en op maat.'
link:
  -
    type: entry
    entry:
      - f0ee3161-1534-4986-9ef1-a92fccfba619
    label: 'Vraag offerte aan'
    new_tab: false
link_style: primary
---
```

Create `content/collections/quicklinks/vraag-brochure-aan.md`:

```markdown
---
id: e6f7a8b9-0002-4c3d-9e4f-5a6b7c8d9e02
title: 'Vraag brochure aan'
text: 'Ontvang de volledige brochure met opties en kleuren in uw bus of mailbox.'
link:
  -
    type: entry
    entry:
      - f0ee3161-1534-4986-9ef1-a92fccfba619
    label: 'Brochure aanvragen'
    new_tab: false
link_style: outline
---
```

Create `content/collections/quicklinks/bezoek-een-showroom.md`:

```markdown
---
id: e6f7a8b9-0002-4c3d-9e4f-5a6b7c8d9e03
title: 'Bezoek een showroom'
text: 'Met Pergola SO! voorinvuld. Vrijblijvend en op maat.'
link:
  -
    type: entry
    entry:
      - f0ee3161-1534-4986-9ef1-a92fccfba619
    label: 'Plan een bezoek'
    new_tab: false
link_style: outline
---
```

De entries krijgen bewust geen `image`: het assets-pad van `offerte-1`/`offerte-2`, `brochure` en `winkel` is nog niet bekend (zie Open punten in de spec). De partial uit Task 5 rendert het beeld alleen als het er is, dus de component werkt zonder.

- [ ] **Step 6: Create the order tree**

Create `content/trees/collections/quicklinks.yaml`:

```yaml
tree:
  -
    entry: e6f7a8b9-0002-4c3d-9e4f-5a6b7c8d9e01
  -
    entry: e6f7a8b9-0002-4c3d-9e4f-5a6b7c8d9e02
  -
    entry: e6f7a8b9-0002-4c3d-9e4f-5a6b7c8d9e03
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=QuicklinksContentTest`
Expected: PASS — 4 tests.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS. Let op `PageBuilderPageTest` en andere tests die de contactpagina renderen — het verwijderde veld werd nergens uitgelezen, dus dit hoort schoon door te gaan.

- [ ] **Step 9: Commit**

```bash
git add content/collections/quicklinks.yaml content/collections/quicklinks/ content/trees/collections/quicklinks.yaml resources/blueprints/collections/pages/contact.yaml tests/Feature/Content/QuicklinksContentTest.php
git commit -m "$(cat <<'EOF'
add the three quicklinks and drop the field they replace

The component always reads the whole collection, so the entries picker on
the contact blueprint no longer fed anything — it goes, rather than
staying on as a control that silently does nothing.

All three buttons point at the contact page for now: there is no quote or
brochure page yet, and contact is where those requests land today.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Quicklinks-partial

De drie CTA-kaarten, plus de ontbrekende `.btn--outline`-knopvariant.

**Files:**
- Modify: `resources/css/components/button.css`
- Create: `resources/views/partials/quicklinks.antlers.html`
- Create: `resources/css/components/quicklinks.css`
- Modify: `resources/css/site.css`
- Test: `tests/Feature/Sections/QuicklinksTest.php`

**Interfaces:**
- Consumes: de drie entries uit Task 4.
- Produces: niets waar latere tasks op steunen.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sections/QuicklinksTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class QuicklinksTest extends SectionTestCase
{
    public function test_it_renders_a_card_per_quicklink_under_the_hardcoded_title(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('data-section="quicklinks"', $html);
        $this->assertStringContainsString('Zet de volgende stap', $html);
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }

    public function test_it_renders_the_copy_from_the_collection(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('Vraag offerte aan', $html);
        $this->assertStringContainsString('Vraag brochure aan', $html);
        $this->assertStringContainsString('Bezoek een showroom', $html);
        $this->assertStringContainsString('Ontvang de volledige brochure met opties en kleuren in uw bus of mailbox.', $html);
        $this->assertStringContainsString('Plan een bezoek', $html);
    }

    public function test_the_first_button_is_filled_and_the_other_two_are_outlined(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        // De link_style-mapping is de enige vertakking in de partial, dus dit
        // is wat vastgepind hoort te worden.
        $this->assertSame(1, substr_count($html, 'btn--accent'));
        $this->assertSame(2, substr_count($html, 'btn--outline'));

        $this->assertLessThan(
            strpos($html, 'btn--outline'),
            strpos($html, 'btn--accent'),
            'De gevulde knop hoort op de eerste kaart te staan'
        );
    }

    public function test_it_lists_the_quicklinks_in_their_designed_order(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $offerte = strpos($html, 'Vraag offerte aan');
        $brochure = strpos($html, 'Vraag brochure aan');
        $showroom = strpos($html, 'Bezoek een showroom');

        $this->assertLessThan($brochure, $offerte, 'Offerte hoort eerst te staan');
        $this->assertLessThan($showroom, $brochure, 'Brochure hoort tweede te staan');
    }

    public function test_a_quicklink_without_an_image_still_renders_its_card(): void
    {
        // De entries hebben nog geen beeld (het assets-pad is nog niet bekend),
        // dus de component hoort daar nu al tegen te kunnen.
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertSame(3, substr_count($html, 'quicklink-card'));
        $this->assertStringNotContainsString('<img', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuicklinksTest`
Expected: FAIL — de partial bestaat niet.

- [ ] **Step 3: Add the outline button variant**

Append to `resources/css/components/button.css`:

```css
/*
 * `.btn--outline` is de omlijnde tegenhanger van `.btn--accent`, gebruikt
 * door de quicklinks-component voor de tweede en derde kaart. Zelfde vorm,
 * padding en fontgrootte als `.btn--accent`; alleen de vulling verschilt.
 * `.btn--primary` is hier geen alternatief (vierkant, kleinere padding) en
 * die overriden met losse utilities op de aanroepplek zou de knopvormen
 * over twee bestanden verspreiden.
 */
.btn--outline {
    @apply rounded-full border border-black bg-transparent px-8 py-5 text-base font-semibold text-black;
}
```

- [ ] **Step 4: Write the quicklinks partial**

Create `resources/views/partials/quicklinks.antlers.html`:

```antlers
{{#
    Vaste component, geen page-builder sectie: geen argumenten, leest zijn
    eigen collectie.

    Hier staat een kale `<h2>` en geen `{{ partial:sectionHeader }}`: het
    design toont boven dit blok geen overline, tekst of link, alleen de
    titel. sectionHeader aanroepen zou drie ongebruikte takken meeslepen —
    en die lezen uit de cascade, dus ze zouden paginavelden binnenhalen.

    Geen slider, in tegenstelling tot `sections/cards` en `sections/ranges`:
    dat zijn open lijsten van wisselende lengte, dit zijn de drie
    belangrijkste CTA's van de pagina. Die horen alle drie in beeld, niet
    twee ervan achter een swipe.

    De link_style-mapping is een expliciete if/else met twee volledig
    uitgeschreven klassenstrings. Nooit interpoleren: Tailwind's scanner
    vindt runtime-samengestelde klassenamen niet.
#}}
<section class="section section--default" data-section="quicklinks">
    <div class="container">
        <div class="section-y-gap">
            <h2 class="text-center">Zet de volgende stap</h2>

            <ul class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                {{ collection:quicklinks }}
                    <li class="quicklink-card flex flex-col gap-4 rounded-md bg-light card-padding lg:gap-6">
                        {{#
                            Bewust `quicklink-media` en niet `quicklink-card__media`:
                            QuicklinksTest telt het aantal kaarten met
                            substr_count($html, 'quicklink-card'), en een BEM-kind
                            van die naam zou elke kaart dubbel tellen zodra de
                            beelden gekoppeld worden.
                        #}}
                        {{ if image }}
                            <div class="quicklink-media">
                                {{ img :src="image" max_width="640" sizes="(min-width: 1024px) 30vw, 90vw" class="size-full object-contain" }}
                            </div>
                        {{ /if }}

                        <h3>{{ title }}</h3>

                        {{ if text }}
                            <p>{{ text }}</p>
                        {{ /if }}

                        {{ if link }}
                            {{ partial:link :style="link_style == 'outline' ? 'btn btn--outline' : 'btn btn--accent'" }}
                        {{ /if }}
                    </li>
                {{ /collection:quicklinks }}
            </ul>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Write the component CSS**

Create `resources/css/components/quicklinks.css`:

```css
/*
 * De productfoto's zijn uitgeknipte beelden op transparant, geen
 * bleed-foto's: een vaste hoogte met object-contain houdt de drie kaarten
 * op één lijn, ook als de bronbeelden verschillende verhoudingen hebben.
 */
.quicklink-media {
    @apply flex h-24 items-end lg:h-32;
}

.quicklink-card {
    @apply shadow-md/0 transition-shadow duration-200 ease-out;
}

.quicklink-card:hover,
.quicklink-card:focus-within {
    @apply shadow-lg shadow-black/10;
}

/* De knop hangt onderaan, ook als de teksten verschillend lang zijn. */
.quicklink-card .btn {
    @apply mt-auto;
}
```

- [ ] **Step 6: Register the stylesheet**

In `resources/css/site.css`, add to the end of the `/* Components */` block:

```css
@import './components/quicklinks.css';
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter=QuicklinksTest`
Expected: PASS — 5 tests.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/css/components/button.css resources/views/partials/quicklinks.antlers.html resources/css/components/quicklinks.css resources/css/site.css tests/Feature/Sections/QuicklinksTest.php
git commit -m "$(cat <<'EOF'
feat(quicklinks): render the collection as three CTA cards

Adds the outline button the design calls for; .btn--primary is square
with tighter padding, so overriding it at the call site would have split
the button shapes across two files.

No slider here, unlike the cards and ranges sections: those are open
lists of varying length, these are the three most important CTAs on the
page and all three belong on screen.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

## Na afloop

De spec houdt drie open punten die dit plan bewust niet oplost, omdat de informatie ontbreekt:

1. **Echte huisnummers** — nu `000` in de drie locatie-entries.
2. **Assets-pad van de quicklink-foto's** — de entries hebben nog geen `image`; de partial rendert eromheen.
3. **Figma-node van de quicklink-component** — de productfoto lijkt op het screenshot net over de bovenrand van de kaart te steken. Dit plan bouwt het beeld bínnen de kaart (`.quicklink-card__media`); met de node kan die overlap alsnog nagebouwd worden.

Alle drie zijn een kwestie van waarden invullen, niet van code herschrijven.
