# Servicepagina — implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/service` bouwen volgens Figma `318:2955` — default header, een `lg`-only ankerbalk met vloeiend scrollen, vier afwisselende text-image-secties uit de bestaande `services`-replicator, en een opgemaakt (nog niet verwerkend) herstellingsformulier.

**Architecture:** Vijf lagen, elk apart testbaar. `textImage` krijgt twee optionele argumenten zodat de afwisseling links/rechts loskomt van de `background`-toggle. Een nieuwe `sectionNav`-partial leest `services` uit de cascade en rendert ankerlinks. Het formulier draait op Statamic's form-tag met een generieke `{{ sections }}`/`{{ fields }}`-loop; alle opmaak zit in herbruikbare klassen in `form.css`. Een `reparation`-sectie zet dat formulier in de band met watermerk en koffer. Het template rijgt alles aan elkaar.

**Tech Stack:** Statamic 6 / Laravel 12 / Antlers, Tailwind v4 (CSS-first, `@theme` in `resources/css/site.css`), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-07-27-service-page-design.md`

## Global Constraints

- **Testcommando:** `php -d memory_limit=1G ./vendor/bin/phpunit`. Zonder de memory-flag crasht de volledige suite in `intervention/image`. Baseline vóór dit werk: **183 tests, 744 assertions, 1 skipped, OK**.
- **Nooit een Tailwind-klassenaam interpoleren.** Tailwind's scanner leest broncode statisch en genereert niets voor een runtime-samengestelde string. Schrijf elke klassenstring voluit in elke tak van een `{{ if }}`.
- **Antlers `{{ var = … }}` schrijft in de gedeelde cascade**, niet in een partial-lokale scope. Een toewijzing in één tak lekt naar de volgende iteratie van een loop. Wijs in élke tak toe, of wijs niet toe.
- **`{{ if }}`-blokken lekken witruimte** — de indentatie vóór de tag en de newline erna blijven staan als de conditie onwaar is. In markup waarvan de uitvoer ongewijzigd moet blijven: tag en markup op één regel.
- **`{{ id }}` binnen een replicator-loop is de set-id van Statamic.** Noem een partial-argument nooit `id`.
- **Loopvariabelen:** `index` is 0-gebaseerd, `count` is 1-gebaseerd. Geverifieerd tegen de runtime.
- **`{{ field class="…" }}` bestaat niet.** Antlers parseert dat als modifier en gooit `Modifier [class] not found`. Fieldtype-views accepteren geen class-attribuut.
- **Taal:** codecommentaar en contentcopy in het Nederlands, net als de rest van dit project. Commitberichten in het Nederlands.
- **Kleuren uit Figma `318:3097`:** invoervulling `#f5f5f5`, streepjesrand `#bfbfbf`, kaart `#ffffff`, knop `#f8d71c` (= `--color-accent`). De invoervulling is nadrukkelijk **niet** `--color-light` (`#f1f6f8`), dat blauw zweemt op wit.

## Afwijkingen van de spec

Drie punten die tijdens de verificatie tegen de runtime naar boven kwamen. Ze zijn hier vastgelegd zodat de uitvoerder ze niet opnieuw hoeft te ontdekken, en zodat een reviewer ziet dat het bewuste keuzes zijn.

1. **De divider zit in `sectionNav`, niet in `headers/default divider="true"`.** Dat argument plaatst 112px (`lg:pb-28`) tussen de lijn en wat erop volgt; het design heeft 32px. De balk krijgt dus zijn eigen `border-t`. Gevolg: het template roept `{{ partial:headers/default }}` aan zónder `divider`, en accepteert de 112px die de header-component boven de lijn zet waar Figma 80px toont — dat is de eigen ritmiek van een component die op drie andere pagina's al zo staat.
2. **`.form-control` en `.form-control--textarea` uit de spec-tabel vervallen.** Beide zouden dood zijn: alle controls staan binnen `.form-field`, dat ze via een `:is()`-selector al stijlt. Wel toegevoegd: `.form-select-wrap`, nodig om de caret te positioneren.
3. **`contact.antlers.html` krijgt de nieuwe klassen.** De oude `form.css` stijlt `form`, `label` en `input` als elementen; die regels moeten weg omdat `input { border border-black }` een zwarte rand op de nieuwe velden zou zetten. Ze weghalen zonder `/contact` mee te nemen laat een zichtbaar kapotte pagina achter. Het is vier class-attributen; taak 3 doet het.
4. **Het formulier is een eigen partial**, `partials/reparationForm.antlers.html`, en niet inline in de herstellingssectie. Daardoor is het los testbaar (`ReparationFormTest` naast `ReparationSectionTest`, waar de spec één testbestand noemde) en blijft elk van de twee bestanden klein genoeg om in één keer te overzien.

---

## Bestandsoverzicht

| Bestand | Taak | Verantwoordelijkheid |
|---|---|---|
| `resources/views/partials/sections/textImage.antlers.html` | 1 | `anchor` + `text_first` erbij |
| `resources/views/partials/sectionNav.antlers.html` | 2 | de ankerbalk |
| `resources/css/components/section-nav.css` | 2 | pills, divider, scroll-margin |
| `resources/css/base/global.css` | 2 | `scroll-behavior` |
| `resources/css/site.css` | 2 | import van `section-nav.css` |
| `resources/blueprints/forms/herstelling.yaml` | 3 | veldstructuur van het formulier |
| `resources/forms/herstelling.yaml` | 3 | de form zelf, zonder verwerking |
| `resources/views/partials/reparationForm.antlers.html` | 3 | rendert het formulier |
| `resources/css/components/form.css` | 3 | herschreven, herbruikbare klassen |
| `resources/views/contact.antlers.html` | 3 | de nieuwe klassen |
| `resources/views/partials/sections/reparation.antlers.html` | 4 | de band eromheen |
| `resources/blueprints/collections/pages/services_overview.yaml` | 4 | `image` op `reparation` |
| `content/collections/pages/service.md` | 5 | de entry |
| `resources/views/service.antlers.html` | 5 | het template |

---

## Task 1: `textImage` uitbreiden met `anchor` en `text_first`

**Files:**
- Modify: `resources/views/partials/sections/textImage.antlers.html`
- Test: `tests/Feature/Sections/TextImageSectionTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: `{{ partial src="sections/textImage" :anchor="<string>" :text_first="<bool>" }}`. Beide optioneel. `anchor` rendert `id="<string>"` op de `<section>`; `text_first` haalt `order-last` van de tekstkolom, zodat de tekst links komt. Zonder beide argumenten is de uitvoer identiek aan vandaag.

- [ ] **Step 1: Schrijf de falende tests**

Voeg toe aan `tests/Feature/Sections/TextImageSectionTest.php`, binnen de bestaande class:

```php
    public function test_renders_the_anchor_as_a_section_id(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" anchor="advies" }}', [
            'title' => 'Advies op maat',
        ]);

        $this->assertStringContainsString('id="advies"', $html);
    }

    public function test_omits_the_id_attribute_without_an_anchor(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Advies op maat']);

        $this->assertStringNotContainsString('id="', $html);
    }

    public function test_puts_the_text_column_last_by_default(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Advies op maat']);

        $this->assertStringContainsString('order-last', $html);
    }

    public function test_text_first_moves_the_text_column_ahead_of_the_image(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" :text_first="true" }}', [
            'title' => 'Vakkundige installatie',
        ]);

        $this->assertStringNotContainsString('order-last', $html);
    }

    public function test_background_still_wins_over_text_first(): void
    {
        // `background` zette de tekstkolom al vooraan, mét lichte kaart. Dat gedrag
        // mag niet veranderen nu `text_first` hetzelfde effect langs een andere weg
        // bereikt: de acht bestaande aanroepers geven `text_first` nooit mee.
        $html = $this->render('{{ partial src="sections/textImage" :text_first="true" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('bg-light card-padding', $html);
        $this->assertStringNotContainsString('order-last', $html);
    }
```

- [ ] **Step 2: Draai de tests en bevestig dat ze falen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/TextImageSectionTest.php`

Expected: FAIL. `test_renders_the_anchor_as_a_section_id` en `test_text_first_moves_the_text_column_ahead_of_the_image` falen; de andere drie slagen al (ze beschrijven het huidige gedrag en zijn de vangrail eronder).

- [ ] **Step 3: Pas het partial aan**

Vervang de eerste tien regels van `resources/views/partials/sections/textImage.antlers.html` (van de `{{ if background }}` tot en met de `<div class="section-col-wide …">`-regel) door:

```antlers
{{ if background }}
    {{ image_base_ratio = "3/4" }}
{{ else }}
    {{ image_base_ratio = "16/9" }}
{{ /if }}

{{#
    Kolomvolgorde. Drie takken, en élke tak wijst toe: een Antlers-toewijzing
    schrijft in de gedeelde cascade, niet in een partial-lokale scope, dus een
    tak die niets zet zou de waarde van de vorige loop-iteratie erven. Dezelfde
    reden waarom `image_base_ratio` hierboven een `else` heeft.

    `background` wint van `text_first`: met achtergrond stond de tekst al
    vooraan, in een lichte kaart. De acht bestaande aanroepers geven
    `text_first` nooit mee en renderen dus ongewijzigd.
#}}
{{ if background }}
    {{ text_column_class = "bg-light card-padding" }}
{{ elseif text_first }}
    {{ text_column_class = "" }}
{{ else }}
    {{ text_column_class = "order-last" }}
{{ /if }}

{{#
    `anchor` heet bewust niet `id`: binnen een replicator-loop is `{{ id }}` de
    set-id die Statamic zelf toekent, en die zou een argument met die naam stil
    overschrijven. Tag en attribuut staan op één regel omdat een `{{ if }}` de
    indentatie ervoor en de newline erna laat staan wanneer hij onwaar is — de
    uitvoer zónder anker moet byte-identiek blijven aan vroeger.
#}}
<section{{ if anchor }} id="{{ anchor }}"{{ /if }} class="section section--default {{ background ? 'text-image--background' : '' }}" data-section="text_image">
    <div class="container">
        <div class="section-x-gap items-center">
            <div class="section-col-wide {{ text_column_class }}">
```

De rest van het bestand (vanaf `{{ partial:sectionHeader }}`) blijft ongewijzigd.

- [ ] **Step 4: Draai de tests en bevestig dat ze slagen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/TextImageSectionTest.php`

Expected: PASS, 8 tests.

- [ ] **Step 5: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`

Expected: OK, 188 tests (183 + 5), 1 skipped. Deze stap is de eigenlijke regressiecheck: `textImage` heeft acht aanroepers verspreid over de page-builder.

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/textImage.antlers.html tests/Feature/Sections/TextImageSectionTest.php
git commit -m "feat(textImage): anker en omgekeerde kolomvolgorde als losse argumenten

De volgorde beeld/tekst hing tot nu toe aan de background-toggle. De
servicepagina wisselt af zonder achtergrondvlak, dus die twee moeten los.
Zonder de nieuwe argumenten rendert het partial ongewijzigd."
```

---

## Task 2: Ankerbalk met vloeiend scrollen

**Files:**
- Create: `resources/views/partials/sectionNav.antlers.html`
- Create: `resources/css/components/section-nav.css`
- Modify: `resources/css/site.css` (import onderaan de componentenlijst)
- Modify: `resources/css/base/global.css`
- Test: `tests/Feature/Sections/ServiceNavTest.php`

**Interfaces:**
- Consumes: niets uit taak 1.
- Produces: `{{ partial:sectionNav }}` — geen argumenten, leest `services` uit de cascade. Rendert `<nav data-section="section-nav">` met per service (mét overline) een `<a href="#{{ overline | slugify }}" class="section-nav__link">`, plus één `<a href="#herstelling" class="section-nav__link section-nav__link--report">`. Taak 5 hangt hierop; taak 1 en 4 leveren de doelen van die ankers.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ServiceNavTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ServiceNavTest extends SectionTestCase
{
    private function services(): array
    {
        return [
            ['overline' => 'Advies', 'title' => 'Advies op maat'],
            ['overline' => 'Installatie', 'title' => 'Vakkundige installatie'],
            ['overline' => 'Onderhoud', 'title' => 'Onderhoud en nazicht'],
            ['overline' => 'Garantie', 'title' => 'Garantie en nazorg'],
        ];
    }

    public function test_renders_one_link_per_service_in_order(): void
    {
        $html = $this->render('{{ partial:sectionNav }}', ['services' => $this->services()]);

        $this->assertStringContainsString('href="#advies"', $html);
        $this->assertStringContainsString('href="#installatie"', $html);
        $this->assertStringContainsString('href="#onderhoud"', $html);
        $this->assertStringContainsString('href="#garantie"', $html);

        $this->assertLessThan(
            strpos($html, 'href="#garantie"'),
            strpos($html, 'href="#advies"'),
            'De volgorde van de pills moet die van de services volgen.'
        );
    }

    public function test_the_anchor_matches_the_slug_of_the_overline(): void
    {
        // Dit is het contract met textImage: het template geeft daar
        // `overline | slugify` als anker mee. Wijkt de slugificatie hier af,
        // dan wijst de pill naar een sectie die niet bestaat.
        $html = $this->render('{{ partial:sectionNav }}', [
            'services' => [['overline' => 'Advies op Maat']],
        ]);

        $this->assertStringContainsString('href="#advies-op-maat"', $html);
    }

    public function test_skips_a_service_without_an_overline(): void
    {
        // Zonder overline is er geen anker om naartoe te springen. De guard is
        // load-bearing: zonder hem rendert er een pill met href="#".
        $html = $this->render('{{ partial:sectionNav }}', [
            'services' => [
                ['overline' => 'Advies'],
                ['title' => 'Sectie zonder overline'],
            ],
        ]);

        $this->assertSame(1, substr_count($html, 'class="section-nav__link"'));
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_links_to_the_reparation_section(): void
    {
        $html = $this->render('{{ partial:sectionNav }}', ['services' => $this->services()]);

        $this->assertStringContainsString('href="#herstelling"', $html);
        $this->assertStringContainsString('Herstelling melden', $html);
        $this->assertStringContainsString('section-nav__link--report', $html);
    }

    public function test_is_hidden_below_the_lg_breakpoint(): void
    {
        $html = $this->render('{{ partial:sectionNav }}', ['services' => $this->services()]);

        $this->assertStringContainsString('hidden lg:block', $html);
    }

    public function test_renders_nothing_without_services(): void
    {
        $html = $this->render('{{ partial:sectionNav }}');

        $this->assertStringNotContainsString('<nav', $html);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/ServiceNavTest.php`

Expected: FAIL. De partial bestaat nog niet; Antlers laat `{{ partial:sectionNav }}` dan als lege string vallen of gooit een view-exception — beide is goed, als het maar rood is.

- [ ] **Step 3: Schrijf de partial**

Maak `resources/views/partials/sectionNav.antlers.html`:

```antlers
{{#
    Ankerbalk van de servicepagina (Figma 318:2983). Vaste component, geen
    page-builder sectie: geen argumenten, leest `services` uit de cascade —
    zelfde patroon als partials/quicklinks.antlers.html.

    NIET `quicklinks` genoemd: die naam is bezet door de drie CTA-kaarten
    (content/collections/quicklinks, partials/quicklinks.antlers.html,
    components/quicklinks.css). Dit is anker-navigatie binnen één pagina.

    Alleen vanaf `lg` zichtbaar, per opdracht. Onder die breedte verdwijnt de
    balk volledig; de secties zelf blijven staan, dus er gaat geen inhoud
    verloren. Zie section-nav.css voor wat dat met de sectie-collapse doet.

    De scheidingslijn zit hier en niet in `headers/default divider="true"`:
    dat argument zet 112px tussen de lijn en wat erop volgt, het design 32px.

    De klassenstrings staan voluit — Tailwind's scanner vindt
    runtime-samengestelde klassenamen niet.
#}}
{{ if services }}
    <nav class="hidden lg:block" aria-label="Op deze pagina" data-section="section-nav">
        <div class="container">
            <div class="border-t border-black/10 pt-8 pb-24">
                <ul class="flex flex-wrap items-center gap-4">
                    {{ services }}
                        {{ if overline }}
                            <li>
                                <a href="#{{ overline | slugify }}" class="section-nav__link">
                                    {{ overline }}
                                    {{ icon src="arrow-down" class="size-3.5 shrink-0" }}
                                </a>
                            </li>
                        {{ /if }}
                    {{ /services }}

                    <li class="ml-auto">
                        <a href="#herstelling" class="section-nav__link section-nav__link--report">
                            {{ icon src="wrench" class="size-6 shrink-0" }}
                            Herstelling melden
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
{{ /if }}
```

- [ ] **Step 4: Schrijf de CSS**

Maak `resources/css/components/section-nav.css`:

```css
/*
 * De pills van de ankerbalk (Figma 318:2983).
 *
 * NIET `.btn--outline`: die heeft `border-black`, terwijl deze pills een
 * lichte rand hebben — dezelfde lijn als de headerdivider. Dat op de
 * aanroepplek overriden met losse utilities zou de knopvormen over twee
 * bestanden verspreiden.
 *
 * Dit is wel de vijfde pill met dezelfde vormdeclaraties. De follow-up om
 * een `.btn--pill`-basis te extraheren staat open in
 * docs/superpowers/specs/2026-07-26-pagebuilder-sections-followups.md; die
 * raakt vier bestaande secties en hoort in een eigen diff.
 */

.section-nav__link {
    @apply inline-flex w-fit items-center justify-center gap-2 rounded-full border border-black/12 px-8 py-4 font-semibold text-base text-black transition-colors duration-200 ease-out;
}

.section-nav__link:hover,
.section-nav__link:focus-visible {
    @apply border-black;
}

.section-nav__link--report {
    @apply border-transparent bg-black text-white;
}

.section-nav__link--report:hover,
.section-nav__link--report:focus-visible {
    @apply border-transparent bg-dark;
}

/*
 * De balk staat tússen de header en de eerste sectie, dus de
 * `.section--default + .section--default`-collapse uit base/section.css kan
 * niet meer matchen — een `+`-selector kijkt naar de DOM, en `display: none`
 * haalt de balk daar niet uit. Onder `lg` is de balk verborgen en hoort die
 * collapse er wél te zijn; zonder deze regel staat er op mobiel 128px tussen
 * header en eerste sectie waar elders 64px staat.
 *
 * Geen `!important` nodig, in tegenstelling tot de regel in section.css:
 * deze selector is specifieker (0,2,0 tegen 0,1,0) en beide bestanden staan
 * ongelaagd, dus geen enkele Tailwind-utility komt ertussen.
 */
@media (max-width: 1023px) {
    [data-section='section-nav'] + .section--default {
        padding-top: 0;
    }
}

/*
 * Opeenvolgende `.section--default` krijgen `padding-top: 0`, dus een anker
 * zou tegen de bovenrand van het venster plakken. Deze marge geeft de sprong
 * lucht. De sitenavigatie is niet sticky, dus er hoeft geen headerhoogte
 * gecompenseerd te worden.
 */
[data-section='text_image'][id],
[data-section='reparation'] {
    scroll-margin-top: var(--spacing-section-half);
}
```

- [ ] **Step 5: Registreer de CSS en zet vloeiend scrollen aan**

Voeg onderaan de componentenlijst in `resources/css/site.css` toe, ná de `quicklinks.css`-regel:

```css
@import './components/section-nav.css';
```

Voeg bovenaan `resources/css/base/global.css` toe, vóór de `body`-regel:

```css
/*
 * Vloeiend scrollen naar de ankers van partials/sectionNav.antlers.html.
 * Geen JavaScript: de balk is gewone `<a href="#…">`, dus de sprong werkt
 * ook zonder JS en verliest dan alleen de animatie. Uit bij "beweging
 * beperken" — een lange sprong met vestibulaire klachten is geen animatie
 * die je wil forceren.
 */
html {
    scroll-behavior: smooth;
}

@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }
}
```

- [ ] **Step 6: Draai de test en bevestig dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/ServiceNavTest.php`

Expected: PASS, 6 tests.

Draai daarna ook de volledige suite: `php -d memory_limit=1G ./vendor/bin/phpunit` → OK, 194 tests (188 + 6), 1 skipped. De nieuwe regel in `section.css`-gebied raakt elke pagina met opeenvolgende secties.

- [ ] **Step 7: Bouw de CSS en controleer dat de klassen erin zitten**

```bash
npm run build
grep -c "section-nav__link" public/build/assets/site-*.css
```

Expected: minstens 1. Slaat dit nul terug, dan heeft Tailwind de klassen niet gevonden — controleer of `@source '../views'` in `site.css` de partial dekt.

- [ ] **Step 8: Commit**

```bash
git add resources/views/partials/sectionNav.antlers.html resources/css/components/section-nav.css resources/css/site.css resources/css/base/global.css tests/Feature/Sections/ServiceNavTest.php
git commit -m "feat(sectionNav): ankerbalk met vloeiend scrollen

Leest services uit de cascade en linkt naar het geslugificeerde overline,
plus een donkere knop naar de herstellingssectie. Alleen vanaf lg zichtbaar;
onder die breedte herstelt de CSS de sectie-collapse die de balk verbreekt."
```

---

## Task 3: Het herstellingsformulier

**Files:**
- Create: `resources/blueprints/forms/herstelling.yaml`
- Create: `resources/forms/herstelling.yaml`
- Create: `resources/views/partials/reparationForm.antlers.html`
- Modify: `resources/css/components/form.css` (volledig vervangen)
- Modify: `resources/views/contact.antlers.html`
- Test: `tests/Feature/Sections/ReparationFormTest.php`

**Interfaces:**
- Consumes: niets uit taak 1 of 2.
- Produces: `{{ partial:reparationForm }}` — geen argumenten. Rendert `<form class="form">` met twee `.form-section`-blokken en acht velden. Taak 4 include't dit.
- CSS-klassen die taak 4 mag gebruiken: `.form`, `.form-section`, `.form-grid`, `.form-field`, `.form-field--half`, `.form-label`, `.form-select-wrap`, `.form-dropzone`, `.form-error`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ReparationFormTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ReparationFormTest extends SectionTestCase
{
    public function test_renders_both_blueprint_sections_with_a_divider_between_them(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame(2, substr_count($html, 'class="form-section"'));
    }

    public function test_renders_every_field_from_the_blueprint(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        foreach (['product', 'installed', 'problem', 'branch', 'photo', 'email', 'name', 'phone'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }
    }

    public function test_marks_exactly_the_four_paired_fields_as_half_width(): void
    {
        // product+installed en name+phone staan in het design naast elkaar.
        // De klasse volgt uit `width: 50` in het blueprint, niet uit dit template.
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame(4, substr_count($html, 'form-field--half'));
    }

    public function test_the_branch_options_come_from_the_locations_collection(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
        $this->assertStringContainsString('Kies een filiaal…', $html);
    }

    public function test_the_photo_field_is_a_file_input_inside_the_dropzone(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('form-dropzone', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('Sleep een foto hierheen of klik om te uploaden', $html);
    }

    public function test_accepts_file_uploads(): void
    {
        // Statamic zet enctype alleen als het blueprint een assets-veld heeft.
        // Valt `photo` weg, dan verdwijnt dit attribuut stil en uploadt de
        // browser alleen de bestandsnaam.
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function test_every_label_points_at_its_control(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('for="herstelling-form-product-field"', $html);
        $this->assertStringContainsString('id="herstelling-form-product-field"', $html);
    }

    public function test_the_submit_button_uses_the_accent_style(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('btn btn--accent', $html);
        $this->assertStringContainsString('>Herstelling melden<', $html);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/ReparationFormTest.php`

Expected: FAIL — de partial en het formulier bestaan nog niet.

- [ ] **Step 3: Maak het formulier-blueprint**

Maak `resources/blueprints/forms/herstelling.yaml`:

```yaml
title: Herstelling
tabs:
  main:
    display: Main
    sections:
      -
        display: Probleem
        fields:
          -
            handle: product
            field:
              type: text
              display: 'Welk product gaat het over?'
              placeholder: 'bv. Pergola SO!, rolluik, raam…'
              width: 50
          -
            handle: installed
            field:
              type: text
              display: 'Ongeveer wanneer geplaatst?'
              placeholder: 'bv. 2021'
              width: 50
          -
            handle: problem
            field:
              type: textarea
              display: 'Wat is er aan de hand?'
              placeholder: 'Beschrijf het probleem zo concreet mogelijk.'
          -
            handle: branch
            field:
              type: select
              display: 'Naar welk filiaal?'
              placeholder: 'Kies een filiaal…'
          -
            handle: photo
            field:
              type: assets
              container: assets
              folder: herstellingen
              max_files: 1
              display: 'Foto van het probleem (optioneel)'
      -
        display: Contact
        fields:
          -
            handle: email
            field:
              type: text
              input_type: email
              display: E-mail
              placeholder: 'naam@voorbeeld.be'
          -
            handle: name
            field:
              type: text
              display: Naam
              placeholder: 'Voor- en achternaam'
              width: 50
          -
            handle: phone
            field:
              type: text
              display: Telefoon
              placeholder: '+32 …'
              width: 50
```

`branch` draagt bewust geen `options`: het template rendert die select zelf uit de `locations`-collectie. Geen `validate`-regels: het formulier verwerkt nog niets, en verplichte velden zonder verwerking leveren alleen een dood foutpad op.

- [ ] **Step 4: Maak de form-configuratie**

Maak `resources/forms/herstelling.yaml`:

```yaml
title: Herstelling
```

Geen `email:`-blok — het formulier verstuurt niets. Zie de follow-ups van taak 5.

- [ ] **Step 5: Schrijf het formulier-partial**

Maak `resources/views/partials/reparationForm.antlers.html`:

```antlers
{{#
    Het herstellingsformulier (Figma 318:3097).

    De veldloop is generiek: labels, breedtes, placeholders en foutmeldingen
    komen uit resources/blueprints/forms/herstelling.yaml, niet uit dit
    bestand. `{{ width }}` zet Statamic altijd, met 100 als default
    (Statamic\Fields\Field::toArray). `{{ sections }}` komt uit
    Statamic\Forms\Tags::getSections en volgt de secties van het blueprint —
    zo volgt de scheidingslijn uit het design uit de datastructuur in plaats
    van uit een marker in de markup.

    De halve-breedte-klasse staat voluit in de `{{ if }}`; Tailwind's scanner
    vindt runtime-samengestelde klassenamen niet.

    Twee velden vallen buiten de generieke tak:

    - `branch`: de drie filialen komen uit de `locations`-collectie, zodat ze
      niet naast het blueprint een tweede keer bestaan. Een Entries-veld kan
      dit niet — alleen Select, Radio, Checkboxes en ButtonGroup geven opties
      door aan de frontend (Statamic\Fieldtypes\HasSelectOptions).
    - `photo`: de streepjeszone. De `<input type="file">` ligt transparant
      over de hele zone; browsers accepteren drag-and-drop rechtstreeks op een
      file-input, dus slepen én klikken werken zonder JavaScript.

    Er staat nergens `{{ field class="…" }}`: Antlers parseert dat als
    modifier en gooit "Modifier [class] not found". De fieldtype-views
    accepteren geen class-attribuut, dus form.css stijlt de controls via
    `.form-field`.
#}}
{{ form:herstelling class="form" }}
    {{ sections }}
        <div class="form-section">
            <div class="form-grid">
                {{ fields }}
                    <div class="form-field{{ if width == 50 }} form-field--half{{ /if }}">
                        <label class="form-label" for="{{ id }}">{{ display }}</label>

                        {{ if handle == "branch" }}
                            <div class="form-select-wrap">
                                <select id="{{ id }}" name="{{ name }}">
                                    <option value="">{{ placeholder }}</option>
                                    {{ collection:locations }}
                                        <option value="{{ slug }}">{{ name }}</option>
                                    {{ /collection:locations }}
                                </select>
                                {{ icon src="caret-down" }}
                            </div>
                        {{ elseif handle == "photo" }}
                            <div class="form-dropzone">
                                {{ icon src="upload" class="size-7" }}
                                <span>Sleep een foto hierheen of klik om te uploaden</span>
                                {{ field }}
                            </div>
                        {{ else }}
                            {{ field }}
                        {{ /if }}

                        {{ if error }}
                            <p class="form-error" id="{{ id }}-error">{{ error }}</p>
                        {{ /if }}
                    </div>
                {{ /fields }}
            </div>
        </div>
    {{ /sections }}

    <input type="text" class="hidden" name="{{ honeypot ?? 'honeypot' }}">

    <button type="submit" class="btn btn--accent">Herstelling melden</button>
{{ /form:herstelling }}
```

> **Let op bij `branch`:** binnen `{{ collection:locations }}` verwijst `{{ name }}` naar de naam van de locatie, niet naar het `name`-attribuut van het formulierveld. Dat is precies de bedoeling — de `name="{{ name }}"` op de `<select>` staat buiten die loop.

- [ ] **Step 6: Herschrijf `form.css`**

Vervang de volledige inhoud van `resources/css/components/form.css` door:

```css
/*
 * Formulieropmaak (Figma 318:3097).
 *
 * De klassen staan op de wrappers, niet op de inputs zelf. Statamic's
 * `{{ field }}` rendert de fieldtype-views uit
 * vendor/statamic/cms/resources/views/extend/forms/fields/*.antlers.html en
 * die accepteren geen class-attribuut: `{{ field class="…" }}` wordt als
 * modifier geparsed en gooit "Modifier [class] not found". Die views
 * overriden in resources/views/vendor/statamic/forms/ kan wel, maar koppelt
 * de opmaak aan een vendor-bestandsindeling die bij een upgrade kan
 * schuiven. Vandaar: `.form-field` stijlt zijn eigen controls, zodat
 * gegenereerde en handgeschreven controls er identiek uitzien.
 *
 * Kleuren gemeten uit het Figma-render: vulling #f5f5f5, streepjesrand
 * #bfbfbf. De vulling is nadrukkelijk niet `--color-light` (#f1f6f8) —
 * dat zweemt blauw op de witte kaart.
 */

.form {
    @apply flex flex-col gap-8 lg:gap-12;
}

.form-section {
    @apply flex flex-col gap-6;
}

.form-section + .form-section {
    @apply border-t border-black/10 pt-8 lg:pt-12;
}

.form-grid {
    @apply grid gap-6 sm:grid-cols-2;
}

/*
 * Elk veld beslaat standaard de volle breedte; `--half` zet het terug naar
 * één kolom. Zo is de brede variant de default en hoeft het blueprint alleen
 * de uitzonderingen (`width: 50`) te dragen.
 */
.form-field {
    @apply flex flex-col gap-2 sm:col-span-2;
}

.form-field--half {
    @apply sm:col-span-1;
}

.form-label {
    @apply font-semibold text-black;
}

/*
 * `input[type="file"]` uitgesloten: die ligt transparant over `.form-dropzone`
 * en mag de vulling van een gewoon veld niet krijgen.
 */
.form-field :is(input:not([type='file']), textarea, select) {
    @apply w-full rounded-md border-none bg-[#f5f5f5] px-6 py-4 text-black;
}

.form-field :is(input, textarea)::placeholder {
    @apply text-black/40;
}

.form-field textarea {
    @apply min-h-34 resize-y;
}

.form-field select {
    @apply appearance-none pr-12;
}

.form-select-wrap {
    @apply relative;
}

.form-select-wrap svg {
    @apply pointer-events-none absolute top-1/2 right-6 size-3.5 -translate-y-1/2 text-black;
}

.form-dropzone {
    @apply relative flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-[#bfbfbf] px-6 py-8 text-center text-black/40;
}

.form-dropzone input[type='file'] {
    @apply absolute inset-0 size-full cursor-pointer opacity-0;
}

.form-error {
    @apply text-sm text-red-600;
}
```

- [ ] **Step 7: Zet `contact.antlers.html` op de nieuwe klassen**

De oude `form.css` stijlde `form`, `label` en `input` als elementen. Die regels zijn weg, dus `/contact` zou anders op browserdefaults terugvallen. In `resources/views/contact.antlers.html`, in het `{{ form:contact }}`-blok:

- `{{ form:contact }}` wordt `{{ form:contact class="form" }}`
- `<fieldset>` wordt `<div class="form-field">`, en `</fieldset>` wordt `</div>`
- `<label>{{ display }}</label>` wordt `<label class="form-label" for="{{ id }}">{{ display }}</label>`
- `<p>{{ error }}</p>` wordt `<p class="form-error">{{ error }}</p>`

Laat de rest van dat bestand ongemoeid.

- [ ] **Step 8: Draai de tests en bevestig dat ze slagen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/ReparationFormTest.php`

Expected: PASS, 8 tests.

- [ ] **Step 9: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`

Expected: OK, 202 tests (194 + 8), 1 skipped.

- [ ] **Step 10: Commit**

```bash
git add resources/blueprints/forms/herstelling.yaml resources/forms/herstelling.yaml resources/views/partials/reparationForm.antlers.html resources/css/components/form.css resources/views/contact.antlers.html tests/Feature/Sections/ReparationFormTest.php
git commit -m "feat(formulier): herstellingsformulier, opgemaakt maar nog niet verwerkend

De veldloop is generiek: labels, breedtes en de scheidingslijn volgen uit
het blueprint. Filiaal komt uit de locations-collectie en de fotozone is een
transparante file-input, dus slepen werkt zonder JavaScript.

form.css stapt over van elementselectors naar herbruikbare klassen; het
contactformulier gaat mee, anders viel dat terug op browserdefaults.

Er is geen email-blok: het formulier verstuurt nog niets."
```

---

## Task 4: De herstellingssectie

**Files:**
- Create: `resources/views/partials/sections/reparation.antlers.html`
- Modify: `resources/blueprints/collections/pages/services_overview.yaml`
- Test: `tests/Feature/Sections/ReparationSectionTest.php`

**Interfaces:**
- Consumes: `{{ partial:reparationForm }}` uit taak 3, en de klassen uit `form.css`.
- Produces: `{{ partial:sections/reparation }}` — geen argumenten, leest `reparation` uit de cascade. Rendert `<section id="herstelling" data-section="reparation">`. Taak 2's donkere pill linkt hierheen; taak 5 include't het.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ReparationSectionTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ReparationSectionTest extends SectionTestCase
{
    private function reparation(): array
    {
        return [
            'overline' => 'Herstelling',
            'title' => 'Iets stuk of werkt iets niet meer?',
            'text' => 'Voor bestaande klanten met een probleem.',
            'image' => 'quicklinks/herstelling.png',
        ];
    }

    public function test_carries_the_anchor_the_nav_links_to(): void
    {
        // Het contract met sectionNav: die rendert href="#herstelling".
        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        $this->assertStringContainsString('id="herstelling"', $html);
        $this->assertStringContainsString('data-section="reparation"', $html);
    }

    public function test_renders_the_header_and_the_form(): void
    {
        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        $this->assertStringContainsString('Iets stuk of werkt iets niet meer?', $html);
        $this->assertStringContainsString('Herstelling', $html);
        $this->assertStringContainsString('class="form-section"', $html);
    }

    public function test_renders_the_decorative_watermark_out_of_the_accessibility_tree(): void
    {
        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $this->reparation(),
        ]);

        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_renders_without_an_image(): void
    {
        // De koffer is decoratief. Ontbreekt hij, dan mag de sectie niet breken
        // en mag er geen lege beeldkolom overblijven.
        $reparation = $this->reparation();
        unset($reparation['image']);

        $html = $this->render('{{ partial src="sections/reparation" }}', [
            'reparation' => $reparation,
        ]);

        $this->assertStringContainsString('id="herstelling"', $html);
        $this->assertStringNotContainsString('reparation__media', $html);
    }

    public function test_renders_nothing_without_a_reparation_group(): void
    {
        $html = $this->render('{{ partial src="sections/reparation" }}');

        $this->assertStringNotContainsString('id="herstelling"', $html);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/ReparationSectionTest.php`

Expected: FAIL — de partial bestaat nog niet.

- [ ] **Step 3: Voeg het `image`-veld toe aan het blueprint**

In `resources/blueprints/collections/pages/services_overview.yaml`, binnen de `reparation`-group, ná het `text`-veld:

```yaml
                -
                  import: image
```

Let op de indentatie: dat `-` staat op hetzelfde niveau als de `-` van `handle: text` binnen `fields:` van de group.

- [ ] **Step 4: Schrijf de partial**

Maak `resources/views/partials/sections/reparation.antlers.html`:

```antlers
{{#
    De herstellingssectie (Figma 318:3087). Vaste component, geen page-builder
    sectie: geen argumenten, leest de `reparation`-group uit de cascade.

    `id="herstelling"` is het doelwit van de donkere pill in
    partials/sectionNav.antlers.html. Verandert die naam hier, dan wijst die
    pill nergens naar; ReparationSectionTest en ServiceNavTest dekken beide
    kanten van dat contract.

    Het watermerk is dezelfde SVG die headers/range.antlers.html gebruikt.
    Decoratief, dus buiten de toegankelijkheidsboom.

    De koffer steekt in Figma links buiten het raster (node 361:4112, x: -97).
    Die negatieve marge slaat pas vanaf `lg` aan: onder die breedte zou hij
    horizontale overflow op de hele pagina veroorzaken.
#}}
{{ if reparation }}
    {{ reparation }}
        <section id="herstelling" class="section section--default relative overflow-hidden bg-light" data-section="reparation">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
                {{ svg src="watermark" class="absolute -bottom-[10%] -left-[30%] h-auto w-[110%] lg:-left-[38%] lg:w-[85%]" }}
            </div>

            <div class="container">
                <div class="section-x-gap items-start">
                    <div class="section-col-wide flex flex-col gap-12">
                        {{ partial:sectionHeader }}

                        {{ if image }}
                            <div class="reparation__media lg:-ml-24 xl:-ml-32">
                                {{ img :src="image" max_width="1200" sizes="(min-width: 1024px) 40vw, 90vw" class="h-auto w-full" }}
                            </div>
                        {{ /if }}
                    </div>

                    <div class="section-col-wide rounded-md bg-white p-6 lg:p-12">
                        {{ partial:reparationForm }}
                    </div>
                </div>
            </div>
        </section>
    {{ /reparation }}
{{ /if }}
```

> **Waarom `{{ if reparation }}` én `{{ reparation }}`:** de tweede tag opent de group als scope zodat `sectionHeader` de `overline`, `title` en `text` erbinnen vindt. De `{{ if }}` eromheen voorkomt dat de sectie rendert op een pagina zonder die group.

- [ ] **Step 5: Draai de test en bevestig dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Sections/ReparationSectionTest.php`

Expected: PASS, 5 tests.

Faalt `test_renders_the_header_and_the_form` op de overline, controleer dan of `{{ partial:sectionHeader }}` de group-scope ziet — dat is het punt waar de dubbele tag hierboven voor staat.

- [ ] **Step 6: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`

Expected: OK, 207 tests (202 + 5), 1 skipped.

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/sections/reparation.antlers.html resources/blueprints/collections/pages/services_overview.yaml tests/Feature/Sections/ReparationSectionTest.php
git commit -m "feat(reparation): de band rond het herstellingsformulier

Lichte band met het bestaande watermerk, links de tekst met de
gereedschapskoffer en rechts het formulier op een witte kaart. Het
image-veld op de reparation-group is nieuw; de koffer is decoratief en de
sectie rendert eromheen als hij ontbreekt."
```

---

## Task 5: De pagina

**Files:**
- Create: `content/collections/pages/service.md`
- Create: `resources/views/service.antlers.html`
- Test: `tests/Feature/Content/ServicePageTest.php`

**Interfaces:**
- Consumes: alles uit taak 1 tot en met 4.
- Produces: de route `/service`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/ServicePageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ServicePageTest extends TestCase
{
    public function test_the_entry_uses_the_services_overview_blueprint_and_template(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'service')->first();

        $this->assertNotNull($entry, 'De service-entry ontbreekt.');
        $this->assertSame('services_overview', $entry->blueprint()->handle());
        $this->assertSame('service', $entry->get('template'));
    }

    public function test_the_reparation_group_carries_an_image_field(): void
    {
        $blueprint = Blueprint::find('collections.pages.services_overview');

        $this->assertTrue(
            $blueprint->hasField('reparation'),
            'De reparation-group ontbreekt in het blueprint.'
        );
        $this->assertArrayHasKey(
            'image',
            collect($blueprint->field('reparation')->get('fields'))->keyBy('handle')->all(),
            'Het image-veld ontbreekt op de reparation-group.'
        );
    }

    public function test_the_page_renders_the_four_sections_and_the_form(): void
    {
        $response = $this->get('/service');

        $response->assertOk();
        $response->assertSee('id="advies"', false);
        $response->assertSee('id="installatie"', false);
        $response->assertSee('id="onderhoud"', false);
        $response->assertSee('id="garantie"', false);
        $response->assertSee('id="herstelling"', false);
    }

    public function test_the_nav_anchors_all_resolve_to_a_section_on_the_page(): void
    {
        // Dit is de test die het hele plan aan elkaar knoopt: sectionNav bouwt
        // de hrefs uit de overlines, textImage bouwt de ids uit dezelfde bron.
        // Loopt dat uiteen, dan springt de balk nergens naartoe.
        $html = $this->get('/service')->getContent();

        preg_match_all('/href="#([^"]+)"/', $html, $matches);
        $anchors = array_unique($matches[1]);

        $this->assertNotEmpty($anchors, 'Er staan geen ankerlinks op de pagina.');

        foreach ($anchors as $anchor) {
            $this->assertStringContainsString(
                'id="'.$anchor.'"',
                $html,
                "De ankerlink #{$anchor} wijst naar een sectie die niet bestaat."
            );
        }
    }

    public function test_the_sections_alternate_starting_with_the_image(): void
    {
        $html = $this->get('/service')->getContent();

        // Sectie 1 en 3 hebben `order-last` op de tekstkolom (beeld links),
        // sectie 2 en 4 niet.
        $this->assertSame(2, substr_count($html, 'order-last'));
    }
}
```

> `test_the_nav_anchors_all_resolve_to_a_section_on_the_page` leest álle `href="#…"` op de pagina, dus ook die van header, footer en skiplink. Slaat hij aan op `#main-content`, dan is dat terecht — `layout.antlers.html` zet dat id — maar controleer bij een onverwachte failure eerst of het anker uit de balk komt.

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Content/ServicePageTest.php`

Expected: FAIL — de entry en het template bestaan nog niet.

- [ ] **Step 3: Maak de entry**

Maak `content/collections/pages/service.md`:

```markdown
---
id: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a04
blueprint: services_overview
title: Service
text: 'Van eerste advies tot lang na de plaatsing — u kunt op ons rekenen voor de hele levensduur van uw installatie.'
template: service
seo_noindex: false
services:
  -
    id: serviceadvies
    type: service
    overline: Advies
    title: 'Advies op maat'
    text: 'Voor u iets beslist, denken we met u mee. We komen gratis langs, meten alles correct op en bekijken uw woning ter plaatse. Op basis daarvan stellen we de oplossing voor die past bij uw situatie, smaak en budget. U krijgt eerlijk advies en een heldere offerte, zonder verrassingen achteraf.'
    image: dummy-images/test-img-1.jpg
  -
    id: serviceinstallatie
    type: service
    overline: Installatie
    title: 'Vakkundige installatie'
    text: 'Uw installatie wordt geplaatst door ons eigen team — geen onderaannemers. Onze vakmensen kennen hun werk en behandelen uw woning met respect. We werken netjes en ruimen na de plaatsing alles op. Bij de oplevering tonen we hoe alles werkt, zodat u meteen zorgeloos kunt genieten.'
    image: dummy-images/test-img-2.jpg
  -
    id: serviceonderhoud
    type: service
    overline: Onderhoud
    title: 'Onderhoud en nazicht'
    text: 'Een goed onderhouden installatie gaat jaren langer mee. We komen op afspraak langs voor periodiek nazicht, afstelling en smering van de bewegende delen. Kleine herstellingen pakken we meteen mee, voor ze grote problemen worden. Zo blijft alles vlot en veilig werken, seizoen na seizoen. En zit u toch met iets, dan staan we snel ter plaatse.'
    image: dummy-images/test-img-3.jpg
  -
    id: servicegarantie
    type: service
    overline: Garantie
    title: 'Garantie en nazorg'
    text: 'Op elke installatie geldt zowel fabrieks- als plaatsingsgarantie. En ook ná de plaatsing blijven we gewoon bereikbaar. U heeft één vast aanspreekpunt in uw buurt, ook jaren later. Heeft u een vraag of een probleem, dan volgen we het vlot voor u op. Zo staat u nooit alleen met uw aankoop.'
    image: dummy-images/test-img-4.jpg
reparation:
  overline: Herstelling
  title: 'Iets stuk of werkt iets niet meer?'
  text: 'Voor bestaande klanten met een probleem. Beschrijf kort wat er aan de hand is — we nemen contact op om langs te komen.'
  image: quicklinks/herstelling.png
---
```

De vier `dummy-images` zijn tijdelijk: het design toont lege placeholders en er zijn nog geen echte servicefoto's. `quicklinks/herstelling.png` staat wél klaar in de assets-container.

- [ ] **Step 4: Hang de entry in de paginaboom**

Voeg onderaan `content/trees/collections/pages.yaml` toe:

```yaml
  -
    entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a04
```

Zonder deze stap heeft de entry geen URL en geeft `/service` een 404.

- [ ] **Step 5: Schrijf het template**

Maak `resources/views/service.antlers.html`:

```antlers
{{#
    De servicepagina (Figma 318:2955).

    Geen `divider="true"` op de header: dat argument zet 112px tussen de lijn
    en wat erop volgt, en het design heeft 32px. De lijn zit daarom in
    partials/sectionNav.antlers.html, samen met de pills die eronder staan.

    `count` is 1-gebaseerd (`index` is dat niet), dus `count % 2 == 0` is waar
    voor sectie 2 en 4 — precies de twee die in het design de tekst links
    hebben.

    Het anker is `overline | slugify`, dezelfde uitdrukking die sectionNav
    voor zijn hrefs gebruikt. Die twee moeten gelijk blijven;
    ServicePageTest::test_the_nav_anchors_all_resolve_to_a_section_on_the_page
    bewaakt dat.
#}}
{{ partial:headers/default }}
{{ partial:sectionNav }}

{{ services }}
    {{ partial src="sections/textImage" :anchor="overline | slugify" :text_first="count % 2 == 0" }}
{{ /services }}

{{ partial src="sections/reparation" }}
```

- [ ] **Step 6: Draai de test en bevestig dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit tests/Feature/Content/ServicePageTest.php`

Expected: PASS, 5 tests.

- [ ] **Step 7: Draai de volledige suite en bouw de assets**

```bash
php -d memory_limit=1G ./vendor/bin/phpunit
npm run build
```

Expected: OK, 212 tests (207 + 5), 1 skipped. De build moet zonder fouten doorlopen.

- [ ] **Step 8: Commit**

```bash
git add content/collections/pages/service.md content/trees/collections/pages.yaml resources/views/service.antlers.html tests/Feature/Content/ServicePageTest.php
git commit -m "feat(service): de servicepagina op /service

Header, ankerbalk, vier afwisselende secties uit de services-replicator en
het herstellingsformulier. De ankers van de balk en de ids van de secties
komen uit dezelfde uitdrukking; een test bewaakt dat ze niet uiteenlopen.

De vier servicefoto's zijn dummy-images: het design toont placeholders."
```

- [ ] **Step 9: Handmatige browsercheck**

De tests dekken de markup, niet de vormgeving. Loop dit door op `/service`:

1. Op 1440px: vier secties, beeld links / tekst links / beeld links / tekst links.
2. De ankerbalk staat er, met vier lichte pills links en de donkere knop rechts uitgelijnd.
3. Klikken op elke pill scrollt vloeiend naar de juiste sectie, en de titel plakt niet tegen de bovenrand.
4. Met "beweging beperken" aan springt de pagina in plaats van te scrollen.
5. Op 402px: geen ankerbalk, geen scheidingslijn, geen horizontale scroll, en de afstand tussen header en eerste sectie is dezelfde als op `/aanbod`.
6. Op 402px: de gereedschapskoffer steekt niet buiten het scherm.
7. Het formulier: product en jaartal naast elkaar, naam en telefoon naast elkaar, de scheidingslijn boven E-mail, de filiaal-dropdown toont de drie Winsol-vestigingen.
8. Een foto naar de streepjeszone slepen laat de browser hem accepteren; klikken opent de bestandskiezer.

- [ ] **Step 10: Leg de follow-ups vast**

Maak `docs/superpowers/specs/2026-07-27-service-page-followups.md` met minstens deze punten:

- **Het formulier mag niet live voordat de verwerking geregeld is.** `resources/forms/herstelling.yaml` heeft geen `email:`-blok, dus er wordt niets verstuurd — maar Statamic schrijft een POST wél als inzending weg. Dat is een formulier dat in stilte inzendingen opslokt.
- **Geen recaptcha.** `partials/recaptcha.antlers.html` bestaat en wordt op `/contact` gebruikt; dit formulier heeft hem niet.
- **Geen drag-and-drop-feedback.** Slepen werkt, maar er is geen highlight tijdens het slepen en de gekozen bestandsnaam wordt niet getoond. Dat is JavaScript-werk.
- **Vier dummy-servicefoto's.** Te vervangen zodra de echte beelden er zijn.
- **`/service` staat niet in de navigatie.** `content/trees/navigation/main.yaml` bevat alleen *Over ons*, *Projecten* en *Contact*; Aanbod en Realisaties ontbreken er ook. De nav is als geheel achterstallig.
- **Vijfde pill-vormige knop.** `.section-nav__link` herhaalt de vormdeclaraties van `.btn--accent`, `.btn--outline`, `.btn--dark` en `.btn--cta`. De `.btn--pill`-extractie staat open in `2026-07-26-pagebuilder-sections-followups.md`.
- **De checklists uit het design zijn niet gebouwd.** Figma-nodes `318:3008`, `318:3029`, `318:3052` en `318:3073` staan op `hidden`. `textImage` heeft er een `features`-argument voor als ze terugkomen.
- **`#f5f5f5` en `#bfbfbf` zijn arbitraire waarden in `form.css`.** Geen `@theme`-token, omdat ze alleen in dit component voorkomen. Komt er een tweede formulier met dezelfde vulling, dan horen ze in `site.css`.

```bash
git add docs/superpowers/specs/2026-07-27-service-page-followups.md
git commit -m "docs(specs): wat de servicepagina open laat"
```

---

## Verificatie na afloop

| Check | Commando | Verwacht |
|---|---|---|
| Volledige suite | `php -d memory_limit=1G ./vendor/bin/phpunit` | OK, 212 tests, 1 skipped |
| Assets bouwen | `npm run build` | geen fouten |
| Nieuwe klassen in de build | `grep -c "section-nav__link\|form-dropzone" public/build/assets/site-*.css` | > 0 |
| Werkboom schoon | `git status --porcelain` | leeg |
