# Page Headers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Elk paginatype krijgt zijn eigen header-partial volgens Figma — home-hero (inclusief value proposition), range, product en project — plus de show-templates die drie collections eraan koppelen.

**Architecture:** Vier zelfstandige partials onder `resources/views/partials/headers/`. Ze delen visueel bijna niets, dus er komt geen gemeenschappelijke shell; wel één gedeeld CSS-bestand voor de typografie die de site-basis moet overrulen. `ranges`, `products` en `projects` krijgen elk een `template:` in hun collection-yaml plus een tweeregelig show-bestand, precies zoals `articles` en `cases` dat al doen.

**Tech Stack:** Statamic 5 (Antlers), Laravel, Tailwind CSS v4, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-26-page-headers-design.md`

## Global Constraints

- **Ongelaagde CSS wint van `@layer utilities`.** Geverifieerd in `public/build/assets/site-*.css`: `@layer utilities` loopt tot byte ~29369; `h1`, `p`, `.section`, `.btn--*` en `.overline` staan daarná, ongelaagd. Ongelaagde CSS wint altijd van gelaagde, ongeacht specificiteit. Gevolg: **`<h1 class="text-display">` en `<p class="text-lg">` doen niets.** Typografie die de basis moet overrulen krijgt een eigen class met een directe `font-size`, zoals `.overline` doet. Dit geldt voor élke `text-*`-utility op een `h1`–`h4` of `p` in dit plan.
- **Inspringing volgt `container`** (`px-5 lg:px-10` = 20/40px), niet Figma's 56px. Zie spec §Uitgangspunten.
- **Tailwind v4-syntax:** `bg-linear-to-b` en `bg-radial`, niet `bg-gradient-*`.
- **`{{ img }}` gebruikt `:src="image"` zonder `alt`-parameter** — de tag valt terug op de alt van het asset. Dit volgt `sections/textImage.antlers.html`, de meest recente precedent.
- **Blueprints worden niet gewijzigd.** `projects.range` komt uit een parallelle branch; zie Task 5.
- **Node-ID's in commentaar.** Elke afwijking van Figma en elke afgeleide waarde (alles onder `lg` behalve de range-header) krijgt een `{{# #}}`-comment met het node-ID en de reden. Dit is de bestaande conventie in `sections/*.antlers.html`.
- **Testen draaien gericht:** de volledige suite loopt vast op een standaard PHP-geheugenlimiet (`intervention/image`). Gebruik altijd `--filter`.

## File Structure

| Bestand | Verantwoordelijkheid |
|---|---|
| `resources/css/components/button.css` | uitgebreid met `.btn--outline` |
| `resources/css/components/header.css` | **nieuw** — typografie-classes die de ongelaagde basis overrulen |

> **Afwijking van de spec.** De spec noemt een aparte `value-proposition.css` voor de scheidingslijnen van de strip. Die lijnen bleken met bestaande utilities te kunnen (`border-white/25`, geverifieerd tegen het lijn-asset in `293:2709`), dus er blijft alleen typografie over — en die hoort bij de andere headertypografie. Eén `header.css` in plaats van twee bestanden.
| `resources/css/site.css` | `--text-display` token + import van `header.css` |
| `resources/svg/watermark.svg` | **nieuw** — het Winsol-W, geëxporteerd uit `360:3243` |
| `resources/views/partials/headers/hero.antlers.html` | herschreven — beeld, kaart, value proposition |
| `resources/views/partials/headers/range.antlers.html` | **nieuw** |
| `resources/views/partials/headers/product.antlers.html` | **nieuw** |
| `resources/views/partials/headers/project.antlers.html` | **nieuw** |
| `resources/views/ranges/show.antlers.html` | **nieuw** — header + page builder |
| `resources/views/products/show.antlers.html` | **nieuw** |
| `resources/views/projects/show.antlers.html` | **nieuw** |
| `content/collections/{ranges,products,projects}.yaml` | `template:` toegevoegd |
| `content/collections/pages/home.md` | hero-copy + value proposition |
| `tests/Feature/Sections/{Hero,Range,Product,Project}HeaderTest.php` | **nieuw** |

## Test Conventions

Alle tests erven van `Tests\Feature\Sections\SectionTestCase` en renderen een partial in isolatie met `$this->render('{{ partial src="..." }}', [...])`.

**Belangrijk — beelden in tests.** `{{ img }}` re-resolveert de src via `AssetFacade::findByUrl()`, wat in debug-modus gooit voor een fixture-url die geen echt asset is. De bestaande conventie (zie `ImageGallerySectionTest`) is `config(['app.debug' => false]);` bovenaan de test, waarna `{{ img }}` stil niets rendert. Assert daarom **nooit** op `<img`, maar op de wrapper-markup rond het beeld — vandaar de `data-header-media`-hooks in de partials.

---

## Task 1: Hero — beeld en kaart

**Files:**
- Modify: `resources/css/components/button.css`
- Modify: `resources/views/partials/headers/hero.antlers.html` (volledig vervangen)
- Test: `tests/Feature/Sections/HeroHeaderTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: `.btn--outline` (CSS-class, gebruikt via `{{ partial:link style="btn btn--outline" }}`); `data-header="hero"` als testhook; de `<section data-header="hero">` waaraan Task 2 de value-proposition-strip toevoegt.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/HeroHeaderTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class HeroHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_image_wrapper(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'text' => 'Ramen, zonwering, rolluiken en meer.',
            'image' => '/img/hero.jpg',
        ]);

        $this->assertStringContainsString('data-header="hero"', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Winsol maakt je woning compleet', $html);
        $this->assertStringContainsString('Ramen, zonwering, rolluiken en meer.', $html);
        $this->assertStringContainsString('data-header-media', $html);
    }

    public function test_renders_the_button_from_a_link(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'link' => [
                ['type' => 'url', 'url' => 'winsol.eu', 'label' => 'Ontdek ons aanbod'],
            ],
        ]);

        $this->assertStringContainsString('btn--outline', $html);
        $this->assertStringContainsString('Ontdek ons aanbod', $html);
    }

    public function test_omits_the_button_entirely_without_a_link(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        // Geen lege knop-wrapper: de home-entry heeft vandaag geen `link`
        // (er is nog geen aanbod-overzichtspagina), dus dit is de tak die
        // in productie draait.
        $this->assertStringNotContainsString('btn--outline', $html);
        $this->assertStringNotContainsString('data-header-action', $html);
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php artisan test --filter=HeroHeaderTest`
Expected: FAIL — `hero.antlers.html` bevat nog de starter-kit-markup, dus `data-header="hero"` ontbreekt.

- [ ] **Step 3: Voeg `.btn--outline` toe**

In `resources/css/components/button.css`, onderaan toevoegen:

```css
/*
 * `.btn--outline` is de knop van de home-hero (Figma 293:2702): 1px zwarte
 * rand op een transparante achtergrond, verder identiek aan `.btn--accent`
 * en `.btn--dark` in vorm, padding en labelgrootte. `.btn--inverse` heeft
 * geen vorm of padding en kan dit dus niet dekken.
 *
 * Dit is de vierde knop met dezelfde vorm-declaraties. De follow-up om een
 * `.btn--pill` base te extraheren staat open in
 * docs/superpowers/specs/2026-07-26-pagebuilder-sections-followups.md en
 * hoort niet in deze diff — die raakt vier bestaande secties.
 */
.btn--outline {
    @apply rounded-full border border-black px-8 py-5 font-semibold text-base text-black;
}
```

- [ ] **Step 4: Vervang `hero.antlers.html`**

Volledige inhoud van `resources/views/partials/headers/hero.antlers.html`:

```antlers
{{#
    Home-hero — Figma 293:2696.

    Het beeld staat in de flow en bepaalt de hoogte van de sectie; het
    verloop en de kaart liggen er absoluut overheen. Zo blijft de compositie
    uit Figma (kaart linksonder, op de container-marge) op elke breedte
    gelijk, in plaats van onder `lg` om te klappen naar een stapel —
    waarvoor geen frame bestaat.

    Afgeleid (geen mobiel frame): Figma tekent alleen 1744x1055 (5/3). De
    ratio's daaronder geven de kaart genoeg hoogte om binnen het beeld te
    passen; 4/5 op mobiel, 16/9 vanaf `sm`.

    Het bovenverloop (rgba(0,0,0,0.5) -> transparant op 26,5%) staat in
    Figma onder een zwevende nav. Zolang `navigation.antlers.html` in de
    flow staat is het cosmetisch; het wordt bewust behouden zodat de header
    klopt zodra de nav wél gaat zweven. Zie de open punten in de spec.
#}}
<section class="relative isolate overflow-hidden rounded-md" data-header="hero">
    {{ if image }}
        <div data-header-media>
            {{ img :src="image" ratio="4/5" sm:ratio="16/9" lg:ratio="5/3" max_width="2560" sizes="100vw" priority="true" class="w-full" }}
        </div>
    {{ /if }}

    <div class="absolute inset-0 bg-linear-to-b from-black/50 to-transparent to-[26.5%]" aria-hidden="true"></div>

    <div class="container absolute inset-0 flex items-end py-5 lg:py-10">
        {{#
            56px padding en 620px breedte komen uit Figma 293:2697. Onder `sm`
            gaat de kaart naar volle breedte en zakt de padding naar de
            gedeelde `card-padding`-utility, zodat hij niet aan een vaste
            breedte vastzit op een smal scherm.
        #}}
        <div class="flex w-full flex-col gap-6 rounded-md bg-white card-padding sm:w-[620px] lg:gap-8 lg:p-14">
            <h1>{{ title }}</h1>

            {{ if text }}
                <p>{{ text }}</p>
            {{ /if }}

            {{#
                De `if` is nodig, niet cosmetisch: `partial:link` rendert bij
                een lege `link` alleen witruimte, en een tekstnode in een
                flexcontainer wordt een anonieme flex-item — dat zou een lege
                `gap` onderaan de kaart achterlaten.
            #}}
            {{ if link }}
                <div data-header-action>
                    {{ partial:link style="btn btn--outline" }}
                </div>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 5: Draai de test en bevestig dat hij slaagt**

Run: `php artisan test --filter=HeroHeaderTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Bouw de CSS en controleer dat `.btn--outline` ongelaagd landt**

Run: `npx vite build`

Controleer daarna dat de nieuwe class buiten `@layer utilities` staat (anders verliest hij van de basisregels):

```bash
python3 - <<'PY'
import glob, os
css = open(max(glob.glob('public/build/assets/site-*.css'), key=os.path.getmtime)).read()
start = css.index('@layer utilities{') + len('@layer utilities{')
depth, i = 1, start
while depth:
    if css[i] == '{': depth += 1
    elif css[i] == '}': depth -= 1
    i += 1
for sel in ['.btn--outline{']:
    # rfind, niet find: Tailwind heeft eigen utilities die qua naam met
    # projectcomponenten kunnen botsen (zoals `.overline`), en de
    # projectregel wordt als laatste geëmit.
    k = css.rfind(sel)
    print(f"{sel:34} ->", 'ONTBREEKT' if k < 0 else ('UNLAYERED (goed)' if k > i else 'IN UTILITIES (fout)'))
PY
```

Expected: `UNLAYERED (goed)`

- [ ] **Step 7: Commit**

```bash
git add resources/css/components/button.css resources/views/partials/headers/hero.antlers.html tests/Feature/Sections/HeroHeaderTest.php
git commit -m "feat(headers): build the home hero image and card"
```

---

## Task 2: Hero — value proposition

**Files:**
- Create: `resources/css/components/header.css`
- Modify: `resources/css/site.css`
- Modify: `resources/views/partials/headers/hero.antlers.html` (strip onderaan toevoegen)
- Modify: `tests/Feature/Sections/HeroHeaderTest.php`

**Interfaces:**
- Consumes: `<section data-header="hero">` uit Task 1.
- Produces: `resources/css/components/header.css` met `.value-proposition__title`, `.value-proposition__item-title` en `.value-proposition__item-text`. Task 3 breidt dit bestand uit met `.header-title` en `.header-intro`; Task 5 met `.header-eyebrow`.

- [ ] **Step 1: Schrijf de falende tests**

Voeg toe aan `tests/Feature/Sections/HeroHeaderTest.php`:

```php
    public function test_loops_the_value_proposition_items(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
            'value_proposition' => [
                'title' => 'Waarom Winsol Brebo',
                'items' => [
                    ['icon' => 'flag', 'title' => 'Belgisch merk', 'text' => '145 jaar vakmanschap.'],
                    ['icon' => 'ruler', 'title' => 'Maatwerk', 'text' => 'Alles op maat gemaakt.'],
                    ['icon' => 'headset', 'title' => 'Lokaal en bereikbaar', 'text' => 'Drie showrooms in de buurt.'],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-header="value-proposition"', $html);
        $this->assertStringContainsString('Waarom Winsol Brebo', $html);

        // Assert op de inhoud, niet alleen op het aantal <li>: een lus die
        // driemaal hetzelfde item rendert zou een telling overleven.
        $this->assertStringContainsString('Belgisch merk', $html);
        $this->assertStringContainsString('Maatwerk', $html);
        $this->assertStringContainsString('Lokaal en bereikbaar', $html);
        $this->assertStringContainsString('Drie showrooms in de buurt.', $html);
        $this->assertSame(3, substr_count($html, '<li'));

        // De iconen worden inline gerendeerd door de `icon`-tag, dus er
        // staan drie <svg>'s in de strip.
        $this->assertSame(3, substr_count($html, '<svg'));
    }

    public function test_omits_the_whole_strip_without_a_value_proposition(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/hero" }}', [
            'title' => 'Winsol maakt je woning compleet',
        ]);

        $this->assertStringNotContainsString('data-header="value-proposition"', $html);
    }
```

- [ ] **Step 2: Draai de tests en bevestig dat ze falen**

Run: `php artisan test --filter=HeroHeaderTest`
Expected: FAIL op `test_loops_the_value_proposition_items` — `data-header="value-proposition"` bestaat nog niet. De vier tests uit Task 1 blijven slagen.

- [ ] **Step 3: Maak `header.css`**

Nieuw bestand `resources/css/components/header.css`:

```css
/*
 * Headertypografie.
 *
 * WAAROM CLASSES EN GEEN `text-*`-UTILITIES:
 * `base/typography.css` en de component-CSS staan ongelaagd; Tailwind's
 * utilities staan in `@layer utilities`. Ongelaagde CSS wint altijd van
 * gelaagde, ongeacht specificiteit. `<h1 class="text-display">` en
 * `<p class="text-lg">` doen dus niets — de `h1`- en `p`-basisregels winnen.
 * Vandaar een class met een directe `font-size`, precies de constructie die
 * `.overline` al gebruikt. Geverifieerd in public/build/assets/site-*.css:
 * `@layer utilities` eindigt vóór de ongelaagde `h1`/`p`/`.overline`-regels.
 */

/* --- Value proposition (Figma 293:2705) --- */

.value-proposition__title {
    font-size: var(--text-xl); /* 20 → 31px, Figma 293:2707 */
}

.value-proposition__item-title {
    font-size: var(--text-base); /* 16 → 20px, Figma 293:2715 */
    line-height: 1;
}

/*
 * Vast 16px, NIET de fluid `--text-base`: die klimt op desktop naar 20px,
 * terwijl Figma 293:2716 op een 1744px-frame 16px aanhoudt. De regelhoogte
 * van 1.5 komt al uit de `p`-basisregel.
 */
.value-proposition__item-text {
    font-size: 1rem;
}
```

- [ ] **Step 4: Importeer `header.css`**

In `resources/css/site.css`, onderaan bij de componenten toevoegen (na `range-card.css`):

```css
@import './components/header.css';
```

- [ ] **Step 5: Voeg de strip toe aan `hero.antlers.html`**

Plak dit blok direct ná de sluitende `</section>` van de hero:

```antlers
{{#
    Value proposition — Figma 293:2705. Onderdeel van de hero, niet van de
    page builder: de strip hoort visueel en inhoudelijk bij de hero en de
    velden staan in de `home`-blueprint.

    De container-padding is 40px in Figma, wat exact `container`'s `lg:px-10`
    is — vandaar gewoon `container` in plaats van een eigen inspringing.

    Scheidingslijnen: wit op 25% (geverifieerd tegen het lijn-asset in
    293:2709). Figma zet er één vóór elke cel plus één na de laatste; de
    dubbele lijn rechts in de file (293:2733 naast 293:2734, en de tweede
    is zwart op 10%) is een duplicaat uit een andere context, geen ontwerp.

    Afgeleid (geen mobiel frame): onder `lg` stapelen de cellen en worden de
    verticale lijnen horizontale scheidingen.
#}}
{{ if value_proposition:items }}
    <section class="bg-black text-white" data-header="value-proposition">
        <div class="container flex flex-col lg:flex-row lg:items-center lg:gap-26">
            {{ if value_proposition:title }}
                <h2 class="value-proposition__title pt-10 lg:w-[303px] lg:shrink-0 lg:pt-0">
                    {{ value_proposition:title }}
                </h2>
            {{ /if }}

            <ul class="flex flex-1 flex-col lg:flex-row">
                {{ value_proposition:items }}
                    <li class="flex flex-1 items-start gap-6 border-t border-white/25 py-7 last:border-b lg:border-t-0 lg:border-l lg:px-10 lg:last:border-r lg:last:border-b-0">
                        {{ if icon }}
                            {{ icon:index src="{{ icon | raw }}" class="size-10 shrink-0 text-accent" aria-hidden="true" }}
                        {{ /if }}

                        <div class="flex flex-col gap-3">
                            {{ if title }}
                                <h3 class="value-proposition__item-title">{{ title }}</h3>
                            {{ /if }}

                            {{ if text }}
                                <p class="value-proposition__item-text">{{ text }}</p>
                            {{ /if }}
                        </div>
                    </li>
                {{ /value_proposition:items }}
            </ul>
        </div>
    </section>
{{ /if }}
```

- [ ] **Step 6: Draai de tests en bevestig dat ze slagen**

Run: `php artisan test --filter=HeroHeaderTest`
Expected: PASS (6 tests)

- [ ] **Step 7: Bouw en controleer de laag van de nieuwe classes**

Run: `npx vite build`

```bash
python3 - <<'PY'
import glob, os
css = open(max(glob.glob('public/build/assets/site-*.css'), key=os.path.getmtime)).read()
start = css.index('@layer utilities{') + len('@layer utilities{')
depth, i = 1, start
while depth:
    if css[i] == '{': depth += 1
    elif css[i] == '}': depth -= 1
    i += 1
for sel in ['.value-proposition__title{', '.value-proposition__item-title{', '.value-proposition__item-text{']:
    # rfind, niet find: Tailwind heeft eigen utilities die qua naam met
    # projectcomponenten kunnen botsen (zoals `.overline`), en de
    # projectregel wordt als laatste geëmit.
    k = css.rfind(sel)
    print(f"{sel:34} ->", 'ONTBREEKT' if k < 0 else ('UNLAYERED (goed)' if k > i else 'IN UTILITIES (fout)'))
PY
```

Expected: driemaal `UNLAYERED (goed)`

- [ ] **Step 8: Commit**

```bash
git add resources/css/components/header.css resources/css/site.css resources/views/partials/headers/hero.antlers.html tests/Feature/Sections/HeroHeaderTest.php
git commit -m "feat(headers): add the value proposition strip to the hero"
```

---

## Task 3: Range-header

**Files:**
- Create: `resources/svg/watermark.svg`
- Create: `resources/views/partials/headers/range.antlers.html`
- Create: `resources/views/ranges/show.antlers.html`
- Modify: `resources/css/site.css` (`--text-display` in `@theme`)
- Modify: `resources/css/components/header.css` (`.header-title`, `.header-intro`)
- Modify: `content/collections/ranges.yaml`
- Test: `tests/Feature/Sections/RangeHeaderTest.php`

**Interfaces:**
- Consumes: `resources/css/components/header.css` uit Task 2.
- Produces: `--text-display` (theme-token), `.header-title` en `.header-intro` (CSS-classes) — Task 4 en 5 gebruiken alle drie. `resources/svg/watermark.svg` wordt alleen hier gebruikt.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/RangeHeaderTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class RangeHeaderTest extends SectionTestCase
{
    public function test_renders_title_and_short_description(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'short_description' => 'Geniet het hele jaar van uw terras.',
            'long_description' => 'Deze hoort in de sectie eronder, niet in de header.',
        ]);

        $this->assertStringContainsString('data-header="range"', $html);
        $this->assertStringContainsString('Terrasoverkapping', $html);
        $this->assertStringContainsString('Geniet het hele jaar van uw terras.', $html);
        $this->assertStringNotContainsString('Deze hoort in de sectie eronder', $html);
    }

    public function test_renders_the_watermark_inside_the_clipping_layer(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => '/img/pergolas.png',
        ]);

        // De kern van dit component: het watermerk wordt geklipt en de
        // range-png niet. Als die twee ooit in dezelfde box belanden, klopt
        // één van beide niet meer — vandaar dat de volgorde en nesting hier
        // expliciet worden vastgelegd.
        $clip = strpos($html, 'data-header-watermark');
        $media = strpos($html, 'data-header-media');

        $this->assertNotFalse($clip);
        $this->assertNotFalse($media);
        $this->assertLessThan($media, $clip, 'Het watermerk staat vóór de png in de markup.');

        // Het watermerk zit in een klippende wrapper, de png staat erbuiten.
        $this->assertMatchesRegularExpression(
            '/data-header-watermark[^>]*class="[^"]*overflow-hidden/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-header-media[^>]*class="[^"]*overflow-hidden/',
            $html
        );
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);

        // Het watermerk hangt niet aan `image` en blijft de header dragen.
        $this->assertStringContainsString('data-header-watermark', $html);
    }

    public function test_ranges_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/ranges/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/ranges.yaml'));
        $this->assertStringContainsString('template: ranges/show', $yaml);

        $view = file_get_contents(resource_path('views/ranges/show.antlers.html'));
        $this->assertStringContainsString('headers/range', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php artisan test --filter=RangeHeaderTest`
Expected: FAIL — `headers/range` bestaat niet.

- [ ] **Step 3: Maak het watermerk-asset**

Nieuw bestand `resources/svg/watermark.svg`. Dit is het Winsol-W uit Figma `360:3243`, met de Figma-exportcruft (`preserveAspectRatio="none"`, `overflow`, inline `style`) verwijderd — `preserveAspectRatio="none"` zou het merk vervormen zodra breedte en hoogte niet exact de Figma-verhouding houden. De hardgecodeerde vulling `#E9EEF0` blijft staan, net zoals `resources/svg/shape.svg` zijn eigen `#F1F6F8` hardcodeert.

```svg
<svg width="1134" height="512" viewBox="0 0 1134 512" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M832.412 511.72C835.449 511.661 838.412 510.92 840.993 509.574C843.574 508.229 845.678 506.328 847.087 504.068L1133.04 7.74491C1133.04 7.74491 1137.48 6.24092e-05 1127.26 6.24092e-05H1045.54C1042.5 0.0970672 1039.54 0.863781 1036.97 2.22321C1034.39 3.58264 1032.29 5.48694 1030.87 7.74491L836.081 346.465C836.081 346.465 831.634 354.117 830.966 345.626L807.841 8.58472C807.617 6.24153 806.348 4.05565 804.287 2.46408C802.225 0.872509 799.524 -0.007471 796.724 6.24092e-05H716.675C713.646 0.0878323 710.696 0.840485 708.121 2.18301C705.546 3.52554 703.436 5.41104 702 7.65159L586.485 206.778L505.881 346.745C505.881 346.745 501.434 354.397 500.767 345.905L477.642 8.58472C477.418 6.24153 476.148 4.05565 474.087 2.46408C472.026 0.872509 469.325 -0.007471 466.524 6.24092e-05H23.8121C20.9463 -0.00824662 18.1647 0.813361 15.9368 2.32627C13.7089 3.83917 12.171 5.95076 11.5825 8.30478L0.464621 50.015C0.113392 50.8294 -0.0413423 51.6943 0.0094224 52.5594C0.0601872 53.4245 0.315437 54.2724 0.760319 55.0539C1.2052 55.8354 1.83083 56.5349 2.6008 57.1116C3.37077 57.6882 4.2697 58.1307 5.24529 58.413C6.38314 58.6016 7.55396 58.6016 8.69181 58.413H236.718C239.519 58.4055 242.22 59.2855 244.282 60.877C246.343 62.4686 247.612 64.6545 247.836 66.9977L276.632 502.949C276.882 505.275 278.163 507.438 280.221 509.01C282.28 510.583 284.966 511.449 287.749 511.44H502.212C505.25 511.381 508.213 510.64 510.794 509.294C513.374 507.949 515.479 506.048 516.888 503.788L593.045 372.033C593.045 372.033 597.492 364.381 598.048 372.873L606.831 503.509C607.081 505.835 608.362 507.998 610.421 509.57C612.479 511.142 615.166 512.009 617.949 512L832.412 511.72Z" fill="#E9EEF0"/>
</svg>
```

- [ ] **Step 4: Voeg het `--text-display` token toe**

In `resources/css/site.css`, in het `@theme`-blok direct ná de `--text-4xl`-regel:

```css
    /* Header-H1: groter dan de site-h1 (die op 61px stopt). Figma 293:3544, 301:3498, 301:3308. */
    --text-display: clamp(2.4375rem, 0.786rem + 4.129vw, 4.75rem); /* 39 → 76 */
```

- [ ] **Step 5: Voeg de headertypografie toe aan `header.css`**

In `resources/css/components/header.css`, boven het value-proposition-blok:

```css
/* --- Detail-headers (range, product, project) --- */

/*
 * LET OP: `--text-display` genereert ook een `text-display`-utility. Gebruik
 * die NIET op een `h1` — die verliest van de ongelaagde `h1`-basisregel.
 * Deze class is de werkende variant.
 */
.header-title {
    font-size: var(--text-display); /* 39 → 76px */
}

.header-intro {
    font-size: var(--text-lg); /* 18 → 25px, Figma 293:3545 / 301:3499 / 301:3309 */
}
```

- [ ] **Step 6: Maak `range.antlers.html`**

Nieuw bestand `resources/views/partials/headers/range.antlers.html`:

```antlers
{{#
    Range-header — Figma 293:3540 (desktop) en 457:6978 (mobiel).

    Twee achtergrondlagen met tegengesteld overloopgedrag, en dát is de reden
    voor de nesting hieronder:

    - Het W-watermerk WORDT geklipt: `overflow-clip` staat expliciet op het
      Figma-frame. Het zit daarom in een eigen `overflow-hidden`-wrapper.
    - De range-png wordt NIET geklipt: hij steekt links buiten beeld en op
      desktop 135px onder de header uit, over de eerste page-builder-sectie
      heen. In Figma staat hij daarom in de sectie eróónder geparenteerd
      (361:4036), niet in de header. Hier hoort hij bij de header — één
      component bezit het beeld — maar de sectie eronder mag dan geen
      dekkende achtergrond over de png leggen. Dat is vandaag zo (wit), maar
      het is een echte koppeling; zie de open punten in de spec.

    De positionering van het watermerk is proportioneel in plaats van in
    pixels: Figma geeft alleen exacte offsets voor 402px en 1744px, en
    daartussen zou een vaste pixelwaarde verlopen. De verhoudingen komen uit
    die twee frames (desktop -321/1744 en 1134/1744; mobiel -218/402 en
    609/402). De mobiele `top` is gecorrigeerd omdat het Figma-frame de 75px
    hoge nav meetelt en onze nav in de flow staat.
#}}
<section class="relative isolate rounded-md bg-light" data-header="range">
    {{# `data-header-watermark` staat vóór `class` omdat RangeHeaderTest de
        volgorde attribuut-dan-class matcht om te bewijzen dat déze wrapper
        klipt en die van de png niet. #}}
    <div data-header-watermark class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        {{ svg src="watermark" class="absolute top-[12%] -left-[54%] w-[152%] lg:top-[21%] lg:-left-[18%] lg:w-[65%]" }}
    </div>

    <div class="container flex flex-col py-14 lg:flex-row lg:justify-end lg:pt-38 lg:pb-28">
        {{ if image }}
            {{#
                Mobiel staat de png in de flow bóven de titel (457:6984);
                vanaf `lg` wordt hij absoluut en hangt hij 135px onder de
                header uit — de exacte overhang uit 361:4036.
            #}}
            <div data-header-media class="mb-8 w-[229px] max-w-[60%] lg:absolute lg:-bottom-[135px] lg:-left-[8%] lg:mb-0 lg:w-[47%]">
                {{ img :src="image" max_width="1280" sizes="(min-width: 1024px) 47vw, 60vw" class="h-auto w-full" }}
            </div>
        {{ /if }}

        <div class="relative z-10 flex flex-col gap-5 lg:w-1/2 lg:gap-9">
            <h1 class="header-title">{{ title }}</h1>

            {{ if short_description }}
                <p class="header-intro">{{ short_description }}</p>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 7: Maak `ranges/show.antlers.html`**

Nieuw bestand `resources/views/ranges/show.antlers.html`:

```antlers
{{ partial:headers/range }}
{{ partial:pageBuilder }}
```

- [ ] **Step 8: Koppel de collection aan het template**

In `content/collections/ranges.yaml`, na de `title`-regel toevoegen:

```yaml
template: ranges/show
```

- [ ] **Step 9: Draai de test en bevestig dat hij slaagt**

Run: `php artisan test --filter=RangeHeaderTest`
Expected: PASS (4 tests)

- [ ] **Step 10: Bouw en controleer de laag van de nieuwe classes**

Run: `npx vite build`

```bash
python3 - <<'PY'
import glob, os
css = open(max(glob.glob('public/build/assets/site-*.css'), key=os.path.getmtime)).read()
start = css.index('@layer utilities{') + len('@layer utilities{')
depth, i = 1, start
while depth:
    if css[i] == '{': depth += 1
    elif css[i] == '}': depth -= 1
    i += 1
for sel in ['.header-title{', '.header-intro{']:
    # rfind, niet find: Tailwind heeft eigen utilities die qua naam met
    # projectcomponenten kunnen botsen (zoals `.overline`), en de
    # projectregel wordt als laatste geëmit.
    k = css.rfind(sel)
    print(f"{sel:34} ->", 'ONTBREEKT' if k < 0 else ('UNLAYERED (goed)' if k > i else 'IN UTILITIES (fout)'))
PY
```

Expected: tweemaal `UNLAYERED (goed)`

- [ ] **Step 11: Commit**

```bash
git add resources/svg/watermark.svg resources/views/partials/headers/range.antlers.html resources/views/ranges/show.antlers.html resources/css/site.css resources/css/components/header.css content/collections/ranges.yaml tests/Feature/Sections/RangeHeaderTest.php
git commit -m "feat(headers): build the range header with watermark and product render"
```

---

## Task 4: Product-header

**Files:**
- Create: `resources/views/partials/headers/product.antlers.html`
- Create: `resources/views/products/show.antlers.html`
- Modify: `content/collections/products.yaml`
- Test: `tests/Feature/Sections/ProductHeaderTest.php`

**Interfaces:**
- Consumes: `.header-title` en `.header-intro` uit Task 3.
- Produces: niets voor latere taken.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ProductHeaderTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ProductHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_both_overlays(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
            'text' => 'De pergola met draaibare lamellen.',
            'image' => '/img/pergola.jpg',
        ]);

        $this->assertStringContainsString('data-header="product"', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('De pergola met draaibare lamellen.', $html);
        $this->assertStringContainsString('data-header-media', $html);

        // Twee donkere lagen (Figma 301:3495): een radiale verdonkering over
        // het hele vlak plus het bovenverloop. Zonder beide is de witte tekst
        // op een licht beeld onleesbaar.
        $this->assertStringContainsString('bg-radial', $html);
        $this->assertStringContainsString('bg-linear-to-b', $html);
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);
    }

    public function test_products_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/products/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/products.yaml'));
        $this->assertStringContainsString('template: products/show', $yaml);

        $view = file_get_contents(resource_path('views/products/show.antlers.html'));
        $this->assertStringContainsString('headers/product', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php artisan test --filter=ProductHeaderTest`
Expected: FAIL — `headers/product` bestaat niet.

- [ ] **Step 3: Maak `product.antlers.html`**

Nieuw bestand `resources/views/partials/headers/product.antlers.html`:

```antlers
{{#
    Product-header — Figma 301:3495.

    Twee donkere lagen over het beeld:
    1. Een radiale verdonkering (`opacity: 0.7`, zwart -> zwart/50%) die het
       midden zwaarder maakt, waar de tekst staat. De Figma-matrix beschrijft
       een ellips die het frame vult; CSS `radial-gradient` doet dat standaard
       ook (ellips, gecentreerd, farthest-corner), dus `bg-radial` is hier een
       nauwe benadering en geen willekeurige keuze.
    2. Hetzelfde bovenverloop als de home-hero, maar zwaarder (70% i.p.v. 50%).
       Zie hero.antlers.html voor waarom dat verloop er staat.

    Afgeleid (geen mobiel frame): de vaste hoogte van 752px wordt een ratio
    per breakpoint, het tekstblok volgt `container` in plaats van 790px vast.
#}}
<section class="relative isolate overflow-hidden rounded-md" data-header="product">
    {{ if image }}
        <div data-header-media>
            {{ img :src="image" ratio="4/5" sm:ratio="16/9" lg:ratio="7/3" max_width="2560" sizes="100vw" priority="true" class="w-full" }}
        </div>
    {{ /if }}

    <div class="absolute inset-0 bg-radial from-black to-black/50 opacity-70" aria-hidden="true"></div>
    <div class="absolute inset-0 bg-linear-to-b from-black/70 to-transparent to-[26.5%]" aria-hidden="true"></div>

    <div class="container absolute inset-0 flex items-center justify-center py-10 lg:py-14">
        <div class="flex w-full max-w-[790px] flex-col items-center gap-6 text-center text-white lg:gap-8">
            <h1 class="header-title">{{ title }}</h1>

            {{ if text }}
                <p class="header-intro">{{ text }}</p>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 4: Maak `products/show.antlers.html`**

Nieuw bestand `resources/views/products/show.antlers.html`:

```antlers
{{ partial:headers/product }}
{{ partial:pageBuilder }}
```

- [ ] **Step 5: Koppel de collection aan het template**

In `content/collections/products.yaml`, na de `title`-regel toevoegen:

```yaml
template: products/show
```

- [ ] **Step 6: Draai de test en bevestig dat hij slaagt**

Run: `php artisan test --filter=ProductHeaderTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/headers/product.antlers.html resources/views/products/show.antlers.html content/collections/products.yaml tests/Feature/Sections/ProductHeaderTest.php
git commit -m "feat(headers): build the product header"
```

---

## Task 5: Project-header

**Files:**
- Create: `resources/views/partials/headers/project.antlers.html`
- Create: `resources/views/projects/show.antlers.html`
- Modify: `resources/css/components/header.css` (`.header-eyebrow`)
- Modify: `content/collections/projects.yaml`
- Test: `tests/Feature/Sections/ProjectHeaderTest.php`

**Interfaces:**
- Consumes: `.header-title` en `.header-intro` uit Task 3.
- Produces: `.header-eyebrow`.

**Let op — afhankelijkheid.** Deze header leest `range.title`. De `projects`-blueprint linkt vandaag naar `product`, niet naar `range`; die relatie wordt in een parallelle branch rechtgetrokken. **Wijzig de blueprint hier niet.** Tot die merge rendert het label niet — dat is de bedoeling en wordt getest.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ProjectHeaderTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ProjectHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/project" }}', [
            'title' => 'Pergola SO! met glazen schuifwanden',
            'text' => 'Een zuidgericht terras dat het hele jaar bruikbaar werd.',
            'image' => '/img/project.jpg',
        ]);

        $this->assertStringContainsString('data-header="project"', $html);
        $this->assertStringContainsString('Pergola SO! met glazen schuifwanden', $html);
        $this->assertStringContainsString('Een zuidgericht terras dat het hele jaar bruikbaar werd.', $html);
        $this->assertStringContainsString('data-header-media', $html);
    }

    public function test_renders_the_range_name_as_eyebrow(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/project" }}', [
            'title' => 'Pergola SO! met glazen schuifwanden',
            'range' => [
                ['title' => 'Terrasoverkapping', 'url' => '/aanbod/terrasoverkapping'],
            ],
        ]);

        $this->assertStringContainsString('header-eyebrow', $html);
        $this->assertStringContainsString('Terrasoverkapping', $html);
    }

    public function test_omits_the_eyebrow_entirely_without_a_range(): void
    {
        config(['app.debug' => false]);

        // Dit is de tak die vandaag draait: `projects` linkt nog naar
        // `product`, en de `range`-relatie komt uit een parallelle branch.
        // Er mag geen leeg label-element achterblijven.
        $html = $this->render('{{ partial src="headers/project" }}', [
            'title' => 'Pergola SO! met glazen schuifwanden',
        ]);

        $this->assertStringNotContainsString('header-eyebrow', $html);
    }

    public function test_projects_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/projects/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/projects.yaml'));
        $this->assertStringContainsString('template: projects/show', $yaml);

        $view = file_get_contents(resource_path('views/projects/show.antlers.html'));
        $this->assertStringContainsString('headers/project', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
```

- [ ] **Step 2: Draai de test en bevestig dat hij faalt**

Run: `php artisan test --filter=ProjectHeaderTest`
Expected: FAIL — `headers/project` bestaat niet.

- [ ] **Step 3: Voeg `.header-eyebrow` toe aan `header.css`**

In `resources/css/components/header.css`, direct ná `.header-intro`:

```css
/*
 * Het label boven de project-H1 (Figma 301:3307) is NIET de `.overline`-
 * component: die is 12 → 16px met `letter-spacing: 0.125em` en rendert
 * altijd een streepje-span. Dit label is 20px, 0.4px tracking, halftrans-
 * parant zwart en heeft geen streepje. Een zesde `.overline`-variant zou een
 * component uitbreiden dat al te veel bereikbare varianten heeft — zie de
 * page-builder-follow-ups.
 */
.header-eyebrow {
    @apply font-semibold text-black/50 uppercase;

    font-size: var(--text-base); /* 16 → 20px */
    line-height: 1.1;
    letter-spacing: 0.02em; /* 0.4px op 20px */
}
```

- [ ] **Step 4: Maak `project.antlers.html`**

Nieuw bestand `resources/views/partials/headers/project.antlers.html`:

```antlers
{{#
    Project-header — Figma 301:3304.

    `range` is een entries-veld en wordt daarom gelust in plaats van met
    `{{ range:title }}` gelezen: Statamic augmenteert een entries-veld altijd
    naar een collectie, ook met `max_items: 1`. De lus rendert niets bij een
    lege relatie, wat precies het gedrag is dat we willen zolang de
    `range`-relatie nog niet gemerged is — zie de spec.

    Afgeleid (geen mobiel frame): het tekstblok volgt `container` in plaats
    van 866px vast, en het beeld houdt een ratio per breakpoint aan.
#}}
<section class="bg-white" data-header="project">
    <div class="container flex flex-col items-center gap-6 pt-10 text-center lg:pt-16">
        {{ range }}
            <p class="header-eyebrow">{{ title }}</p>
        {{ /range }}

        <h1 class="header-title max-w-[866px]">{{ title }}</h1>

        {{ if text }}
            <p class="header-intro max-w-[866px]">{{ text }}</p>
        {{ /if }}
    </div>

    {{ if image }}
        <div data-header-media class="container mt-10 lg:mt-16">
            {{ img :src="image" ratio="4/3" lg:ratio="2/1" max_width="2560" sizes="100vw" priority="true" class="w-full rounded-md" }}
        </div>
    {{ /if }}
</section>
```

- [ ] **Step 5: Maak `projects/show.antlers.html`**

Nieuw bestand `resources/views/projects/show.antlers.html`:

```antlers
{{ partial:headers/project }}
{{ partial:pageBuilder }}
```

- [ ] **Step 6: Koppel de collection aan het template**

In `content/collections/projects.yaml`, na de `title`-regel toevoegen:

```yaml
template: projects/show
```

- [ ] **Step 7: Draai de test en bevestig dat hij slaagt**

Run: `php artisan test --filter=ProjectHeaderTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Draai alle headertests samen**

Run: `php artisan test --filter='HeaderTest'`
Expected: PASS (17 tests: 6 hero + 4 range + 3 product + 4 project)

- [ ] **Step 9: Commit**

```bash
git add resources/views/partials/headers/project.antlers.html resources/views/projects/show.antlers.html resources/css/components/header.css content/collections/projects.yaml tests/Feature/Sections/ProjectHeaderTest.php
git commit -m "feat(headers): build the project header"
```

---

## Task 6: Home-content invullen

**Files:**
- Modify: `content/collections/pages/home.md`

**Interfaces:**
- Consumes: de veldnamen die `headers/hero.antlers.html` leest (Task 1 en 2): `title`, `text`, `image`, `link`, `value_proposition.title`, `value_proposition.items[icon, title, text]`.
- Produces: niets voor latere taken.

- [ ] **Step 1: Controleer welk dummy-asset bestaat**

De `home`-entry heeft nog geen beeld. Kijk welke assets de andere entries gebruiken:

```bash
grep -h "^image:" content/collections/products/*.md content/collections/projects/*.md | sort -u
```

Gebruik één van die paden voor `image:` hieronder — dit is bewust een tijdelijk beeld; het echte hero-beeld staat bij de open punten.

- [ ] **Step 2: Vervang de frontmatter van `home.md`**

`content/collections/pages/home.md` wordt (vervang `dummy-images/test-img-1.jpg` door het pad uit Step 1):

```yaml
---
id: e1521336-1bf2-4789-9b9f-7b2e93365671
blueprint: home
title: 'Winsol maakt je woning compleet'
home_projects:
  highlight: color
seo_noindex: false
updated_by: d308c19c-c205-4453-9862-1f62996a3734
updated_at: 1773327237
text: 'Ramen, zonwering, rolluiken, pergola''s en meer — op maat gemaakt en geplaatst door je lokale Winsol-team in Dilbeek, Sint-Pieters-Leeuw en Aartselaar.'
image: dummy-images/test-img-1.jpg
value_proposition:
  title: 'Waarom Winsol Brebo'
  items:
    -
      id: vp-belgisch-merk
      type: value_proposition
      enabled: true
      icon: flag
      title: 'Belgisch merk'
      text: 'Producten van Winsol — 145 jaar Belgisch vakmanschap.'
    -
      id: vp-maatwerk
      type: value_proposition
      enabled: true
      icon: ruler
      title: Maatwerk
      text: 'Alles op maat van je woning gemaakt en afgewerkt.'
    -
      id: vp-lokaal
      type: value_proposition
      enabled: true
      icon: headset
      title: 'Lokaal en bereikbaar'
      text: 'Drie showrooms in de buurt, ook lang na de plaatsing.'
template: home
---
```

Let op drie dingen:

1. **De verweesde `intro` is weg.** Dat veld staat niet in de `home`-blueprint (`resources/blueprints/collections/pages/home.yaml`) en wordt door niets gerenderd.
2. **`title` wordt de Figma-kop, niet `Home`.** In de blueprint staat `title` binnen de sectie `Hero`, náást `text`, `link` en `image` — het veld is daar bewust als hero-titel neergezet. De entry heet daardoor in de CP ook zo; dat is de standaard Statamic-consequentie van één `title`-veld en geen reden om de kop ergens anders te zetten. Verifieer met `grep -n -B4 -A6 'handle: title' resources/blueprints/collections/pages/home.yaml` dat `title` inderdaad onder `display: Hero` valt voor je dit doorvoert.
3. **Geen `link`.** Er is geen aanbod-overzichtspagina om naar te wijzen (`range_overview.yaml` bestaat als blueprint, maar er is geen entry, en `/aanbod` is geen route — `ranges` routeert op `/aanbod/{slug}`). De knop rendert daardoor niet; dat is getest in Task 1.

- [ ] **Step 3: Controleer dat de YAML parseert en de juiste velden bevat**

```bash
php -r 'require "vendor/autoload.php";
$parts = explode("---", file_get_contents("content/collections/pages/home.md"));
$data = Symfony\Component\Yaml\Yaml::parse($parts[1]);
echo implode(", ", array_keys($data)), PHP_EOL;
echo "items: ", count($data["value_proposition"]["items"]), PHP_EOL;
echo "intro aanwezig: ", isset($data["intro"]) ? "JA (fout)" : "nee (goed)", PHP_EOL;'
```

Expected: de sleutels bevatten `title`, `text`, `image` en `value_proposition`; `items: 3`; `intro aanwezig: nee (goed)`.

- [ ] **Step 4: Draai de headertests**

Run: `php artisan test --filter=HeroHeaderTest`
Expected: PASS (6 tests) — die draaien op fixtures, maar bevestigen dat er niets gebroken is.

- [ ] **Step 5: Commit**

```bash
git add content/collections/pages/home.md
git commit -m "content(home): fill the hero and value proposition"
```

---

## Task 7: Follow-ups vastleggen

**Files:**
- Modify: `docs/superpowers/specs/2026-07-26-pagebuilder-sections-followups.md`

**Interfaces:**
- Consumes: de bevindingen uit Task 1–6.
- Produces: niets.

- [ ] **Step 1: Voeg de nieuwe follow-ups toe**

In `docs/superpowers/specs/2026-07-26-pagebuilder-sections-followups.md`, onder **Code follow-ups**:

```markdown
- **`text-*`-utilities op `h1`–`h4` en `p` doen niets.** `base/typography.css`
  en de component-CSS staan ongelaagd; Tailwind-utilities staan in
  `@layer utilities`, en ongelaagde CSS wint daar altijd van, ongeacht
  specificiteit. Concreet dood: `<p class="text-lg">` in
  `headers/default.antlers.html` en `<h2 class="... text-base">` in
  `cookieConsent.antlers.html`. `.overline` en de nieuwe `header.css`-classes
  omzeilen dit met een directe `font-size` op een class. Beslis of de twee
  bestaande gevallen de bedoelde grootte moeten krijgen — dat is een zichtbare
  verandering op articles, cases, legal en contact.
- **`.btn--pill` base.** `.btn--outline` is de vierde knop met identieke
  vorm-declaraties naast `.btn--accent`, `.btn--cta` en `.btn--dark`.
- **Tailwind's eigen `.overline`-utility** (`text-decoration-line: overline`)
  botst qua naam met de projectcomponent. De ongelaagde projectregel wint, dus
  er is vandaag geen zichtbaar probleem, maar de naam is een valkuil.
```

Onder **Needs a decision from the client**:

```markdown
- **De nav zweeft niet.** In Figma ligt de nav over de home-hero, de range- en
  de product-header (vandaar de bovenverlopen en een wit logo op de
  foto-headers). In code staat `navigation.antlers.html` in de flow met
  donkere tekst en `logo.svg`. De headers zijn zo gebouwd dat ze in beide
  gevallen kloppen; de nav-wijziging zelf is niet gedaan.
- **De aanbod-overzichtspagina ontbreekt.** `range_overview.yaml` bestaat als
  blueprint, maar er is geen entry en `/aanbod` is geen route. De hero-knop
  "Ontdek ons aanbod" heeft daardoor geen bestemming en rendert niet.
- **De copy wisselt tussen `je` en `uw`.** De home-hero zegt "je woning", de
  range- en product-headers zeggen "uw terras". Zo staat het in Figma.
- **Het mobiele range-frame verdween tijdens het uitlezen** (`457:6977`). De
  maten in de spec komen uit een eerdere uitlezing en zijn niet opnieuw tegen
  de file geverifieerd.
- **Koppeling header ↔ eerste sectie** op de range-pagina: de png steekt onder
  de header uit en rekent erop dat de sectie eronder geen dekkende achtergrond
  heeft.
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-07-26-pagebuilder-sections-followups.md
git commit -m "docs: record header follow-ups and the CSS layering trap"
```

---

## Verificatie na afloop

- [ ] `php artisan test --filter='HeaderTest'` → 17 tests groen
- [ ] `npx vite build` → geen fouten
- [ ] Alle nieuwe CSS-classes landen ongelaagd (de check uit Task 1 Step 6, Task 2 Step 7 en Task 3 Step 10)
- [ ] `git status` is schoon op `resources/svg/test-img.png` na — dat bestand was al untracked vóór dit werk en staat bij de te verwijderen bestanden in de page-builder-follow-ups
