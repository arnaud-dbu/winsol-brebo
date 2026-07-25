# Page Builder Sections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Alle elf `page_builder` sets, de page header, navigatie en footer visueel afwerken volgens Figma, met een showcase-pagina die alles toont.

**Architecture:** Token-first CSS: fluid typografie en spacing-utilities in `resources/css`, layout als Tailwind-utilities in de Antlers-partials. Vijf gedeelde partials (`overline`, `sectionHeader`, `featureList`, `card`, `slider`) dragen het merendeel van de secties. Eén generieke Swiper-initializer bedient alle sliders via `data-slider-*` attributen.

**Tech Stack:** Statamic 6 (Antlers), Tailwind CSS v4 (`@theme`), Vite, Alpine.js, Swiper, PHPUnit.

## Global Constraints

- **Figma bron:** fileKey `dgMxUtoYzYrR5FRuwPzQBn`. Desktop `/page-builder` = `451:2676`, mobile = `451:3003`.
- **Design tokens (Figma variables, exact):** `accent #f8d71c`, `black #121b22`, `white #ffffff`, `light #f1f6f8`. `margin` 40 (desktop) / 20 (mobile), `gutter` 32, `section` 112, `section-half` 64.
- **Typografie (Figma, desktop → mobile):** H1 61→39, H2 49→31, H3 31→20, P 20→16, P-lg 25→18, Overline 16→12. Alle koppen General Sans Semibold (600), line-height 1.1, letter-spacing 0. Overline: Semibold, line-height 1.1, **letter-spacing 2px**, uppercase. Body: Regular (400), line-height 1.5.
- **Regel:** typografie fluid via `clamp()`, spacing via breakpoints.
- Geen hardcoded NL in partials — vaste UI-teksten via `{{ trans:site.x }}` uit `lang/nl/site.php`.
- Alle beelden via de bestaande `{{ img }}`-component met expliciete `sizes`.
- Geen wijzigingen aan blueprints of fieldsets tenzij een taak dat expliciet voorschrijft.
- Elke sectie-partial staat in `resources/views/partials/sections/` en wordt aangeroepen vanuit `pageBuilder.antlers.html`.
- Commit na elke taak.

---

## Bestandsoverzicht

**Nieuw**

| Bestand | Verantwoordelijkheid |
|---|---|
| `resources/views/partials/overline.antlers.html` | label + streepje |
| `resources/views/partials/featureList.antlers.html` | ✓-lijstje |
| `resources/views/partials/slider.antlers.html` | Swiper-markup + nav/pagination |
| `resources/views/partials/sections/*.antlers.html` | elf secties |
| `resources/js/components/sliders.js` | generieke Swiper-init |
| `resources/css/components/overline.css`, `card.css`, `slider.css` | component-styling |
| `lang/nl/site.php` | vaste UI-teksten |
| `tests/Feature/Sections/SectionTestCase.php` | render-helper voor partial-tests |
| `tests/Feature/Sections/*Test.php` | één test per partial |

**Gewijzigd**

| Bestand | Wijziging |
|---|---|
| `resources/css/site.css` | fluid `--text-*`, spacing-tokens, `--color-dark`, imports |
| `resources/css/base/typography.css` | h1–h4 en p op de fluid tokens |
| `resources/css/base/spacing.css` | `grid-gutter`, `card-padding`, `slider-bleed` |
| `resources/css/base/section.css`, `container.css` | uitgelijnd op Figma-tokens |
| `resources/css/components/button.css` | Figma-varianten |
| `resources/views/partials/pageBuilder.antlers.html` | alle elf sets |
| `resources/views/partials/sectionHeader.antlers.html`, `card.antlers.html` | herschreven |
| `resources/views/partials/headers/default.antlers.html`, `navigation`, `mobileNavigation`, `footer` | volgens Figma |
| `resources/js/site.js` | slider-import, collapses-import weg |
| `package.json` | Swiper |

**Verwijderd**

`resources/views/partials/sections/{reviews,list,images,cases,callToAction,collapses}.antlers.html`, `partials/blockHeader.antlers.html`, `resources/fieldsets/collapses.yaml`, `resources/css/components/collapse.css`, `resources/js/components/collapses.js`.

---

## Fase 1 — Fundament

### Task 1: Design tokens, typografie en spacing

**Files:**
- Modify: `resources/css/site.css`
- Modify: `resources/css/base/typography.css`
- Modify: `resources/css/base/spacing.css`
- Modify: `resources/css/base/section.css`
- Modify: `resources/css/base/container.css`

**Interfaces:**
- Produces: fluid tokens `--text-base` t/m `--text-4xl`; kleur `--color-dark`; utilities `grid-gutter`, `card-padding`, `slider-bleed`; `.section` op 64px/112px; `.container` op 20px/40px. Alle latere taken gebruiken deze.

- [ ] **Step 1: Vervang de tekstschaal in `resources/css/site.css`**

In het `@theme`-blok staat de `--text-*` lijst nu twee keer (kopieerfout). Verwijder het dubbele blok en vervang de reeks `--text-base` t/m `--text-4xl` door de fluid varianten. Laat `--text-xs`, `--text-sm` en `--text-5xl` en hoger ongewijzigd.

```css
    /* Fluid: mobiel (640px) → desktop (1536px), waarden uit Figma */
    --text-base: clamp(1rem, 0.821rem + 0.446vw, 1.25rem);        /* P        16 → 20 */
    --text-lg: clamp(1.125rem, 0.813rem + 0.781vw, 1.5625rem);    /* P-lg     18 → 25 */
    --text-xl: clamp(1.25rem, 0.759rem + 1.228vw, 1.9375rem);     /* H3       20 → 31 */
    --text-2xl: clamp(1.5625rem, 0.938rem + 1.563vw, 2.4375rem);  /*          25 → 39 */
    --text-3xl: clamp(1.9375rem, 1.134rem + 2.009vw, 3.0625rem);  /* H2       31 → 49 */
    --text-4xl: clamp(2.4375rem, 1.455rem + 2.455vw, 3.8125rem);  /* H1       39 → 61 */
```

- [ ] **Step 2: Voeg de ontbrekende kleur en spacing-tokens toe aan `@theme`**

```css
    --color-dark: #292d2d;

    --spacing-gutter: 2rem;          /* 32 */
    --spacing-margin: 2.5rem;        /* 40 */
    --spacing-margin-mobile: 1.25rem;/* 20 */
    --spacing-section: 7rem;         /* 112 */
    --spacing-section-half: 4rem;    /* 64 */
```

- [ ] **Step 3: Schrijf `resources/css/base/typography.css`**

```css
h1,
h2,
h3,
h4 {
    @apply font-semibold text-balance;

    line-height: 1.1;
}

h1 {
    @apply text-4xl;
}

h2 {
    @apply text-3xl;
}

h3 {
    @apply text-xl;
}

h4 {
    @apply text-lg;
}

p {
    @apply text-base;

    line-height: 1.5;
}
```

- [ ] **Step 4: Voeg de nieuwe utilities toe aan `resources/css/base/spacing.css`**

Laat `section-y-gap`, `section-x-gap`, `section-col-wide`, `section-col-narrow` en `section-header-gap` staan; voeg toe:

```css
@utility grid-gutter {
    @apply gap-6 lg:gap-8;
}

@utility card-padding {
    @apply p-6 lg:p-8;
}

@utility slider-bleed {
    margin-inline: calc(var(--spacing-margin-mobile) * -1);
    padding-inline: var(--spacing-margin-mobile);

    @media (min-width: 1024px) {
        margin-inline: calc(var(--spacing-margin) * -1);
        padding-inline: var(--spacing-margin);
    }
}
```

- [ ] **Step 5: Lijn `section.css` en `container.css` uit op de Figma-tokens**

`resources/css/base/section.css`:

```css
.section {
    padding-block: var(--spacing-section-half);
}

@media (min-width: 1024px) {
    .section {
        padding-block: var(--spacing-section);
    }
}

.section--default + .section--default {
    padding-top: 0 !important;
}
```

In `resources/css/base/container.css` vervang je de eerste regel van `@utility container` door:

```css
    @apply px-5 lg:px-10;
```

- [ ] **Step 6: Bouw en controleer**

Run: `npm run build`
Expected: build slaagt zonder fouten. Open daarna een bestaande pagina en controleer dat koppen meeschalen bij het versmallen van het venster.

- [ ] **Step 7: Commit**

```bash
git add resources/css
git commit -m "feat(css): fluid type scale and Figma spacing tokens"
```

---

### Task 2: Testbasis voor partials

**Files:**
- Create: `tests/Feature/Sections/SectionTestCase.php`
- Create: `tests/Feature/Sections/RenderHarnessTest.php`

**Interfaces:**
- Produces: `Tests\Feature\Sections\SectionTestCase` met `protected function render(string $template, array $context = []): string`. Elke latere sectietest erft hiervan.

- [ ] **Step 1: Schrijf de falende test**

`tests/Feature/Sections/RenderHarnessTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class RenderHarnessTest extends SectionTestCase
{
    public function test_render_helper_parses_antlers_with_context(): void
    {
        $html = $this->render('<p>{{ title }}</p>', ['title' => 'Pergola SO!']);

        $this->assertSame('<p>Pergola SO!</p>', trim($html));
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter RenderHarnessTest`
Expected: FAIL — `Class "Tests\Feature\Sections\SectionTestCase" not found`.

- [ ] **Step 3: Schrijf `tests/Feature/Sections/SectionTestCase.php`**

```php
<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Antlers;
use Tests\TestCase;

abstract class SectionTestCase extends TestCase
{
    protected function render(string $template, array $context = []): string
    {
        return (string) Antlers::parse($template, $context);
    }
}
```

- [ ] **Step 4: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter RenderHarnessTest`
Expected: PASS (1 test, 1 assertion).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Sections
git commit -m "test: add Antlers render harness for section partials"
```

---

### Task 3: Overline-partial

**Files:**
- Create: `resources/views/partials/overline.antlers.html`
- Create: `resources/css/components/overline.css`
- Modify: `resources/css/site.css`
- Test: `tests/Feature/Sections/OverlineTest.php`

**Interfaces:**
- Produces: `{{ partial:overline label="..." is_inverse="true" }}` → `<p class="overline">` met daarin een `<span class="overline__rule">`. Rendert niets zonder `label`.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class OverlineTest extends SectionTestCase
{
    public function test_renders_label_with_rule(): void
    {
        $html = $this->render('{{ partial:overline label="In de kijker" }}');

        $this->assertStringContainsString('class="overline"', $html);
        $this->assertStringContainsString('In de kijker', $html);
        $this->assertStringContainsString('overline__rule', $html);
    }

    public function test_renders_nothing_without_label(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:overline }}')));
    }

    public function test_adds_inverse_modifier(): void
    {
        $html = $this->render('{{ partial:overline label="Aanbod" is_inverse="true" }}');

        $this->assertStringContainsString('overline--inverse', $html);
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter OverlineTest`
Expected: FAIL — partial bestaat niet.

- [ ] **Step 3: Schrijf de partial**

`resources/views/partials/overline.antlers.html`:

```antlers
{{ if label }}
    <p class="overline {{ is_inverse ? 'overline--inverse' : '' }}">
        {{ label }}
        <span class="overline__rule" aria-hidden="true"></span>
    </p>
{{ /if }}
```

- [ ] **Step 4: Schrijf `resources/css/components/overline.css`**

```css
.overline {
    @apply flex flex-col items-start gap-2 font-semibold uppercase text-black;

    font-size: clamp(0.75rem, 0.571rem + 0.446vw, 1rem); /* 12 → 16 */
    line-height: 1.1;
    letter-spacing: 0.125em; /* 2px op 16px */
}

.overline--inverse {
    @apply text-white;
}

.overline__rule {
    @apply block h-px w-6 bg-current;
}
```

Voeg `@import './components/overline.css';` toe onder de andere component-imports in `resources/css/site.css`.

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter OverlineTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/overline.antlers.html resources/css tests/Feature/Sections/OverlineTest.php
git commit -m "feat: add overline partial"
```

---

### Task 4: sectionHeader-partial

**Files:**
- Modify: `resources/views/partials/sectionHeader.antlers.html`
- Test: `tests/Feature/Sections/SectionHeaderTest.php`

**Interfaces:**
- Consumes: `{{ partial:overline }}` uit Task 3.
- Produces: `{{ partial:sectionHeader is_centered="true" is_inverse="true" tag="h3" btn_variant="btn btn--inverse" }}`. Leest `overline`, `title`, `text`, `link` uit de context. Wrapper heeft altijd `class="section-header"`, plus `section-header--centered` en/of `section-header--inverse`.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class SectionHeaderTest extends SectionTestCase
{
    private array $context = [
        'overline' => 'In de kijker',
        'title' => 'Pergola SO!',
        'text' => '<p>De pergola met draaibare lamellen.</p>',
    ];

    public function test_renders_overline_title_and_text(): void
    {
        $html = $this->render('{{ partial:sectionHeader }}', $this->context);

        $this->assertStringContainsString('class="overline"', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('draaibare lamellen', $html);
    }

    public function test_defaults_to_left_aligned(): void
    {
        $html = $this->render('{{ partial:sectionHeader }}', $this->context);

        $this->assertStringNotContainsString('section-header--centered', $html);
    }

    public function test_centered_variant(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_centered="true" }}', $this->context);

        $this->assertStringContainsString('section-header--centered', $html);
    }

    public function test_inverse_variant(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_inverse="true" }}', $this->context);

        $this->assertStringContainsString('section-header--inverse', $html);
        $this->assertStringContainsString('overline--inverse', $html);
    }

    public function test_heading_tag_is_configurable(): void
    {
        $html = $this->render('{{ partial:sectionHeader tag="h3" }}', $this->context);

        $this->assertStringContainsString('<h3', $html);
        $this->assertStringNotContainsString('<h2', $html);
    }

    public function test_renders_nothing_without_title_or_text(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:sectionHeader }}')));
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter SectionHeaderTest`
Expected: FAIL — de huidige partial rendert geen `section-header` class en negeert de argumenten.

- [ ] **Step 3: Herschrijf de partial**

`resources/views/partials/sectionHeader.antlers.html`:

```antlers
{{ tag = tag ?? "h2" }}
{{ if title || text || overline || link }}
    <div
        class="section-header section-header-gap {{ is_centered ? 'section-header--centered items-center text-center' : '' }} {{ is_inverse ? 'section-header--inverse text-white' : '' }}">
        {{ partial:overline :label="overline" :is_inverse="is_inverse" }}

        {{ if title }}
            <{{ tag }} class="max-w-3xl">{{ title }}</{{ tag }}>
        {{ /if }}

        {{ if text }}
            <div class="rich-text max-w-2xl">{{ text }}</div>
        {{ /if }}

        {{ if link }}
            {{ partial:link :style="btn_variant" :inverse="is_inverse" }}
        {{ /if }}
    </div>
{{ /if }}
```

- [ ] **Step 4: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter SectionHeaderTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/sectionHeader.antlers.html tests/Feature/Sections/SectionHeaderTest.php
git commit -m "feat: rewrite sectionHeader partial with alignment variants"
```

---

### Task 5: featureList-partial

**Files:**
- Create: `resources/views/partials/featureList.antlers.html`
- Test: `tests/Feature/Sections/FeatureListTest.php`

**Interfaces:**
- Produces: `{{ partial:featureList :items="features" }}` waarbij `items` een lijst van arrays met sleutel `label` is. Rendert `<ul class="feature-list">` met per item een vinkje-icoon en het label.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class FeatureListTest extends SectionTestCase
{
    public function test_renders_one_item_per_feature(): void
    {
        $html = $this->render('{{ partial:featureList :items="features" }}', [
            'features' => [
                ['label' => 'Automatische lamellen'],
                ['label' => 'Bediening via app'],
                ['label' => 'Belgisch maatwerk'],
            ],
        ]);

        $this->assertStringContainsString('feature-list', $html);
        $this->assertSame(3, substr_count($html, '<li'));
        $this->assertStringContainsString('Belgisch maatwerk', $html);
    }

    public function test_renders_nothing_without_items(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:featureList }}')));
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter FeatureListTest`
Expected: FAIL — partial bestaat niet.

- [ ] **Step 3: Schrijf de partial**

`resources/views/partials/featureList.antlers.html`:

```antlers
{{ if items }}
    <ul class="feature-list flex flex-col gap-2">
        {{ items }}
            <li class="flex items-center gap-3 text-base">
                {{ svg src="icons/regular/check" class="size-5 shrink-0 text-accent" }}
                <span>{{ label }}</span>
            </li>
        {{ /items }}
    </ul>
{{ /if }}
```

- [ ] **Step 4: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter FeatureListTest`
Expected: PASS (2 tests). `resources/svg/icons/regular/check.svg` bestaat; de repo sluit statische iconen altijd zo in (zie `partials/cookieConsent.antlers.html`).

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/featureList.antlers.html tests/Feature/Sections/FeatureListTest.php
git commit -m "feat: add featureList partial"
```

---

### Task 6: Swiper en de generieke slider

**Files:**
- Modify: `package.json`
- Create: `resources/js/components/sliders.js`
- Create: `resources/views/partials/slider.antlers.html`
- Create: `resources/css/components/slider.css`
- Modify: `resources/css/site.css`
- Modify: `resources/js/site.js`
- Create: `lang/nl/site.php`
- Test: `tests/Feature/Sections/SliderTest.php`

**Interfaces:**
- Produces: `{{ partial:slider per_view="1.15,md:2,xl:3" from="md" pagination="true" navigation="true" bleed="true" }}{{ slot }}{{ /partial:slider }}`. Wrapper krijgt `data-slider` plus `data-slider-per-view`, `data-slider-from`, `data-slider-pagination`, `data-slider-navigation`. Slides moeten door de aanroeper als `<div class="swiper-slide">` worden aangeleverd.
- Produces: `lang/nl/site.php` met de sleutels `slider_previous`, `slider_next`.

- [ ] **Step 1: Installeer Swiper**

Run: `npm install swiper`
Expected: `swiper` staat onder `dependencies` in `package.json`.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class SliderTest extends SectionTestCase
{
    public function test_renders_swiper_scaffolding_with_options(): void
    {
        $html = $this->render(
            '{{ partial:slider per_view="1.15,md:2,xl:3" from="md" pagination="true" navigation="true" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}'
        );

        $this->assertStringContainsString('data-slider', $html);
        $this->assertStringContainsString('data-slider-per-view="1.15,md:2,xl:3"', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertStringContainsString('class="swiper-wrapper"', $html);
        $this->assertStringContainsString('swiper-slide', $html);
        $this->assertStringContainsString('swiper-pagination', $html);
    }

    public function test_omits_navigation_when_not_requested(): void
    {
        $html = $this->render('{{ partial:slider per_view="1" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}');

        $this->assertStringNotContainsString('swiper-button-next', $html);
        $this->assertStringNotContainsString('swiper-pagination', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter SliderTest`
Expected: FAIL — partial bestaat niet.

- [ ] **Step 4: Schrijf `lang/nl/site.php`**

```php
<?php

return [
    'slider_previous' => 'Vorige',
    'slider_next' => 'Volgende',
];
```

- [ ] **Step 5: Schrijf `resources/views/partials/slider.antlers.html`**

```antlers
<div class="slider {{ bleed ? 'slider-bleed' : '' }}" data-slider data-slider-per-view="{{ per_view }}"
    {{ if from }}data-slider-from="{{ from }}"{{ /if }}
    {{ if pagination }}data-slider-pagination="true"{{ /if }}
    {{ if navigation }}data-slider-navigation="true"{{ /if }}>
    <div class="swiper-wrapper">
        {{ slot }}
    </div>

    {{ if pagination }}
        <div class="swiper-pagination"></div>
    {{ /if }}

    {{ if navigation }}
        <div class="slider__nav">
            <button type="button" class="swiper-button-prev" aria-label="{{ trans:site.slider_previous }}"></button>
            <button type="button" class="swiper-button-next" aria-label="{{ trans:site.slider_next }}"></button>
        </div>
    {{ /if }}
</div>
```

- [ ] **Step 6: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter SliderTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Schrijf `resources/js/components/sliders.js`**

```js
const BREAKPOINTS = {
    xs: 448,
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    '2xl': 1536,
}

/**
 * "1.15,md:2,xl:3" -> { slidesPerView: 1.15, breakpoints: { 768: {...}, 1280: {...} } }
 */
function parsePerView(value) {
    const config = { slidesPerView: 1, breakpoints: {} }

    for (const part of (value ?? '1').split(',')) {
        const [key, raw] = part.includes(':') ? part.split(':') : [null, part]
        const slidesPerView = parseFloat(raw)

        if (Number.isNaN(slidesPerView)) continue

        if (key === null) {
            config.slidesPerView = slidesPerView
        } else if (BREAKPOINTS[key]) {
            config.breakpoints[BREAKPOINTS[key]] = { slidesPerView }
        }
    }

    return config
}

async function createSwiper(element) {
    const { default: Swiper } = await import('swiper')
    const { Navigation, Pagination } = await import('swiper/modules')

    const { slidesPerView, breakpoints } = parsePerView(element.dataset.sliderPerView)

    return new Swiper(element, {
        modules: [Navigation, Pagination],
        slidesPerView,
        breakpoints,
        spaceBetween: 32,
        watchOverflow: true,
        a11y: { enabled: true },
        pagination: element.dataset.sliderPagination
            ? { el: element.querySelector('.swiper-pagination'), clickable: true }
            : false,
        navigation: element.dataset.sliderNavigation
            ? {
                  nextEl: element.querySelector('.swiper-button-next'),
                  prevEl: element.querySelector('.swiper-button-prev'),
              }
            : false,
    })
}

/**
 * Zonder data-slider-from draait de slider altijd. Met data-slider-from="md"
 * draait hij alleen ONDER die breakpoint; erboven wordt hij vernietigd zodat
 * de CSS-grid het overneemt.
 */
function register(element) {
    let instance = null
    const from = element.dataset.sliderFrom

    const enable = async () => {
        if (!instance) instance = await createSwiper(element)
    }

    const disable = () => {
        if (instance) {
            instance.destroy(true, true)
            instance = null
        }
    }

    if (!from) {
        enable()
        return
    }

    const query = window.matchMedia(`(min-width: ${BREAKPOINTS[from]}px)`)
    const sync = () => (query.matches ? disable() : enable())

    query.addEventListener('change', sync)
    sync()
}

document.querySelectorAll('[data-slider]').forEach(register)
```

- [ ] **Step 8: Schrijf `resources/css/components/slider.css`**

```css
.slider {
    @apply relative overflow-hidden;
}

.slider .swiper-wrapper {
    @apply flex;
}

/* Boven data-slider-from valt Swiper weg; dan is het een gewoon grid. */
.slider:not(.swiper-initialized) .swiper-wrapper {
    @apply grid grid-cols-1 grid-gutter md:grid-cols-2 xl:grid-cols-3;
}

.slider__nav {
    @apply mt-8 flex gap-3;
}

.slider__nav button {
    @apply flex size-11 items-center justify-center rounded-full bg-black text-white transition-opacity;
}

.slider__nav button:disabled {
    @apply opacity-40;
}

.swiper-pagination {
    @apply mt-6 flex justify-center gap-2;
}

.swiper-pagination-bullet {
    @apply size-2 rounded-full bg-black/25;
}

.swiper-pagination-bullet-active {
    @apply bg-black;
}
```

Voeg `@import './components/slider.css';` toe in `resources/css/site.css`.

- [ ] **Step 9: Registreer de slider in `resources/js/site.js`**

Vervang de regel `import "./components/collapses";` door:

```js
import "./components/sliders";
```

- [ ] **Step 10: Bouw**

Run: `npm run build`
Expected: build slaagt; Swiper zit in een aparte chunk omdat hij dynamisch geïmporteerd wordt.

- [ ] **Step 11: Commit**

```bash
git add package.json package-lock.json resources/js resources/css resources/views/partials/slider.antlers.html lang tests/Feature/Sections/SliderTest.php
git commit -m "feat: add generic Swiper slider partial and initializer"
```

---

### Task 7: Card-partial

**Files:**
- Modify: `resources/views/partials/card.antlers.html`
- Create: `resources/css/components/card.css`
- Modify: `resources/css/site.css`
- Test: `tests/Feature/Sections/CardTest.php`

**Interfaces:**
- Consumes: `{{ partial:featureList }}` uit Task 5.
- Produces: `{{ partial:card layout="horizontal" }}`. Leest `image`, `title`, `text`, `features`, `link`, `overline` uit de context. Wrapper: `class="card"` plus `card--horizontal` of `card--vertical` (default vertical).

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class CardTest extends SectionTestCase
{
    private array $context = [
        'title' => 'Sfeervolle ledverlichting',
        'text' => '<p>Dimbare spots in de lamellen.</p>',
        'features' => [['label' => 'Dimbaar via app']],
    ];

    public function test_renders_vertical_card_by_default(): void
    {
        $html = $this->render('{{ partial:card }}', $this->context);

        $this->assertStringContainsString('card--vertical', $html);
        $this->assertStringContainsString('Sfeervolle ledverlichting', $html);
        $this->assertStringContainsString('feature-list', $html);
    }

    public function test_renders_horizontal_card(): void
    {
        $html = $this->render('{{ partial:card layout="horizontal" }}', $this->context);

        $this->assertStringContainsString('card--horizontal', $html);
        $this->assertStringNotContainsString('card--vertical', $html);
    }

    public function test_omits_feature_list_when_absent(): void
    {
        $html = $this->render('{{ partial:card }}', ['title' => 'Alleen een titel']);

        $this->assertStringNotContainsString('feature-list', $html);
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter CardTest`
Expected: FAIL — de huidige partial rendert `bg-gray-200` en kent geen layout-varianten.

- [ ] **Step 3: Haal het kaartontwerp op uit Figma**

Roep `mcp__figma__get_design_context` aan met `fileKey: dgMxUtoYzYrR5FRuwPzQBn` en `nodeId: 451:2739` (desktop horizontale kaart) en met `nodeId: 451:3090` (mobiele verticale kaart). Neem daaruit de achtergrondkleur, radius en beeldverhouding over.

- [ ] **Step 4: Herschrijf `resources/views/partials/card.antlers.html`**

```antlers
{{ layout = layout ?? "vertical" }}
<article class="card card--{{ layout }} @container">
    {{ if image }}
        <div class="card__media">
            {{ img :src="image" ratio="4/3" max_width="960" sizes="(min-width: 1024px) 33vw, 90vw" }}
        </div>
    {{ /if }}

    <div class="card__body card-padding">
        {{ partial:overline :label="overline" }}

        {{ if title }}
            <h3>{{ title }}</h3>
        {{ /if }}

        {{ if text }}
            <div class="rich-text">{{ text }}</div>
        {{ /if }}

        {{ partial:featureList :items="features" }}

        {{ if link }}
            {{ partial:link }}
        {{ /if }}
    </div>
</article>
```

- [ ] **Step 5: Schrijf `resources/css/components/card.css`**

Vul kleur, radius en verhoudingen in met de waarden uit Step 3.

```css
.card {
    @apply flex overflow-hidden bg-light;
}

.card--vertical {
    @apply flex-col;
}

.card--horizontal {
    @apply flex-col sm:flex-row;
}

.card--horizontal .card__media {
    @apply sm:w-1/3 sm:shrink-0;
}

.card__media img {
    @apply size-full object-cover;
}

.card__body {
    @apply flex flex-col gap-4;
}
```

Voeg `@import './components/card.css';` toe in `resources/css/site.css`.

- [ ] **Step 6: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter CardTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/card.antlers.html resources/css tests/Feature/Sections/CardTest.php
git commit -m "feat: rewrite card partial with layout variants"
```

---

### Task 8: Page builder dispatcher

**Files:**
- Modify: `resources/views/partials/pageBuilder.antlers.html`
- Create: `resources/views/partials/sections/{cta,cards,imageGallery,technicalDetails,ranges,text,textImage,products,projects,features,gridCta}.antlers.html` (voorlopig leeg met alleen een marker)
- Test: `tests/Feature/Sections/PageBuilderTest.php`

**Interfaces:**
- Produces: `{{ partial:pageBuilder }}` mapt elk `type` uit de `page_builder`-replicator op de bijbehorende partial. De sets `cta`, `cards`, `image_gallery`, `technical_details`, `ranges`, `text`, `text_image`, `products`, `projects`, `features`, `grid_cta` zijn allemaal bereikbaar.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class PageBuilderTest extends SectionTestCase
{
    public function test_dispatches_every_set_to_its_partial(): void
    {
        $types = [
            'cta', 'cards', 'image_gallery', 'technical_details', 'ranges',
            'text', 'text_image', 'products', 'projects', 'features', 'grid_cta',
        ];

        $html = $this->render('{{ partial:pageBuilder }}', [
            'page_builder' => array_map(fn (string $type) => ['type' => $type], $types),
        ]);

        foreach ($types as $type) {
            $this->assertStringContainsString('data-section="'.$type.'"', $html, "Set {$type} is niet gerenderd");
        }
    }

    public function test_ignores_unknown_types(): void
    {
        $html = $this->render('{{ partial:pageBuilder }}', [
            'page_builder' => [['type' => 'does_not_exist']],
        ]);

        $this->assertStringNotContainsString('data-section', $html);
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter PageBuilderTest`
Expected: FAIL — alleen vier sets zijn gemapt.

- [ ] **Step 3: Maak de elf sectie-partials aan met een marker**

Elk bestand krijgt voorlopig alleen zijn wrapper, zodat de dispatcher testbaar is. Voor `resources/views/partials/sections/text.antlers.html`:

```antlers
<section class="section section--default" data-section="text"></section>
```

Doe hetzelfde voor de overige tien, met steeds de juiste `data-section`-waarde: `cta`, `cards`, `image_gallery`, `technical_details`, `ranges`, `text_image`, `products`, `projects`, `features`, `grid_cta`. Bestandsnamen zijn camelCase (`imageGallery`, `technicalDetails`, `textImage`, `gridCta`), de `data-section`-waarde is de snake_case set-handle.

- [ ] **Step 4: Herschrijf `resources/views/partials/pageBuilder.antlers.html`**

```antlers
{{ page_builder }}
    {{ if type == "text" }}
        {{ partial src="sections/text" }}
    {{ elseif type == "text_image" }}
        {{ partial src="sections/textImage" }}
    {{ elseif type == "ranges" }}
        {{ partial src="sections/ranges" }}
    {{ elseif type == "cards" }}
        {{ partial src="sections/cards" }}
    {{ elseif type == "projects" }}
        {{ partial src="sections/projects" }}
    {{ elseif type == "technical_details" }}
        {{ partial src="sections/technicalDetails" }}
    {{ elseif type == "features" }}
        {{ partial src="sections/features" }}
    {{ elseif type == "grid_cta" }}
        {{ partial src="sections/gridCta" }}
    {{ elseif type == "image_gallery" }}
        {{ partial src="sections/imageGallery" }}
    {{ elseif type == "cta" }}
        {{ partial src="sections/cta" }}
    {{ elseif type == "products" }}
        {{ partial src="sections/products" }}
    {{ /if }}
{{ /page_builder }}
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter PageBuilderTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials tests/Feature/Sections/PageBuilderTest.php
git commit -m "feat: dispatch all eleven page builder sets"
```

---

## Fase 2 — De secties

Elke taak in deze fase volgt hetzelfde ritme. **Stap 1 is altijd: haal het ontwerp op uit Figma** met `mcp__figma__get_design_context` (fileKey `dgMxUtoYzYrR5FRuwPzQBn`) voor zowel de desktop- als de mobiele node. Neem daaruit kleuren, afstanden, radii en beeldverhoudingen over; de markup-structuur staat in het plan.

### Task 9: Sectie `text`

**Files:**
- Modify: `resources/views/partials/sections/text.antlers.html`
- Test: `tests/Feature/Sections/TextSectionTest.php`

**Interfaces:**
- Consumes: `sectionHeader` (Task 4).
- Velden uit de set: `overline`, `title`, `text` (bard), `link`.

- [ ] **Step 1: Haal het ontwerp op**

`mcp__figma__get_design_context` met `nodeId: 451:2816` (desktop) en `nodeId: 451:3158` (mobile).

Desktop: twee kolommen van 752px met 176px tussenruimte. Links de grote intro-tekst (P-lg, 25px), rechts de bodytekst (P, 20px). Mobile: gestapeld.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class TextSectionTest extends SectionTestCase
{
    public function test_renders_title_and_body_in_two_columns(): void
    {
        $html = $this->render('{{ partial src="sections/text" }}', [
            'title' => 'Van de draaibare lamellen tot de avondsfeer',
            'text' => '<p>De oplossing werd een aangebouwde Pergola SO!</p>',
        ]);

        $this->assertStringContainsString('data-section="text"', $html);
        $this->assertStringContainsString('Van de draaibare lamellen', $html);
        $this->assertStringContainsString('aangebouwde Pergola SO!', $html);
        $this->assertStringContainsString('section-x-gap', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter TextSectionTest`
Expected: FAIL — de partial bevat alleen de marker.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="text">
    <div class="container">
        <div class="section-x-gap">
            <div class="section-col-wide">
                {{ if title }}
                    <h2 class="text-lg">{{ title }}</h2>
                {{ /if }}
            </div>

            <div class="section-col-narrow">
                {{ if text }}
                    <div class="rich-text">{{ text }}</div>
                {{ /if }}

                {{ if link }}
                    {{ partial:link }}
                {{ /if }}
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter TextSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/text.antlers.html tests/Feature/Sections/TextSectionTest.php
git commit -m "feat(sections): build text section"
```

---

### Task 10: Sectie `text_image`

**Files:**
- Modify: `resources/views/partials/sections/textImage.antlers.html`
- Test: `tests/Feature/Sections/TextImageSectionTest.php`

**Interfaces:**
- Consumes: `sectionHeader` (Task 4), `featureList` (Task 5).
- Velden: `overline`, `title`, `text` (bard), `link`, `image`, `background` (toggle).

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2679` (desktop, zonder achtergrond), `451:2944` (desktop, mét achtergrond), `451:3026` en `451:3302` (mobiel).

Zonder achtergrond: beeld links 776px, tekst rechts, verticaal gecentreerd. Met achtergrond: tekstpaneel links met een gevuld vlak (`--color-light`) van 812px, beeld rechts van 812px.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class TextImageSectionTest extends SectionTestCase
{
    public function test_renders_header_and_features(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Pergola SO!',
            'text' => '<p>Met draaibare lamellen.</p>',
            'features' => [['label' => 'Bediening via app']],
        ]);

        $this->assertStringContainsString('data-section="text_image"', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('Bediening via app', $html);
    }

    public function test_adds_background_modifier_when_toggled(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('text-image--background', $html);
    }

    public function test_omits_background_modifier_by_default(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Pergola SO!']);

        $this->assertStringNotContainsString('text-image--background', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter TextImageSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default {{ background ? 'text-image--background' : '' }}" data-section="text_image">
    <div class="container">
        <div class="section-x-gap items-center">
            <div class="section-col-wide {{ background ? 'order-last bg-light card-padding' : '' }}">
                {{ partial:sectionHeader }}
                {{ partial:featureList :items="features" }}
            </div>

            {{ if image }}
                <div class="section-col-narrow">
                    {{ img :src="image" ratio="16/9" max_width="1600" sizes="(min-width: 640px) 50vw, 100vw" }}
                </div>
            {{ /if }}
        </div>
    </div>
</section>
```

Zet `order-last` alleen als dat overeenkomt met wat Step 1 laat zien; in Figma staat bij de achtergrondvariant de tekst links en het beeld rechts, dus mogelijk is `order-last` niet nodig. Corrigeer op basis van de opgehaalde context.

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter TextImageSectionTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/textImage.antlers.html tests/Feature/Sections/TextImageSectionTest.php
git commit -m "feat(sections): build text_image section"
```

---

### Task 11: Sectie `technical_details`

**Files:**
- Modify: `resources/views/partials/sections/technicalDetails.antlers.html`
- Test: `tests/Feature/Sections/TechnicalDetailsSectionTest.php`

**Interfaces:**
- Velden: `overline`, `title`, `text` (textarea), `link`, `technical_details` (grid met `key` en `value`).

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2821` (desktop) en `451:3195` (mobiel).

Desktop: spectabel links (612px breed, per rij `key` links en `value` rechts, met een scheidingslijn eronder), kop met tekst en knop rechts (908px). Mobiel: kop boven, tabel eronder, `key` en `value` onder elkaar.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class TechnicalDetailsSectionTest extends SectionTestCase
{
    public function test_renders_one_row_per_specification(): void
    {
        $html = $this->render('{{ partial src="sections/technicalDetails" }}', [
            'title' => 'Technische specificaties',
            'technical_details' => [
                ['key' => 'Max. breedte per module', 'value' => 'tot 6,0 m'],
                ['key' => 'Lamellen openingsgraad', 'value' => '0 tot 145 graden'],
            ],
        ]);

        $this->assertStringContainsString('data-section="technical_details"', $html);
        $this->assertSame(2, substr_count($html, 'specs__row'));
        $this->assertStringContainsString('Max. breedte per module', $html);
        $this->assertStringContainsString('0 tot 145 graden', $html);
    }

    public function test_renders_header_without_rows(): void
    {
        $html = $this->render('{{ partial src="sections/technicalDetails" }}', [
            'title' => 'Technische specificaties',
        ]);

        $this->assertStringContainsString('Technische specificaties', $html);
        $this->assertStringNotContainsString('specs__row', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter TechnicalDetailsSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="technical_details">
    <div class="container">
        <div class="section-x-gap">
            {{ if technical_details }}
                <dl class="section-col-wide order-last lg:order-first">
                    {{ technical_details }}
                        <div class="specs__row flex flex-col justify-between gap-1 border-b border-black/10 py-4 sm:flex-row sm:gap-8">
                            <dt class="text-base opacity-70">{{ key }}</dt>
                            <dd class="text-base sm:w-1/2">{{ value }}</dd>
                        </div>
                    {{ /technical_details }}
                </dl>
            {{ /if }}

            <div class="section-col-narrow">
                {{ partial:sectionHeader }}
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter TechnicalDetailsSectionTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/technicalDetails.antlers.html tests/Feature/Sections/TechnicalDetailsSectionTest.php
git commit -m "feat(sections): build technical_details section"
```

---

### Task 12: Sectie `features`

**Files:**
- Modify: `resources/views/partials/sections/features.antlers.html`
- Test: `tests/Feature/Sections/FeaturesSectionTest.php`

**Interfaces:**
- Velden: `overline`, `title`, `features` (replicator met `icon`, `title`, `text`).
- `sectionHeader` staat hier **gecentreerd**.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2851` (desktop) en `451:3222` (mobiel).

Desktop: gecentreerde kop, daaronder vier kolommen van 376px met het icoon (48px) boven de tekst. Mobiel: één kolom, icoon links naast de tekst.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class FeaturesSectionTest extends SectionTestCase
{
    public function test_renders_centered_header_and_one_item_per_feature(): void
    {
        $html = $this->render('{{ partial src="sections/features" }}', [
            'title' => 'Waar we voor staan',
            'overline' => 'Onze aanpak',
            'features' => [
                ['title' => 'Lokaal verankerd', 'text' => 'Drie verkooppunten uit de buurt.'],
                ['title' => 'Eigen plaatsers', 'text' => 'Geen onderaannemers.'],
            ],
        ]);

        $this->assertStringContainsString('data-section="features"', $html);
        $this->assertStringContainsString('section-header--centered', $html);
        $this->assertSame(2, substr_count($html, 'feature-item'));
        $this->assertStringContainsString('Lokaal verankerd', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter FeaturesSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="features">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader is_centered="true" }}

            {{ if features }}
                <div class="grid grid-cols-1 grid-gutter sm:grid-cols-2 xl:grid-cols-4">
                    {{ features }}
                        <div class="feature-item flex gap-5 sm:flex-col">
                            {{ if icon }}
                                {{ icon:index src="{{ icon | raw }}" class="size-12 shrink-0" }}
                            {{ /if }}

                            <div class="flex flex-col gap-2">
                                {{ if title }}
                                    <h3 class="text-lg">{{ title }}</h3>
                                {{ /if }}

                                {{ if text }}
                                    <p>{{ text }}</p>
                                {{ /if }}
                            </div>
                        </div>
                    {{ /features }}
                </div>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter FeaturesSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/features.antlers.html tests/Feature/Sections/FeaturesSectionTest.php
git commit -m "feat(sections): build features section"
```

---

### Task 13: Sectie `cards`

**Files:**
- Modify: `resources/views/partials/sections/cards.antlers.html`
- Test: `tests/Feature/Sections/CardsSectionTest.php`

**Interfaces:**
- Consumes: `card` (Task 7, `layout="horizontal"`), `slider` (Task 6, `from="md"`).
- Velden: `overline`, `title`, `text`, `cards` (replicator met `image`, `title`, `text`).
- `sectionHeader` staat **gecentreerd**.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2738` (desktop) en `451:3085` (mobiel).

Desktop: gecentreerde kop, daaronder een 2×2 grid van horizontale kaarten (788px breed: beeld 261px links, tekstblok 527px rechts). Mobiel: slider met verticale kaarten van 326px.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class CardsSectionTest extends SectionTestCase
{
    public function test_renders_a_card_per_item_inside_a_breakpoint_slider(): void
    {
        $html = $this->render('{{ partial src="sections/cards" }}', [
            'title' => 'Alle mogelijkheden op een rij',
            'cards' => [
                ['title' => 'Sfeervolle ledverlichting', 'text' => '<p>Dimbare spots.</p>'],
                ['title' => 'Glazen schuifwanden', 'text' => '<p>Volledig op maat.</p>'],
            ],
        ]);

        $this->assertStringContainsString('data-section="cards"', $html);
        $this->assertStringContainsString('section-header--centered', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
        $this->assertSame(2, substr_count($html, 'card--horizontal'));
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter CardsSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="cards">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader is_centered="true" }}

            {{ if cards }}
                {{ partial:slider per_view="1.15,sm:2" from="md" }}
                    {{ cards }}
                        <div class="swiper-slide">
                            {{ partial:card layout="horizontal" }}
                        </div>
                    {{ /cards }}
                {{ /partial:slider }}
            {{ /if }}
        </div>
    </div>
</section>
```

Let op: boven `md` vernietigt `sliders.js` de Swiper-instance en valt `.swiper-wrapper` terug op het grid uit `slider.css`. Dat grid staat standaard op 3 kolommen; overschrijf het hier naar 2 door in `resources/css/components/card.css` toe te voegen:

```css
[data-section='cards'] .slider:not(.swiper-initialized) .swiper-wrapper {
    @apply md:grid-cols-2 xl:grid-cols-2;
}
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter CardsSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/cards.antlers.html resources/css/components/card.css tests/Feature/Sections/CardsSectionTest.php
git commit -m "feat(sections): build cards section"
```

---

### Task 14: Sectie `projects`

**Files:**
- Modify: `resources/views/partials/sections/projects.antlers.html`
- Test: `tests/Feature/Sections/ProjectsSectionTest.php`

**Interfaces:**
- Consumes: `slider` (Task 6, `from="md"`).
- Velden: `overline`, `title`, `link`, `projects` (entries → `projects`). Elke entry heeft `title`, `text`, `image`, `url` en optioneel `product`.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2820` (desktop) en `451:3162` (mobiel).

Kaart: beeld boven, daaronder een categorielabel, de titel en rechts een pijl; onderaan een scheidingslijn. Desktop drie naast elkaar, mobiel een slider.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class ProjectsSectionTest extends SectionTestCase
{
    public function test_renders_a_linked_card_per_project(): void
    {
        $html = $this->render('{{ partial src="sections/projects" }}', [
            'title' => 'Recent gerealiseerd',
            'overline' => 'realisaties',
            'projects' => [
                ['title' => 'Pergola SO! met glazen schuifwanden', 'url' => '/realisaties/pergola-so'],
                ['title' => 'Zip-screens op nieuwbouwwoning', 'url' => '/realisaties/zip-screens'],
            ],
        ]);

        $this->assertStringContainsString('data-section="projects"', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(2, substr_count($html, 'project-card'));
        $this->assertStringContainsString('href="/realisaties/pergola-so"', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter ProjectsSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="projects">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader }}

            {{ if projects }}
                {{ partial:slider per_view="1.1,sm:2,lg:3" from="md" }}
                    {{ projects }}
                        <div class="swiper-slide">
                            <a class="project-card group flex flex-col gap-6" href="{{ url }}">
                                {{ if image }}
                                    {{ img :src="image" ratio="4/3" max_width="960" sizes="(min-width: 1024px) 33vw, 90vw" }}
                                {{ /if }}

                                <div class="flex flex-col gap-2 border-b border-black/10 pb-6">
                                    {{ if product }}
                                        <span class="text-sm uppercase opacity-70">{{ product:title }}</span>
                                    {{ /if }}

                                    <div class="flex items-end justify-between gap-4">
                                        <h3 class="text-lg">{{ title }}</h3>
                                        {{ svg src="icons/regular/arrow-right" class="size-5 shrink-0 transition-transform group-hover:translate-x-1" }}
                                    </div>
                                </div>
                            </a>
                        </div>
                    {{ /projects }}
                {{ /partial:slider }}
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter ProjectsSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/projects.antlers.html tests/Feature/Sections/ProjectsSectionTest.php
git commit -m "feat(sections): build projects section"
```

---

### Task 15: Sectie `products`

**Files:**
- Modify: `resources/views/partials/sections/products.antlers.html`
- Test: `tests/Feature/Sections/ProductsSectionTest.php`

**Interfaces:**
- Consumes: `card` (Task 7, verticaal), `slider` (Task 6, `from="md"`).
- Velden: `overline`, `title`, `products` (entries → `products`, elk met `title`, `text`, `image`, `url`).
- `sectionHeader` staat **gecentreerd**.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2949` (desktop) en `451:3307` (mobiel).

Desktop: gecentreerde kop, daaronder twee rijen productkaarten binnen 1664px. Mobiel: slider.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class ProductsSectionTest extends SectionTestCase
{
    public function test_renders_a_card_per_product(): void
    {
        $html = $this->render('{{ partial src="sections/products" }}', [
            'title' => 'Zes soorten terrasoverkapping',
            'overline' => 'producten',
            'products' => [
                ['title' => 'Pergola SO!', 'text' => '<p>Draaibare lamellen.</p>'],
                ['title' => 'Pergola CO!', 'text' => '<p>Vast dak.</p>'],
                ['title' => 'Veranda', 'text' => '<p>Volledig gesloten.</p>'],
            ],
        ]);

        $this->assertStringContainsString('data-section="products"', $html);
        $this->assertStringContainsString('section-header--centered', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(3, substr_count($html, 'card--vertical'));
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter ProductsSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="products">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader is_centered="true" }}

            {{ if products }}
                {{ partial:slider per_view="1.15,sm:2,lg:3" from="md" }}
                    {{ products }}
                        <div class="swiper-slide">
                            {{ partial:card }}
                        </div>
                    {{ /products }}
                {{ /partial:slider }}
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter ProductsSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/products.antlers.html tests/Feature/Sections/ProductsSectionTest.php
git commit -m "feat(sections): build products section"
```

---

### Task 16: Sectie `cta`

**Files:**
- Modify: `resources/views/partials/sections/cta.antlers.html`
- Test: `tests/Feature/Sections/CtaSectionTest.php`

**Interfaces:**
- Velden: `overline`, `title`, `text` (textarea), `link`, `image`.
- De sectie is full-bleed: geen `container`, geen `section`-padding.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2932` (desktop) en `451:3293` (mobiel). Neem de exacte overlay-vulling over — dat is de enige kleur in het ontwerp die nog geen token heeft. Voeg hem toe als `--color-overlay` in het `@theme`-blok van `resources/css/site.css` als hij afwijkt van `--color-dark`.

Layout: foto over de volle breedte, met onderin een donker paneel dat overline, titel en knop bevat.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class CtaSectionTest extends SectionTestCase
{
    public function test_renders_full_bleed_panel_with_inverse_header(): void
    {
        $html = $this->render('{{ partial src="sections/cta" }}', [
            'overline' => 'Over ons',
            'title' => 'Lokale verkooppunten, eigen vakmensen',
        ]);

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('section-header--inverse', $html);
        $this->assertStringContainsString('overline--inverse', $html);
        $this->assertStringNotContainsString('class="container"', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter CtaSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="cta relative isolate overflow-hidden" data-section="cta">
    {{ if image }}
        <div class="absolute inset-0 -z-10">
            {{ img :src="image" fill="true" max_width="2560" sizes="100vw" class="size-full object-cover" }}
        </div>
    {{ /if }}

    <div class="flex min-h-[70vh] flex-col justify-end lg:min-h-[45rem]">
        <div class="bg-dark/90 card-padding lg:px-10 lg:py-12">
            <div class="container">
                {{ partial:sectionHeader is_inverse="true" btn_variant="btn btn--accent" }}
            </div>
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter CtaSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Voeg de accent-knop toe aan `resources/css/components/button.css`**

```css
.btn--accent {
    @apply rounded-full bg-accent px-8 py-4 font-semibold text-black;
}

.btn--inverse {
    @apply bg-white text-black;
}
```

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/sections/cta.antlers.html resources/css tests/Feature/Sections/CtaSectionTest.php
git commit -m "feat(sections): build cta section"
```

---

### Task 17: Sectie `grid_cta`

**Files:**
- Modify: `resources/views/partials/sections/gridCta.antlers.html`
- Test: `tests/Feature/Sections/GridCtaSectionTest.php`

**Interfaces:**
- Velden: `image`, `grid` (replicator met `title`, `text`, `link`).
- Gebruikt `resources/svg/shape.svg` als achtergrondvorm, ingesloten via `{{ svg src="shape" }}`.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2887` (desktop) en `451:3257` (mobiel).

Desktop: beeld links dat over de sectierand heen valt, rechts twee gestapelde panelen van 812px met titel, tekst en knop. De vorm (`shape.svg`) loopt links buiten het canvas (x = -681, 1518px breed).

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class GridCtaSectionTest extends SectionTestCase
{
    public function test_renders_a_panel_per_grid_item(): void
    {
        $html = $this->render('{{ partial src="sections/gridCta" }}', [
            'grid' => [
                ['title' => 'Wij werken met Winsol', 'text' => 'Belgisch merk met 145 jaar vakmanschap.'],
                ['title' => 'Kom ons team versterken', 'text' => 'Stuur ons gerust je cv.'],
            ],
        ]);

        $this->assertStringContainsString('data-section="grid_cta"', $html);
        $this->assertSame(2, substr_count($html, 'grid-cta__panel'));
        $this->assertStringContainsString('Kom ons team versterken', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter GridCtaSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default relative isolate overflow-hidden" data-section="grid_cta">
    <div class="pointer-events-none absolute -left-1/3 top-1/4 -z-10 w-[90rem] max-w-none text-light" aria-hidden="true">
        {{ svg src="shape" }}
    </div>

    <div class="container">
        <div class="section-x-gap items-center">
            {{ if image }}
                <div class="section-col-wide">
                    {{ img :src="image" ratio="4/5" max_width="1200" sizes="(min-width: 640px) 45vw, 100vw" }}
                </div>
            {{ /if }}

            {{ if grid }}
                <div class="section-col-narrow flex flex-col gap-8">
                    {{ grid }}
                        <div class="grid-cta__panel card-padding flex flex-col gap-6 bg-white">
                            {{ if title }}
                                <h3>{{ title }}</h3>
                            {{ /if }}

                            {{ if text }}
                                <p>{{ text }}</p>
                            {{ /if }}

                            {{ if link }}
                                {{ partial:link }}
                            {{ /if }}
                        </div>
                    {{ /grid }}
                </div>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter GridCtaSectionTest`
Expected: PASS (1 test). `{{ svg src="shape" }}` leest `resources/svg/shape.svg`, net zoals `{{ svg src="logo" }}` in `partials/navigation.antlers.html`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/gridCta.antlers.html tests/Feature/Sections/GridCtaSectionTest.php
git commit -m "feat(sections): build grid_cta section"
```

---

### Task 18: Sectie `ranges`

**Files:**
- Modify: `resources/views/partials/sections/ranges.antlers.html`
- Test: `tests/Feature/Sections/RangesSectionTest.php`

**Interfaces:**
- Consumes: `slider` (Task 6, **zonder** `from` — dit is overal een slider).
- Velden: `overline`, `title`, `range` (entries → `ranges`, elk met `title`, `short_description`, `image`, `url`).

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2700` (desktop) en `451:3048` (mobiel).

De track is 2084px breed binnen een canvas van 1744px: op desktop zijn ongeveer 3,5 kaarten zichtbaar. Elke kaart heeft de png links en titel plus korte beschrijving rechts, met een navigatieknop onder de rij.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class RangesSectionTest extends SectionTestCase
{
    public function test_renders_a_slide_per_range_and_is_always_a_slider(): void
    {
        $html = $this->render('{{ partial src="sections/ranges" }}', [
            'title' => 'Waar mogen we mee helpen?',
            'overline' => 'Aanbod',
            'range' => [
                ['title' => 'Ramen en deuren', 'short_description' => 'Volledig op maat.', 'url' => '/aanbod/ramen-en-deuren'],
                ['title' => 'Rolluiken', 'short_description' => 'Comfort en veiligheid.', 'url' => '/aanbod/rolluiken'],
            ],
        ]);

        $this->assertStringContainsString('data-section="ranges"', $html);
        $this->assertStringContainsString('data-slider', $html);
        $this->assertStringNotContainsString('data-slider-from', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter RangesSectionTest`
Expected: FAIL — marker-partial.

- [ ] **Step 4: Schrijf de partial**

```antlers
<section class="section section--default" data-section="ranges">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader }}

            {{ if range }}
                {{ partial:slider per_view="1.15,sm:2,lg:3,xl:3.5" navigation="true" }}
                    {{ range }}
                        <div class="swiper-slide">
                            <a class="range-card flex items-center gap-5" href="{{ url }}">
                                {{ if image }}
                                    {{ img :src="image" max_width="320" sizes="120px" class="w-24 shrink-0 object-contain" }}
                                {{ /if }}

                                <div class="flex flex-col gap-2">
                                    <h3 class="text-lg">{{ title }}</h3>
                                    {{ if short_description }}
                                        <p>{{ short_description }}</p>
                                    {{ /if }}
                                </div>
                            </a>
                        </div>
                    {{ /range }}
                {{ /partial:slider }}
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter RangesSectionTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/ranges.antlers.html tests/Feature/Sections/RangesSectionTest.php
git commit -m "feat(sections): build ranges section"
```

---

### Task 19: Sectie `image_gallery`

**Files:**
- Modify: `resources/views/partials/sections/imageGallery.antlers.html`
- Test: `tests/Feature/Sections/ImageGallerySectionTest.php`

**Interfaces:**
- Consumes: `slider` (Task 6, met `bleed="true"` en `pagination="true"`, **zonder** `from`).
- Velden: `overline`, `title`, `images` (assets, meerdere).

Let op: de bestaande partial gebruikt een `image_width`-veld dat niet in het nieuwe `page_builder` bestaat. Die logica verdwijnt volledig.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2902` (desktop) en `451:3272` (mobiel).

De track is 3724px breed en begint op x = -998: de slider loopt aan beide zijden buiten de container, terwijl de kop wel uitgelijnd blijft. Onderaan staan pagination-dots.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class ImageGallerySectionTest extends SectionTestCase
{
    public function test_renders_a_bleeding_slider_with_pagination(): void
    {
        $html = $this->render('{{ partial src="sections/imageGallery" }}', [
            'title' => 'Dit project van dichtbij',
            'overline' => 'In beeld',
            'images' => [
                ['url' => '/img/een.jpg'],
                ['url' => '/img/twee.jpg'],
            ],
        ]);

        $this->assertStringContainsString('data-section="image_gallery"', $html);
        $this->assertStringContainsString('slider-bleed', $html);
        $this->assertStringContainsString('swiper-pagination', $html);
        $this->assertStringNotContainsString('data-slider-from', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
    }

    public function test_no_longer_branches_on_image_width(): void
    {
        $partial = file_get_contents(resource_path('views/partials/sections/imageGallery.antlers.html'));

        $this->assertStringNotContainsString('image_width', $partial);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter ImageGallerySectionTest`
Expected: FAIL — de partial bevat nog de `image_width`-vertakkingen.

- [ ] **Step 4: Herschrijf de partial**

```antlers
<section class="section section--default" data-section="image_gallery">
    <div class="container">
        <div class="section-y-gap">
            {{ partial:sectionHeader }}

            {{ if images }}
                {{ partial:slider per_view="1.15,sm:2,lg:2.6" pagination="true" bleed="true" }}
                    {{ images }}
                        <div class="swiper-slide">
                            {{ img :src="url" ratio="16/10" max_width="1400" sizes="(min-width: 1024px) 40vw, 90vw" }}
                        </div>
                    {{ /images }}
                {{ /partial:slider }}
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter ImageGallerySectionTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/sections/imageGallery.antlers.html tests/Feature/Sections/ImageGallerySectionTest.php
git commit -m "feat(sections): rebuild image_gallery as a bleeding slider"
```

---

## Fase 3 — Content

### Task 20: Taxonomie-termen en ranges

**Files:**
- Create: `content/taxonomies/range_categories/*.md` (3 termen)
- Create: `content/collections/ranges/*.md` (9 entries)
- Test: `tests/Feature/Content/RangesContentTest.php`

**Interfaces:**
- Produces: negen `ranges`-entries met de slugs `pergolas`, `ramen-en-deuren`, `rolluiken`, `zonwering`, `garagepoorten`, `velux`, `airco`, `somfy-smart-home`, `stalen-binnendeuren`. Elke entry heeft `image: ranges/<slug>.png`.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class RangesContentTest extends TestCase
{
    public function test_every_range_exists_with_its_image(): void
    {
        $slugs = [
            'pergolas', 'ramen-en-deuren', 'rolluiken', 'zonwering', 'garagepoorten',
            'velux', 'airco', 'somfy-smart-home', 'stalen-binnendeuren',
        ];

        foreach ($slugs as $slug) {
            $entry = Entry::query()->where('collection', 'ranges')->where('slug', $slug)->first();

            $this->assertNotNull($entry, "Range {$slug} ontbreekt");
            $this->assertSame("ranges/{$slug}.png", $entry->get('image'));
            $this->assertNotEmpty($entry->get('short_description'));
        }
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter RangesContentTest`
Expected: FAIL — geen enkele range bestaat.

- [ ] **Step 3: Maak de taxonomie-termen aan**

Drie termen in `content/taxonomies/range_categories/`, elk met dezelfde vorm. `buitenzonwering.md`:

```markdown
---
id: 5f1a0d64-6b34-4f2e-9e5c-1a2b3c4d5e01
title: Buitenzonwering
---
```

Doe hetzelfde voor `schrijnwerk.md` (titel `Schrijnwerk`, id `5f1a0d64-6b34-4f2e-9e5c-1a2b3c4d5e02`) en `comfort-en-techniek.md` (titel `Comfort en techniek`, id `5f1a0d64-6b34-4f2e-9e5c-1a2b3c4d5e03`).

- [ ] **Step 4: Maak de negen range-entries aan**

Elke entry in `content/collections/ranges/<slug>.md` volgt dit patroon. `pergolas.md`:

```markdown
---
id: 8c2e41a0-0001-4a1b-9c7d-3e5f6a7b8c01
title: "Pergola's"
short_description: 'Terrasoverkappingen met draaibare of vaste lamellen, klaar voor zon, schaduw en regen.'
long_description: 'Een pergola maakt van je terras een buitenkamer die het hele jaar bruikbaar blijft. Kies voor draaibare lamellen die de zon doseren, of een vast dak met glazen schuifwanden. Alles wordt op maat gemaakt en geplaatst door onze eigen vakmensen.'
image: ranges/pergolas.png
range_category:
  - buitenzonwering
---
```

De overige acht, met dezelfde structuur:

| Bestand | title | image | range_category |
|---|---|---|---|
| `ramen-en-deuren.md` | Ramen en deuren | `ranges/ramen-en-deuren.png` | schrijnwerk |
| `rolluiken.md` | Rolluiken | `ranges/rolluiken.png` | buitenzonwering |
| `zonwering.md` | Zonwering | `ranges/zonwering.png` | buitenzonwering |
| `garagepoorten.md` | Garagepoorten | `ranges/garagepoorten.png` | schrijnwerk |
| `velux.md` | Velux dakramen | `ranges/velux.png` | schrijnwerk |
| `airco.md` | Airco | `ranges/airco.png` | comfort-en-techniek |
| `somfy-smart-home.md` | Somfy Smart Home | `ranges/somfy-smart-home.png` | comfort-en-techniek |
| `stalen-binnendeuren.md` | Stalen binnendeuren | `ranges/stalen-binnendeuren.png` | schrijnwerk |

Geef elk bestand een uniek `id` door het laatste blok op te hogen (`...8c02` t/m `...8c09`). Schrijf voor elke entry een `short_description` van één zin en een `long_description` van drie à vier zinnen in dezelfde toon als het voorbeeld — Nederlands, concreet, geen marketingsuperlatieven.

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `php please stache:clear && ./vendor/bin/phpunit --filter RangesContentTest`
Expected: PASS (1 test, 27 assertions).

- [ ] **Step 6: Commit**

```bash
git add content/taxonomies content/collections/ranges tests/Feature/Content/RangesContentTest.php
git commit -m "content: add range categories and nine ranges"
```

---

### Task 21: Products en projects

**Files:**
- Create: `content/collections/products/*.md` (6 entries)
- Create: `content/collections/projects/*.md` (6 entries)
- Test: `tests/Feature/Content/CatalogContentTest.php`

**Interfaces:**
- Produces: zes `products`-entries en zes `projects`-entries; elke project-entry verwijst via `product` naar een bestaande product-entry.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class CatalogContentTest extends TestCase
{
    public function test_six_products_exist_with_an_image(): void
    {
        $products = Entry::query()->where('collection', 'products')->get();

        $this->assertCount(6, $products);

        foreach ($products as $product) {
            $this->assertNotEmpty($product->get('image'), "Product {$product->slug()} heeft geen beeld");
        }
    }

    public function test_six_projects_exist_and_reference_a_product(): void
    {
        $projects = Entry::query()->where('collection', 'projects')->get();

        $this->assertCount(6, $projects);

        foreach ($projects as $project) {
            $this->assertNotEmpty($project->get('product'), "Project {$project->slug()} verwijst niet naar een product");
            $this->assertNotEmpty($project->get('image'));
        }
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter CatalogContentTest`
Expected: FAIL — beide collecties zijn leeg.

- [ ] **Step 3: Maak zes product-entries aan**

De zes soorten terrasoverkapping uit het ontwerp. `content/collections/products/pergola-so.md`:

```markdown
---
id: 9a3f52b1-0001-4c2d-8e6f-4a5b6c7d8e01
title: 'Pergola SO!'
text: 'Draaibare lamellen die je terras het hele jaar bruikbaar maken — zon, schaduw of beschutting tegen de regen, met één tik op je smartphone.'
image: dummy-images/test-img-1.jpg
---
```

De overige vijf, met dezelfde structuur en oplopende `id`:

| Bestand | title | image |
|---|---|---|
| `pergola-co.md` | Pergola CO! | `dummy-images/test-img-2.jpg` |
| `pergola-lo.md` | Pergola LO! | `dummy-images/test-img-3.jpg` |
| `terrasoverkapping-met-glasdak.md` | Terrasoverkapping met glasdak | `dummy-images/test-img-4.jpg` |
| `carport.md` | Carport | `dummy-images/test-img-5.jpg` |
| `veranda.md` | Veranda | `dummy-images/test-img-6.jpg` |

Schrijf per product een `text` van één à twee zinnen die het verschil met de andere types benoemt.

- [ ] **Step 4: Maak zes project-entries aan**

`content/collections/projects/pergola-so-met-glazen-schuifwanden.md`:

```markdown
---
id: b7d4e2c3-0001-4f5a-9b8c-6d7e8f9a0b01
title: 'Pergola SO! met glazen schuifwanden'
text: 'Een aangebouwde pergola met draaibare lamellen, glazen schuifwanden en zip-screens. Geïntegreerde ledverlichting verlengt de avonden tot ver in het seizoen.'
product: 9a3f52b1-0001-4c2d-8e6f-4a5b6c7d8e01
image: dummy-images/test-img-7.jpg
---
```

De overige vijf, met oplopende `id` en een `product`-verwijzing naar het bijbehorende product-id uit Step 3:

| Bestand | title | product | image |
|---|---|---|---|
| `zip-screens-op-nieuwbouwwoning.md` | Zip-screens op nieuwbouwwoning | Pergola CO! | `dummy-images/test-img-8.jpg` |
| `ramen-en-voordeur-in-aluminium.md` | Ramen en voordeur in aluminium | Veranda | `dummy-images/test-img-9.jpg` |
| `carport-in-hout-en-aluminium.md` | Carport in hout en aluminium | Carport | `dummy-images/test-img-10.jpg` |
| `veranda-met-schuifdeuren.md` | Veranda met schuifdeuren | Veranda | `dummy-images/test-img-11.jpg` |
| `rolluiken-op-rijwoning.md` | Rolluiken op rijwoning | Pergola LO! | `dummy-images/test-img-12.jpg` |

Schrijf per project een `text` van twee à drie zinnen: welke vraag de klant had en welke oplossing er kwam.

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `php please stache:clear && ./vendor/bin/phpunit --filter CatalogContentTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add content/collections/products content/collections/projects tests/Feature/Content/CatalogContentTest.php
git commit -m "content: add six products and six projects"
```

---

### Task 22: Showcase-pagina

**Files:**
- Create: `content/collections/pages/page-builder.md`
- Modify: `content/trees/collections/pages.yaml`
- Test: `tests/Feature/Content/PageBuilderPageTest.php`

**Interfaces:**
- Consumes: alle sectie-partials (Tasks 9–19) en de content uit Tasks 20–21.
- Produces: een pagina op `/page-builder` met alle elf sets, blueprint `page`, template `default`.

- [ ] **Step 1: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class PageBuilderPageTest extends TestCase
{
    public function test_showcase_page_renders_every_section(): void
    {
        $response = $this->get('/page-builder');

        $response->assertOk();

        foreach ([
            'text', 'text_image', 'ranges', 'cards', 'projects', 'technical_details',
            'features', 'grid_cta', 'image_gallery', 'cta', 'products',
        ] as $type) {
            $response->assertSee('data-section="'.$type.'"', false);
        }
    }
}
```

- [ ] **Step 2: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter PageBuilderPageTest`
Expected: FAIL — 404, de pagina bestaat niet.

- [ ] **Step 3: Maak `content/collections/pages/page-builder.md`**

Blueprint `page`, template `default`. De frontmatter bevat `page_builder` met elf items in de volgorde van het ontwerp: `text_image`, `ranges`, `cards`, `text`, `projects`, `technical_details`, `features`, `grid_cta`, `image_gallery`, `cta`, `text_image` (met `background: true`), `products`.

Kop van het bestand:

```markdown
---
id: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a01
blueprint: page
title: Pagebuilder
text: 'Samen je huis klaarmaken voor de toekomst — energiebewust, comfortabel en met vakmanschap uit je eigen buurt.'
template: default
page_builder:
  -
    id: sec01
    type: text_image
    overline: 'In de kijker'
    title: 'Pergola SO!'
    text: '<p>De pergola met draaibare lamellen die je terras het hele jaar door bruikbaar maakt — zon, schaduw of beschutting tegen de regen, met één tik op je smartphone.</p>'
    features:
      -
        id: feat01
        label: 'Automatische lamellen'
      -
        id: feat02
        label: 'Bediening via app'
      -
        id: feat03
        label: 'Belgisch maatwerk'
    image: dummy-images/test-img-13.jpg
---
```

Vul de overige tien sets aan met de teksten uit het desktopontwerp; die staan letterlijk in de Figma-metadata van `451:2676`. Gebruik voor de entry-velden de id's uit Tasks 20–21: `range` verwijst naar alle negen ranges, `projects` naar drie project-id's, `products` naar alle zes product-id's. Voor `image_gallery` gebruik je `dummy-images/test-img-14.jpg` t/m `test-img-16.jpg`, voor `cta` `dummy-images/test-img-17.jpg`, en voor `grid_cta` `winsol-team.png`.

Ontbreekt er een veld in de blueprint dat het ontwerp wel toont (bijvoorbeeld `features` op `text_image`), voeg dat veld dan toe aan de betreffende set in `resources/fieldsets/page_builder.yaml` en noem die wijziging in de commit-boodschap.

- [ ] **Step 4: Voeg de pagina toe aan de paginaboom**

In `content/trees/collections/pages.yaml` voeg je onderaan `tree:` toe:

```yaml
  -
    entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a01
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `php please stache:clear && ./vendor/bin/phpunit --filter PageBuilderPageTest`
Expected: PASS (1 test, 12 assertions).

- [ ] **Step 6: Commit**

```bash
git add content resources/fieldsets tests/Feature/Content/PageBuilderPageTest.php
git commit -m "content: add page builder showcase page"
```

---

## Fase 4 — Header, navigatie en footer

### Task 23: Page header

**Files:**
- Modify: `resources/views/partials/headers/default.antlers.html`
- Test: `tests/Feature/Sections/PageHeaderTest.php`

**Interfaces:**
- Velden uit `page_intro`: `title`, `text`.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2678` (desktop) en `451:3021` (mobiel).

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class PageHeaderTest extends SectionTestCase
{
    public function test_renders_title_and_intro(): void
    {
        $html = $this->render('{{ partial src="headers/default" }}', [
            'title' => 'Pagebuilder',
            'text' => 'Samen je huis klaarmaken voor de toekomst.',
        ]);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Pagebuilder', $html);
        $this->assertStringContainsString('Samen je huis klaarmaken', $html);
    }
}
```

De huidige partial leest `intro` in plaats van `text`; de blueprint levert `text`. Die mismatch is precies wat deze test vangt.

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter PageHeaderTest`
Expected: FAIL — de introtekst verschijnt niet.

- [ ] **Step 4: Herschrijf de partial**

```antlers
<section class="section section--default pb-0!">
    <div class="container">
        <div class="section-header-gap max-w-4xl">
            <h1>{{ title }}</h1>

            {{ if text }}
                <p class="text-lg">{{ text }}</p>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter PageHeaderTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/headers/default.antlers.html tests/Feature/Sections/PageHeaderTest.php
git commit -m "feat: rebuild page header per Figma"
```

---

### Task 24: Navigatie

**Files:**
- Modify: `resources/views/partials/navigation.antlers.html`
- Modify: `resources/views/partials/mobileNavigation.antlers.html`
- Modify: `lang/nl/site.php`

**Interfaces:**
- Consumes: de bestaande `main`-navigatiestructuur en `resources/svg/logo.svg` / `logo-inverse.svg`.
- Produces: `lang/nl/site.php` krijgt de sleutels `nav_open`, `nav_close`, `nav_label`.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2677` (desktop nav) en `451:3004` (mobiele nav).

Desktop: logo links met de regel "BY BREBO" eronder, menu-items rechts (Aanbod met dropdown, Realisaties, Service, Over ons, Contact). Mobiel: logo links, hamburger rechts.

- [ ] **Step 2: Lees de bestaande partials**

Bekijk `resources/views/partials/navigation.antlers.html`, `mobileNavigation.antlers.html`, `hamburger.antlers.html` en `resources/js/components/mobile-navigation.js` zodat je het bestaande openen/sluiten-gedrag behoudt in plaats van het opnieuw te bouwen.

- [ ] **Step 3: Herschrijf de navigatie**

Behoud de bestaande Alpine-hooks en de `main`-navigatielus; vervang alleen de markup en classes door wat Step 1 oplevert. Alle vaste labels (`nav_open`, `nav_close`, `nav_label`) komen uit `lang/nl/site.php`:

```php
    'nav_open' => 'Menu openen',
    'nav_close' => 'Menu sluiten',
    'nav_label' => 'Hoofdnavigatie',
```

- [ ] **Step 4: Verifieer in de browser**

Run: `npm run build && php please stache:clear`
Open `/page-builder` op 390px en 1744px. Controleer: het menu opent en sluit op mobiel, de focus blijft zichtbaar, en de nav overlapt de header niet.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/navigation.antlers.html resources/views/partials/mobileNavigation.antlers.html lang
git commit -m "feat: rebuild navigation per Figma"
```

---

### Task 25: Footer

**Files:**
- Modify: `resources/views/partials/footer.antlers.html`
- Test: `tests/Feature/Sections/FooterTest.php`

**Interfaces:**
- Consumes: de `main`-navigatie, de `legal`-collectie en de globals.

- [ ] **Step 1: Haal het ontwerp op**

`get_design_context` met `nodeId: 451:2964` (desktop) en `451:3339` (mobiel).

Drie kolommen (Aanbod / Bedrijf / Contact), logo met "BY BREBO", een scheidingslijn, en onderaan copyright plus de links Privacybeleid, Algemene voorwaarden en Cookies.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class FooterTest extends SectionTestCase
{
    public function test_renders_three_link_columns_and_a_colophon(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertSame(3, substr_count($html, 'footer__column'));
        $this->assertStringContainsString('footer__colophon', $html);
        $this->assertStringContainsString('BY BREBO', $html);
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter FooterTest`
Expected: FAIL — de huidige footer heeft geen kolomstructuur.

- [ ] **Step 4: Herschrijf de footer**

Drie `footer__column`-blokken, gevoed vanuit de navigatie en globals in plaats van hardcoded `<a>`-lijsten. De legal-links komen uit de `legal`-collectie:

```antlers
{{ collection:legal }}
    <a href="{{ url }}">{{ title }}</a>
{{ /collection:legal }}
```

Het copyright-jaar via `{{ now format="Y" }}`.

- [ ] **Step 5: Run de test en zie hem slagen**

Run: `./vendor/bin/phpunit --filter FooterTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/footer.antlers.html tests/Feature/Sections/FooterTest.php
git commit -m "feat: rebuild footer per Figma"
```

---

## Fase 5 — Opruimen en eindcontrole

### Task 26: Ongebruikte partials, CSS en JS verwijderen

**Files:**
- Delete: `resources/views/partials/sections/{reviews,list,images,cases,callToAction,collapses}.antlers.html`
- Delete: `resources/views/partials/blockHeader.antlers.html`
- Delete: `resources/fieldsets/collapses.yaml`
- Delete: `resources/css/components/collapse.css`
- Delete: `resources/js/components/collapses.js`
- Modify: `resources/css/site.css`, `resources/js/site.js`
- Test: `tests/Feature/Sections/CleanupTest.php`

- [ ] **Step 1: Controleer dat niets meer verwijst naar deze bestanden**

Run: `grep -rn "collapses\|blockHeader\|sections/reviews\|sections/list\|sections/images\|sections/cases\|callToAction" resources content`
Expected: alleen treffers in de te verwijderen bestanden zelf.

- [ ] **Step 2: Schrijf de falende test**

```php
<?php

namespace Tests\Feature\Sections;

class CleanupTest extends SectionTestCase
{
    public function test_unused_starter_kit_files_are_gone(): void
    {
        foreach ([
            'views/partials/sections/reviews.antlers.html',
            'views/partials/sections/list.antlers.html',
            'views/partials/sections/images.antlers.html',
            'views/partials/sections/cases.antlers.html',
            'views/partials/sections/callToAction.antlers.html',
            'views/partials/sections/collapses.antlers.html',
            'views/partials/blockHeader.antlers.html',
            'fieldsets/collapses.yaml',
            'css/components/collapse.css',
            'js/components/collapses.js',
        ] as $path) {
            $this->assertFileDoesNotExist(resource_path($path));
        }
    }
}
```

- [ ] **Step 3: Run de test en zie hem falen**

Run: `./vendor/bin/phpunit --filter CleanupTest`
Expected: FAIL — de bestanden bestaan nog.

- [ ] **Step 4: Verwijder de bestanden en hun imports**

```bash
git rm resources/views/partials/sections/{reviews,list,images,cases,callToAction,collapses}.antlers.html \
       resources/views/partials/blockHeader.antlers.html \
       resources/fieldsets/collapses.yaml \
       resources/css/components/collapse.css \
       resources/js/components/collapses.js
```

Verwijder daarna `@import './components/collapse.css';` uit `resources/css/site.css`. De import van `collapses.js` is in Task 6 al vervangen door `sliders.js`; controleer dat er geen restant staat.

- [ ] **Step 5: Run de test en de volledige suite**

Run: `./vendor/bin/phpunit`
Expected: alles groen.

- [ ] **Step 6: Commit**

```bash
git add -A resources tests/Feature/Sections/CleanupTest.php
git commit -m "chore: remove unused starter kit sections"
```

---

### Task 27: Eindcontrole tegen het ontwerp

**Files:** geen wijzigingen tenzij de controle iets blootlegt.

- [ ] **Step 1: Bouw en leeg de cache**

Run: `npm run build && php please stache:clear`
Expected: geen fouten of waarschuwingen.

- [ ] **Step 2: Draai de volledige testsuite**

Run: `./vendor/bin/phpunit`
Expected: alles groen.

- [ ] **Step 3: Vergelijk elke sectie met Figma**

Open `/page-builder` op 390px, 834px en 1744px. Leg per sectie de weergave naast de bijbehorende Figma-node uit de tabel in de spec. Noteer afwijkingen in spacing, typografie en beeldverhouding.

- [ ] **Step 4: Controleer op overflow en console-fouten**

Voer in de browserconsole uit:

```js
document.documentElement.scrollWidth > document.documentElement.clientWidth
```

Expected: `false` op alle drie de breedtes. De console bevat geen fouten.

- [ ] **Step 5: Controleer de sliders**

Op 390px: `cards`, `projects` en `products` zijn sliders. Op 1744px: diezelfde drie zijn grids en Swiper is vernietigd (`.swiper-initialized` staat er niet meer op). `ranges` en `image_gallery` zijn op beide breedtes een slider.

- [ ] **Step 6: Los gevonden afwijkingen op en commit**

```bash
git add -A
git commit -m "fix: align sections with Figma after review"
```

---

## Zelfcontrole van dit plan

- **Spec-dekking:** fundament (Task 1–8), elf secties (Task 9–19: text, text_image, technical_details, features, cards, projects, products, cta, grid_cta, ranges, image_gallery), content (Task 20–22), header/nav/footer (Task 23–25), opruimen (Task 26), verificatie (Task 27). Meertaligheid zit in Task 6 (`lang/nl/site.php`) en wordt in Task 24 uitgebreid.
- **Geverifieerd:** `resources/svg/icons/regular/check.svg` en `arrow-right.svg` bestaan; `{{ svg src="..." }}` is de tag waarmee de repo statische SVG's insluit (`cookieConsent`, `navigation`, `footer`). `{{ icon:index }}` blijft alleen in gebruik voor waarden uit het `icon`-veld (Task 12).
- **Bekend risico:** Task 22 kan een ontbrekend blueprint-veld blootleggen (`features` op `text_image`, gebruikt in Task 10) en schrijft voor hoe dat op te lossen. Dat is de enige plek waar het plan een fieldset-wijziging toestaat.
