# Animaties en hover-effecten — implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De site-brede `hover:opacity-70` vervangen door een hover-taal per component, met geel en een kantelende pijl als rode draad, plus een subtiele parallax op de CTA-secties en de product-header.

**Architecture:** Alles is CSS en markup-attributen. Er komt geen JavaScript en geen bibliotheek bij: de pills zijn `hover:`-utilities, het mega menu ruilt `x-collapse` in voor Alpine's `x-transition`, en de parallax draait op CSS scroll-driven animations (`animation-timeline: view()`), die in browsers zonder ondersteuning netjes degradeert naar een stilstaand beeld.

**Tech Stack:** Statamic 6, Antlers, Tailwind v4 (`@utility`, `@theme`), Alpine 3, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-08-03-animaties-en-hover-effecten-design.md`

## Global Constraints

- **Tests draaien met** `php -d memory_limit=1G ./vendor/bin/phpunit`, nooit met `php artisan test`. Eén klasse: `--filter=<TestClass>`.
- **`SectionTestCase` heeft geen cascade:** `{{ globals:… }}` is daar altijd leeg. De contactbalk (taak 8) is daarom niet testbaar via phpunit — dat is browserwerk.
- **Conditionals:** één keuze is een ternary (`{{ a ? 'x' : 'y' }}`), geen `{{ if }}`-blok. Complexere logica gaat als variabele bóven de markup.
- **Styling:** Tailwind-utilities in de markup, geen `style=""`. Herhaalde klassenreeksen worden een `@utility` in `resources/css/` — componentgebonden in `components/<naam>.css`, generiek in `base/`. Nieuwe utility → import toevoegen in `site.css`. Geen arbitrary values als `bg-[#1a2b3c]`; nieuwe kleur wordt eerst een token in het `@theme`-blok.
- **Iconen:** `{{ icon src="phone" }}`, nooit `{{ svg src="icons/..." }}`. De `svg`-tag blijft voor logo's en decoratieve vormen.
- **Commentaar:** alleen wat je niet uit de code kunt lezen — een niet-evidente reden waarom iets zo staat, niet een parafrase van de regel eronder.
- **Formattering:** Prettier doet de Tailwind-klassevolgorde en de Antlers-opmaak. Niet handmatig herschikken. Draai `npx prettier --write <bestand>` na elke markup-wijziging.
- **Geen gedachtestreepjes** in Nederlandse commentaarteksten. Splits de zin of gebruik een komma.
- **Tempo-afspraak:** kleur 200ms, vorm 300ms, beeld 400–500ms. Hoe groter het bewegende vlak, hoe trager het gaat. Wijk hier niet van af zonder reden.

## Bestandsstructuur

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `resources/css/site.css` | Twee nieuwe kleurtokens + twee nieuwe imports | 1, 8, 9 |
| `resources/css/base/global.css` | Blanket-regel voor "beweging beperken" | 1 |
| `resources/css/components/button.css` | Hover per knopvariant | 2 |
| `resources/views/partials/megaMenu.antlers.html` | CTA-kaart hover + de verschijn-animatie | 2, 4 |
| `resources/css/components/navigation.css` | `nav-link`-utilities | 3 |
| `resources/views/partials/navigation.antlers.html` | Pills op de desktoplinks | 3 |
| `tests/Feature/Sections/NavigationTest.php` | Bewaakt de `inverse`-tak | 3 |
| `tests/Feature/Sections/MegaMenuTest.php` | Bewaakt dat `x-collapse` weg blijft | 4 |
| `resources/views/partials/rangeCard.antlers.html` | Shape en beeld schalen | 5 |
| `resources/views/partials/articleCard.antlers.html` | Pijlcirkel en beeldzoom | 6 |
| `resources/css/components/article-card.css` | Radius van `<img>` naar `<picture>` | 6 |
| `resources/views/partials/locationCard.antlers.html` | Gele kaart en kantelende pijl | 7 |
| `resources/css/components/locations.css` | Transition verbreden van shadow naar alles | 7 |
| `resources/css/components/contact-bar.css` | **Nieuw.** `contact-circle`-utility | 8 |
| `resources/views/partials/contactDetails.antlers.html` | Cirkels worden zwart op hover | 8 |
| `resources/css/base/motion.css` | **Nieuw.** `parallax-media` + keyframes + reduced-motion | 9 |
| `resources/css/components/cta.css` | **Nieuw.** Knop-hover schaalt het achtergrondbeeld | 9 |
| `resources/views/partials/sections/cta.antlers.html` | Parallax op het CTA-beeld | 9 |
| `resources/views/partials/headers/product.antlers.html` | Parallax op het header-beeld | 9 |

## Twee dingen die je moet weten voor je begint

Deze twee bepalen waar een `scale` wél en niet mag staan, en ze zijn niet af te leiden uit de bestanden die je aanpast.

**1. `base/global.css` geeft élke `<picture>` een `overflow: hidden`, zonder radius.**

Dat heeft twee gevolgen die tegengesteld uitpakken:

- Wil je "de container blijft staan, het beeld erin zoomt" (nieuws card, parallax), dan zet je de `scale` op de `<img>`. De `<picture>` knipt hem netjes bij.
- Wil je "het hele beeld wordt groter" (range card met een `object-contain` product-png), dan zet je de `scale` op een `<div>` óm het beeld. Op de `<img>` zou de `<picture>` de randen van het product afknippen.

En: staat de radius op de `<img>` terwijl die schaalt, dan schuiven de ronde hoeken buiten het vierkante clipvlak en zie je op hover vierkante hoeken. De radius hoort dan op de `<picture>`.

**2. `{{ img fill="true" }}` zet er `absolute inset-0 w-full h-full object-cover` vóór** (`app/Tags/Img.php:82`).

Een absoluut gepositioneerd kind wordt niet geklipt door een statische `<picture>` — het containing block is de dichtstbijzijnde gepositioneerde voorouder, in dit geval de sectie. Bij de CTA klipt de sectie dus, niet de picture. Dat is de reden dat het CTA-beeld vandaag al werkt en dat de parallax daar zonder extra wrapper kan.

---

### Taak 1: Kleurtokens en de blanket-regel voor beperkte beweging

Fundament zonder zichtbaar effect. Alles hierna leunt erop.

**Files:**
- Modify: `resources/css/site.css:15` (het `@theme`-blok)
- Modify: `resources/css/base/global.css:12-16`

**Interfaces:**
- Consumes: niets.
- Produces: de utilities `bg-accent-hover` en `bg-black-hover`, bruikbaar vanaf taak 2. Een blanket-regel die alle `transition-duration` en `animation-duration` platlegt bij *beweging beperken*.

- [ ] **Step 1: Voeg de twee kleurtokens toe**

In `resources/css/site.css`, direct onder `--color-whatsapp: #25d366;`:

```css
    --color-accent-hover: #e9c714; /* accent, ~6% donkerder */
    --color-black-hover: #23303a; /* zwart, iets lichter */
```

- [ ] **Step 2: Verbreed de reduced-motion-regel**

Vervang in `resources/css/base/global.css` het bestaande blok:

```css
@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }
}
```

door:

```css
@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }

    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

Laat het commentaarblok erboven (regels 1 tot 7) staan; dat gaat over `scroll-behavior` en klopt nog steeds.

- [ ] **Step 3: Bouw en controleer dat de regel in de output zit**

```bash
npm run build && grep -c "prefers-reduced-motion" public/build/assets/site-*.css
```

Verwacht: exit 0 en een getal van minstens 1.

- [ ] **Step 4: Commit**

```bash
git add resources/css/site.css resources/css/base/global.css
git commit -m "feat: hover-kleurtokens en blanket-regel voor beperkte beweging"
```

---

### Taak 2: Hover-states op de vier knopvarianten

**Files:**
- Modify: `resources/css/components/button.css`
- Modify: `resources/views/partials/megaMenu.antlers.html:62`

**Interfaces:**
- Consumes: `bg-accent-hover` en `bg-black-hover` uit taak 1.
- Produces: niets waar latere taken op leunen.

- [ ] **Step 1: Vervang `resources/css/components/button.css` volledig**

```css
@utility btn {
    @apply inline-flex h-fit w-fit items-center justify-center gap-x-1.5 rounded-full px-5 py-2 text-base font-semibold transition-colors duration-200 ease-out lg:px-6 lg:py-2.5 xl:px-7;

    svg {
        @apply size-5 shrink-0;
    }
}

@utility btn--primary {
    @apply bg-accent text-black hover:bg-accent-hover;
}

@utility btn--secondary {
    @apply bg-black text-white hover:bg-black-hover;
}

@utility btn--tertiary {
    @apply bg-light text-black hover:bg-light-shape;
}

@utility btn--outline {
    @apply border border-black/20 text-black hover:border-black hover:bg-black hover:text-white;
}
```

`btn--tertiary` stond niet in de briefing maar krijgt wél een hover: een knop die niet reageert leest als uitgeschakeld.

De outline-knop mag naar zwart vullen omdat hij nergens op een donker vlak staat — `headers/hero` zet hem op de witte herokaart, `quicklinkCard` op een `bg-light`-kaart, en `sectionNav` op wit boven de eerste sectie.

- [ ] **Step 2: Haal de dovende opacity van de mega-menu-CTA**

In `resources/views/partials/megaMenu.antlers.html`, regel 62. Vervang:

```
class="flex flex-col items-start gap-4 rounded-md bg-light px-8 py-6 transition-opacity hover:opacity-70">
```

door:

```
class="flex flex-col items-start gap-4 rounded-md bg-light px-8 py-6 transition-colors duration-200 ease-out hover:bg-light-shape">
```

Die kaart bevat een `<span class="btn btn--secondary">`. De oude `hover:opacity-70` zou die knop meedoven terwijl hij nu zijn eigen hover heeft; met een achtergrondwissel verkleuren de kaart en de knop elk op eigen houtje.

- [ ] **Step 3: Formatteer en bouw**

```bash
npx prettier --write resources/views/partials/megaMenu.antlers.html
npm run build && grep -c "e9c714" public/build/assets/site-*.css
```

Verwacht: exit 0 en minstens 1 — het accent-hover-token belandt nu in de output omdat `btn--primary` het gebruikt.

- [ ] **Step 4: Draai de tests die de knoppen renderen**

```bash
php -d memory_limit=1G ./vendor/bin/phpunit --filter=MegaMenuTest
```

Verwacht: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/css/components/button.css resources/views/partials/megaMenu.antlers.html
git commit -m "feat: hover-states op de knopvarianten"
```

---

### Taak 3: Pills op de header-links

De links krijgen een achtergrond in hun eigen contour. Geen meeschuivende indicator en geen JavaScript.

**Files:**
- Modify: `resources/css/components/navigation.css` (aanvullen onderaan)
- Modify: `resources/views/partials/navigation.antlers.html:7`, `:48`, `:52-59`, `:76-82`
- Test: `tests/Feature/Sections/NavigationTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: de klassen `nav-link`, `nav-link--dark`, `nav-link--light`, `nav-link--open-dark`, `nav-link--open-light`. Taak 4 raakt hetzelfde bestand maar een ander element.

- [ ] **Step 1: Schrijf de falende test**

Voeg toe aan `tests/Feature/Sections/NavigationTest.php`, binnen de klasse:

```php
    public function test_the_links_switch_hover_variant_with_the_inverse_flag(): void
    {
        // De inverse-tak is op de helft van de pagina's onzichtbaar: alleen de
        // product-header laat de nav wit over een foto zweven. Een fout daarin
        // valt op een gewone pagina nooit op.
        $donker = $this->render('{{ partial:navigation }}');
        $licht = $this->render('{{ partial:navigation inverse="true" }}');

        $this->assertStringContainsString('nav-link--dark', $donker);
        $this->assertStringNotContainsString('nav-link--light', $donker);

        $this->assertStringContainsString('nav-link--light', $licht);
        $this->assertStringNotContainsString('nav-link--dark', $licht);
    }
```

- [ ] **Step 2: Draai de test en zie hem falen**

```bash
php -d memory_limit=1G ./vendor/bin/phpunit --filter=test_the_links_switch_hover_variant_with_the_inverse_flag
```

Verwacht: FAIL — `nav-link--dark` staat nog nergens in de output.

- [ ] **Step 3: Voeg de utilities toe aan `resources/css/components/navigation.css`**

Onderaan het bestand, na `@utility nav-bar`:

```css
/*
 * De pill hangt in de contour van de link. Twee varianten omdat de nav op de
 * product-header wit over een foto zweeft: daar is zwart-op-transparant
 * onzichtbaar en andersom.
 *
 * De `--open`-varianten houden de pill vast zolang het mega menu openstaat.
 * Zonder die twee dooft de Aanbod-knop uit terwijl zijn eigen paneel nog
 * openhangt.
 */
@utility nav-link {
    @apply flex items-center gap-2 rounded-full px-4 py-1.5 text-base transition-colors duration-200 ease-out;
}

@utility nav-link--dark {
    @apply hover:bg-black/5 focus-visible:bg-black/5;
}

@utility nav-link--light {
    @apply hover:bg-white/12 focus-visible:bg-white/12;
}

@utility nav-link--open-dark {
    @apply bg-black/5;
}

@utility nav-link--open-light {
    @apply bg-white/12;
}
```

- [ ] **Step 4: Zet de twee variabelen bovenaan de partial**

In `resources/views/partials/navigation.antlers.html`, direct onder regel 7 (`{{ nav_text_class = … }}`):

```antlers
{{ nav_hover_class = inverse ? 'nav-link--light' : 'nav-link--dark' }}
{{ nav_open_class = inverse ? 'nav-link--open-light' : 'nav-link--open-dark' }}
```

- [ ] **Step 5: Verklein de gap van de `<ul>`**

Regel 48. Vervang `class="flex items-center gap-8 xl:gap-10"` door:

```
class="flex items-center gap-2 xl:gap-3"
```

Met de nieuwe `px-4` staat er tussen twee labels 40px waar nu 32 staat (op `xl` 44 tegen 40). Bevalt dat in het echt niet, dan is deze gap de knop om aan te draaien.

- [ ] **Step 6: Vervang de klasse op de Aanbod-knop**

Regel 55. Vervang:

```
class="{{ nav_text_class }} flex items-center gap-2 text-base transition-opacity hover:opacity-70 focus-visible:opacity-70"
```

door:

```
class="{{ nav_text_class }} {{ nav_hover_class }} nav-link"
:class="{ '{{ nav_open_class }}': open }"
```

De `<svg>` in deze knop heeft al een eigen `:class`; dat is een ander element, dus de twee bindingen botsen niet.

- [ ] **Step 7: Vervang de klasse op de gewone link**

Regel 78. Vervang:

```
class="{{ nav_text_class }} text-base transition-opacity hover:opacity-70 focus-visible:opacity-70 aria-[current=page]:opacity-60"
```

door:

```
class="{{ nav_text_class }} {{ nav_hover_class }} nav-link aria-[current=page]:opacity-60"
```

De actieve pagina houdt bewust zijn opacity en krijgt géén permanente pill: die zou lezen als "gehoverd" en de hover zijn betekenis afnemen.

- [ ] **Step 8: Formatteer en draai de test**

```bash
npx prettier --write resources/views/partials/navigation.antlers.html
php -d memory_limit=1G ./vendor/bin/phpunit --filter=NavigationTest
```

Verwacht: PASS, alle gevallen in de klasse.

- [ ] **Step 9: Bouw en commit**

```bash
npm run build
git add resources/css/components/navigation.css resources/views/partials/navigation.antlers.html tests/Feature/Sections/NavigationTest.php
git commit -m "feat: pill-hover op de header-links"
```

---

### Taak 4: Verschijn-animatie van het mega menu

**Files:**
- Modify: `resources/views/partials/megaMenu.antlers.html:1-8`
- Test: `tests/Feature/Sections/MegaMenuTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: niets.

De `collapse`-plugin blijft geregistreerd in `resources/js/site.js` — `partials/cookieConsent.antlers.html` gebruikt hem ook. Alleen dit paneel stapt eruit.

- [ ] **Step 1: Schrijf de falende test**

Voeg toe aan `tests/Feature/Sections/MegaMenuTest.php`, binnen de klasse:

```php
    public function test_the_panel_animates_with_a_transform_and_not_with_a_height_collapse(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // x-collapse animeert de hoogte en zou stil vechten met de scale en de
        // translate hieronder. Losse assertions op de tokens, niet op de hele
        // attribuutwaarde: Prettier sorteert `class` wel en `x-transition:*`
        // niet, en die asymmetrie moet geen test breken.
        $this->assertStringNotContainsString('x-collapse', $html);
        $this->assertStringContainsString('x-transition:enter-start', $html);
        $this->assertStringContainsString('origin-top', $html);
        $this->assertStringContainsString('scale-98', $html);
    }
```

- [ ] **Step 2: Draai de test en zie hem falen**

```bash
php -d memory_limit=1G ./vendor/bin/phpunit --filter=test_the_panel_animates_with_a_transform_and_not_with_a_height_collapse
```

Verwacht: FAIL op de eerste assertion — `x-collapse` staat er nog.

- [ ] **Step 3: Vervang de openingstag van het paneel**

In `resources/views/partials/megaMenu.antlers.html`, regels 1 tot 8. Vervang:

```antlers
<div
    id="mega-menu-panel"
    x-ref="megaPanel"
    tabindex="-1"
    x-show="open"
    x-cloak
    x-collapse
    class="pointer-events-none absolute top-full left-0 z-40 w-full">
```

door:

```antlers
<div
    id="mega-menu-panel"
    x-ref="megaPanel"
    tabindex="-1"
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-2 scale-98"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 -translate-y-2 scale-98"
    class="pointer-events-none absolute top-full left-0 z-40 w-full origin-top">
```

Sneller weg dan naartoe: dat is wat responsief laat aanvoelen zonder de beweging groter te maken.

De transition hangt aan deze buitenste wrapper en niet aan de witte kaart erin. Die wrapper draagt ook de `py-4`-strook die de cursor opvangt op weg naar het paneel; die schaalt dus mee, over 16px hoogte en 2%, wat neerkomt op een fractie van een pixel.

- [ ] **Step 4: Formatteer en draai de test**

```bash
npx prettier --write resources/views/partials/megaMenu.antlers.html
php -d memory_limit=1G ./vendor/bin/phpunit --filter=MegaMenuTest
```

Verwacht: PASS, alle gevallen in de klasse.

- [ ] **Step 5: Bouw en commit**

```bash
npm run build
git add resources/views/partials/megaMenu.antlers.html tests/Feature/Sections/MegaMenuTest.php
git commit -m "feat: mega menu verschijnt met een transform in plaats van een collapse"
```

---

### Taak 5: Range cards — shape en beeld schalen

**Files:**
- Modify: `resources/views/partials/rangeCard.antlers.html:4-9`

**Interfaces:**
- Consumes: niets.
- Produces: niets.

De shadow-lift in `resources/css/components/range-card.css` blijft ongemoeid. Die is met een Figma-verwijzing gedocumenteerd en is de klik-affordance; de schaling komt eroverheen, niet ervoor in de plaats.

- [ ] **Step 1: Zet `group` op de kaart**

Regel 4. Vervang `class="range-card relative isolate flex …"` door:

```
class="range-card group relative isolate flex h-full flex-col overflow-hidden rounded-sm bg-light card-padding @lg:flex-row @lg:items-center @lg:gap-4"
```

- [ ] **Step 2: Laat de shape uitzetten**

Regel 5. Vervang de `class` van de `svg`-tag door:

```
class="pointer-events-none absolute left-0 top-1/2 -z-10 size-full -translate-x-1/4 -translate-y-1/3 transition-transform duration-500 ease-out group-hover:scale-110 [&_path]:fill-black/3"
```

De shape krijgt méér beweging dan het beeld en dat is geen willekeur: hij is `fill-black/3`, bijna onzichtbaar, dus 5% zou je niet opmerken.

In Tailwind v4 zijn `translate` en `scale` losse CSS-eigenschappen, dus de `scale` wist de bestaande `-translate-y-1/3 -translate-x-1/4` niet. Er is geen `transform`-conflict om te omzeilen.

- [ ] **Step 3: Laat het beeld meekomen, via de wrapper**

Regel 7. Vervang de `class` van de `<div>` om het beeld door:

```
class="relative z-10 flex w-2/3 shrink-0 items-center transition-transform duration-500 ease-out group-hover:scale-105 @lg:w-1/3 @lg:justify-center @lg:px-4"
```

**De `scale` hoort hier op de `<div>` en niet op de `<img>`.** `base/global.css` geeft elke `<picture>` een `overflow: hidden`; een schalende `<img>` zou daar tegenaan lopen en de randen van het `object-contain`-product afknippen. De wrapper heeft geen clip, dus daar groeit het beeld in zijn geheel.

Laat de `class` van de `{{ img }}`-tag op regel 8 ongemoeid.

- [ ] **Step 4: Formatteer, bouw en draai de kaarttest**

```bash
npx prettier --write resources/views/partials/rangeCard.antlers.html
npm run build
php -d memory_limit=1G ./vendor/bin/phpunit --filter=RangeCardTest
```

Verwacht: PASS, zonder aanpassing aan de test. `RangeCardTest` assert op `class="range-card` als prefix. Prettier zet eigen utilities vooraan en `group` daar direct achter — zie `articleCard.antlers.html`, dat vandaag al `class="article-card group …"` is — dus die prefix blijft heel.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/rangeCard.antlers.html
git commit -m "feat: range card laat shape en beeld meeademen op hover"
```

---

### Taak 6: Nieuws cards — pijlcirkel en beeldzoom

**Files:**
- Modify: `resources/views/partials/articleCard.antlers.html`
- Modify: `resources/css/components/article-card.css` (aanvullen onderaan)

**Interfaces:**
- Consumes: niets.
- Produces: niets.

`partials/productCard.antlers.html` gebruikt deze waarden al (`group-hover:scale-105 duration-500` op het beeld, `bg-accent`-cirkel om een `-rotate-45`-pijl). Dit maakt af wat daar al staat.

- [ ] **Step 1: Verplaats de radius naar de `<picture>`**

Voeg onderaan `resources/css/components/article-card.css` toe:

```css
/*
 * De radius hoort op de `<picture>` en niet op de `<img>`: `base/global.css`
 * geeft elke picture een `overflow: hidden`, maar geen radius. Schaalt de img
 * op met zijn eigen ronde hoeken, dan schuiven die hoeken buiten het vierkante
 * clipvlak en verschijnen er op hover scherpe hoeken.
 *
 * De productkaart heeft dit probleem niet omdat daar de `<a>` zelf
 * `overflow-hidden rounded-md` draagt en dus de knipper is.
 */
.article-card picture {
    @apply rounded-md;
}
```

- [ ] **Step 2: Laat het beeld zoomen en haal de radius van de `<img>`**

In `resources/views/partials/articleCard.antlers.html`, regel 3. Vervang:

```antlers
{{ img :src="image" ratio="1/1" sizes="(min-width: 1024px) 33vw, 90vw" class="rounded-md max-h-100" }}
```

door:

```antlers
{{ img :src="image" ratio="1/1" sizes="(min-width: 1024px) 33vw, 90vw" class="max-h-100 transition-transform duration-500 ease-out group-hover:scale-105" }}
```

Hier staat de `scale` wél op de `<img>`: de `<picture>` blijft precies staan waar hij staat en knipt het groeiende beeld bij. Dat is letterlijk "de fotocontainer blijft identiek".

- [ ] **Step 3: Geef de pijl een eigen cirkel**

Regels 12 tot 14. Vervang:

```antlers
            <span aria-hidden="true" class="contents">
                {{ icon src="arrow-right" class="size-6 lg:size-7 shrink-0 -rotate-45 transition-transform group-hover:translate-x-1" }}
            </span>
```

door:

```antlers
            <span
                aria-hidden="true"
                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent/0 transition-colors duration-200 ease-out group-hover:bg-accent lg:size-11">
                {{ icon src="arrow-right" class="size-5 lg:size-6 shrink-0 -rotate-45 transition-transform duration-300 ease-out group-hover:rotate-0" }}
            </span>
```

De cirkel is in rust `bg-accent/0` en niet afwezig: de doos staat er dus altijd, en er is geen sprong in de layout op het moment dat de kleur invalt.

De oude `group-hover:translate-x-1` verdwijnt — de kanteling van −45° naar 0° neemt die taak over.

- [ ] **Step 4: Formatteer, bouw en draai de kaarttest**

```bash
npx prettier --write resources/views/partials/articleCard.antlers.html
npm run build
php -d memory_limit=1G ./vendor/bin/phpunit --filter=ArticleCardTest
```

Verwacht: PASS, zonder aanpassing aan de test. `ArticleCardTest` assert op `class="article-card` als prefix en op `<span class="article-card__category">`; geen van beide raakt de `<img>` of de pijl. Er staat geen assertion op `rounded-md`.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/articleCard.antlers.html resources/css/components/article-card.css
git commit -m "feat: nieuws card krijgt een gele pijlcirkel en een zoomend beeld"
```

---

### Taak 7: Locatiekaarten — geel op hover, kantelende pijl

**Files:**
- Modify: `resources/views/partials/locationCard.antlers.html:3`, `:10-12`
- Modify: `resources/css/components/locations.css:10`

**Interfaces:**
- Consumes: niets.
- Produces: niets.

- [ ] **Step 1: Verbreed de transition van de kaart**

In `resources/css/components/locations.css`, regel 10. Vervang:

```css
    @apply shadow-md/0 transition-shadow duration-200 ease-out;
```

door:

```css
    @apply shadow-md/0 transition duration-200 ease-out;
```

Zonder deze verbreding klapt het geel er hard in terwijl de schaduw netjes opbouwt. Tailwind v4's `transition` dekt onder meer `background-color` en `box-shadow`.

- [ ] **Step 2: Maak de kaart geel op hover**

In `resources/views/partials/locationCard.antlers.html`, regel 3. Vervang:

```
class="location-card flex h-full items-center justify-between gap-5 rounded-md bg-light card-padding"
```

door:

```
class="location-card group flex h-full items-center justify-between gap-5 rounded-md bg-light card-padding hover:bg-accent"
```

- [ ] **Step 3: Laat de pijl kantelen**

Regel 11. Vervang de `class` van de `svg`-tag door:

```
class="w-3.5 -rotate-45 transition-transform duration-300 ease-out group-hover:rotate-0"
```

De witte cirkel eromheen blijft wit. Op geel leest die nog steeds als een knop, en zwart maken zou hem laten botsen met de contactbalk uit taak 8, waar zwart juist hét hover-signaal is.

- [ ] **Step 4: Formatteer, bouw en draai de locatietest**

```bash
npx prettier --write resources/views/partials/locationCard.antlers.html
npm run build
php -d memory_limit=1G ./vendor/bin/phpunit --filter=LocationsTest
```

Verwacht: alle gevallen groen **behalve** `test_it_credits_the_tile_providers_outside_the_hidden_map`, die al rood stond vóór dit plan begon en identiek faalt op `main`. Die hoort bij de kaart-attributie en staat los van dit werk. **Repareer hem niet** en pas de test niet aan; meld hem alleen als hij van foutmelding verandert, want dan heeft jouw wijziging hem wél geraakt.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/locationCard.antlers.html resources/css/components/locations.css
git commit -m "feat: locatiekaart wordt geel en kantelt zijn pijl op hover"
```

---

### Taak 8: Contactbalk — cirkels worden zwart

**Files:**
- Create: `resources/css/components/contact-bar.css`
- Modify: `resources/css/site.css` (import toevoegen)
- Modify: `resources/views/partials/contactDetails.antlers.html:29-70`

**Interfaces:**
- Consumes: niets.
- Produces: de klasse `contact-circle`.

**Niet testbaar via phpunit.** `SectionTestCase` rendert zonder cascade, dus `{{ globals:contact:… }}` is leeg en de hele `{{ if }}`-tak met deze drie cirkels rendert niet. `ContactDetailsTest` test om diezelfde reden vandaag alleen de locatiekaarten. Controleer dit in de browser.

- [ ] **Step 1: Maak `resources/css/components/contact-bar.css`**

```css
/*
 * De drie cirkels in de contactbalk van `partials/contactDetails` delen hun
 * vorm en verschillen alleen in kleur.
 */
@utility contact-circle {
    @apply flex size-10 shrink-0 items-center justify-center rounded-full transition-colors duration-200 ease-out;
}
```

- [ ] **Step 2: Voeg de import toe**

In `resources/css/site.css`, onder `@import './components/locations.css';`:

```css
@import './components/contact-bar.css';
```

- [ ] **Step 3: Herschrijf de WhatsApp-link**

In `resources/views/partials/contactDetails.antlers.html`, regels 31 tot 40. Vervang de `class` van de `<a>`:

```
class="flex items-center gap-3 text-base font-semibold transition-opacity hover:opacity-70"
```

door:

```
class="group flex items-center gap-3 text-base font-semibold"
```

en de `class` van de `<span>` eromheen het glyph:

```
class="flex size-10 shrink-0 items-center justify-center rounded-full bg-whatsapp text-white"
```

door:

```
class="contact-circle bg-whatsapp text-white group-hover:bg-black"
```

Hier verandert alleen de achtergrond. Dat merkglyph heeft `fill="white"` in het bestand zelf staan, dus het icoon is al wit en blijft dat — laat het bestaande commentaar op regel 38 staan, dat legt precies dit uit.

- [ ] **Step 4: Herschrijf de telefoonlink**

Regels 47 tot 53. Vervang de `class` van de `<a>` door:

```
class="group flex items-center gap-3 text-base font-semibold"
```

en de `class` van de `<span>` door:

```
class="contact-circle bg-accent text-black group-hover:bg-black group-hover:text-white"
```

- [ ] **Step 5: Herschrijf de e-maillink**

Regels 60 tot 66. Exact dezelfde twee vervangingen als in stap 4:

```
class="group flex items-center gap-3 text-base font-semibold"
```

en

```
class="contact-circle bg-accent text-black group-hover:bg-black group-hover:text-white"
```

De `hover:opacity-70` gaat van alle drie de links af. Anders dooft de hele regel terwijl de cirkel het signaal moet dragen.

- [ ] **Step 6: Formatteer, bouw en draai de bestaande test**

```bash
npx prettier --write resources/views/partials/contactDetails.antlers.html
npm run build && grep -c "contact-circle" public/build/assets/site-*.css
php -d memory_limit=1G ./vendor/bin/phpunit --filter=ContactDetailsTest
```

Verwacht: het grep-getal is minstens 1, en de test blijft groen (hij dekt de locatiekaarten, niet de balk).

- [ ] **Step 7: Commit**

```bash
git add resources/css/components/contact-bar.css resources/css/site.css resources/views/partials/contactDetails.antlers.html
git commit -m "feat: contactcirkels worden zwart op hover"
```

---

### Taak 9: Parallax op de CTA-secties en de product-header

De laatste taak, en de enige die nieuwe CSS-infrastructuur introduceert.

**Files:**
- Create: `resources/css/base/motion.css`
- Create: `resources/css/components/cta.css`
- Modify: `resources/css/site.css` (twee imports)
- Modify: `resources/views/partials/sections/cta.antlers.html:3`
- Modify: `resources/views/partials/headers/product.antlers.html:32`

**Interfaces:**
- Consumes: de blanket-regel uit taak 1 (die de parallax bewust *niet* stilzet — zie stap 1), en `.btn` uit taak 2.
- Produces: de klassen `parallax-media` en `cta__media`.

- [ ] **Step 1: Maak `resources/css/base/motion.css`**

```css
/*
 * Scroll-gedreven parallax zonder JavaScript. `view()` koppelt de animatie aan
 * de positie van het element in het venster; een browser zonder ondersteuning
 * negeert `animation-timeline` en toont een stilstaand beeld. Dat is de juiste
 * degradatie voor een effect dat sowieso subtiel hoort te zijn.
 *
 * De zoom is afgeleid van de shift, niet gekozen: het beeld schuift 6% omhoog
 * én 6% omlaag, dus er moet aan weerszijden 6% extra hoogte onder de rand
 * zitten. Verlaagt de shift, dan mag de zoom mee omlaag.
 *
 * `--parallax-zoom` staat los van de keyframes zodat een component de zoom kan
 * bijstellen zonder de beweging te raken; `components/cta.css` doet dat op
 * hover. `scale` en `translate` zijn losse eigenschappen, dus een transition op
 * de ene botst niet met een animatie op de andere.
 */
@utility parallax-media {
    --parallax-zoom: 1.12;
    --parallax-shift: 6%;

    scale: var(--parallax-zoom);
    animation: parallax-rise linear both;
    animation-timeline: view();
    animation-range: cover;
}

@keyframes parallax-rise {
    from {
        translate: 0 calc(var(--parallax-shift) * -1);
    }
    to {
        translate: 0 var(--parallax-shift);
    }
}

/*
 * Een scroll-gedreven animatie leest zijn voortgang uit de tijdlijn en niet uit
 * `animation-duration`, dus de blanket-regel in base/global.css raakt hem niet.
 * De tijdlijn losknippen is wat hem wél stilzet.
 */
@media (prefers-reduced-motion: reduce) {
    .parallax-media {
        animation-timeline: none;
        scale: 1;
    }
}
```

- [ ] **Step 2: Maak `resources/css/components/cta.css`**

```css
/*
 * De knop staat in `partials/sectionHeader`, diep in de sectie, en het beeld is
 * zijn oom. Van kind naar boven selecteren kan alleen met `:has()`, vandaar een
 * regel op de sectie in plaats van een `group` op de knop.
 *
 * De CTA rendert twee sectionHeaders, een mobiele en een desktopvariant, maar
 * er is er altijd één `hidden`. Een verborgen knop kan niet gehoverd worden,
 * dus de twee bijten elkaar niet.
 */
.cta__media {
    transition: scale 400ms ease-out;
}

.cta:has(.btn:hover) .cta__media {
    --parallax-zoom: 1.18;
}
```

- [ ] **Step 3: Voeg beide imports toe aan `resources/css/site.css`**

Onder `@import './base/scrollbar.css';`:

```css
@import './base/motion.css';
```

En onder `@import './components/offerte-form.css';`:

```css
@import './components/cta.css';
```

- [ ] **Step 4: Zet de parallax op het CTA-beeld**

In `resources/views/partials/sections/cta.antlers.html`, regel 3. Vervang:

```antlers
{{ img :src="image" fill="true" sizes="100vw" class="size-full object-cover" }}
```

door:

```antlers
{{ img :src="image" fill="true" sizes="100vw" class="cta__media parallax-media size-full object-cover" }}
```

`fill="true"` zet er `absolute inset-0 …` vóór, dus de `<picture>` klipt dit beeld niet — de sectie doet dat, met haar `overflow-hidden`. Er is hier dus geen extra wrapper nodig.

- [ ] **Step 5: Zet de parallax op het product-headerbeeld**

In `resources/views/partials/headers/product.antlers.html`, regel 32. Vervang:

```antlers
class="product-frame w-full"
```

door:

```antlers
class="product-frame parallax-media w-full"
```

Hier staat het beeld wél in de flow, binnen een `<picture>` met `overflow: hidden`. Dat is precies het venster dat de parallax nodig heeft: de picture blijft staan, het beeld erin drijft.

De twee donkere lagen op regels 35 en 36 liggen `absolute inset-0` op de sectie en niet op het beeld, dus het verloop en de tekst blijven staan terwijl de foto eronder beweegt. Laat ze ongemoeid.

- [ ] **Step 6: Formatteer en controleer dat de animatie in de output zit**

```bash
npx prettier --write resources/views/partials/sections/cta.antlers.html resources/views/partials/headers/product.antlers.html
npm run build && grep -c "animation-timeline" public/build/assets/site-*.css
```

Verwacht: exit 0 en minstens 1.

- [ ] **Step 7: Draai de tests van beide secties**

```bash
php -d memory_limit=1G ./vendor/bin/phpunit --filter='CtaSectionTest|ProductHeaderTest'
```

Verwacht: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/css/base/motion.css resources/css/components/cta.css resources/css/site.css resources/views/partials/sections/cta.antlers.html resources/views/partials/headers/product.antlers.html
git commit -m "feat: subtiele parallax op de CTA-secties en de product-header"
```

---

### Taak 10: Volledige suite en handmatige controle

**Files:** geen.

- [ ] **Step 1: Draai de hele suite**

```bash
php -d memory_limit=1G ./vendor/bin/phpunit
```

Verwacht: PASS. Zonder een voorafgaande `npm run build` falen zestien HTTP-tests met een 500 omdat het Vite-manifest ontbreekt; die build heb je in taak 9 al gedaan.

- [ ] **Step 2: Loop de zes punten langs die je alleen ziet door te kijken**

Start `npm run dev` en controleer:

1. De nav-pills op een lichte pagina én op de product-header (wit over een foto).
2. Het mega menu openen en sluiten, en de cursor van de Aanbod-link naar de kaart bewegen zonder dat het paneel dichtklapt.
3. De vier knopvarianten naast elkaar, op wit en op `bg-light`. De outline-knop staat op de herokaart, in een quicklink-kaart en in de ankerbalk van de servicepagina.
4. De parallax bij traag scrollen: merkbaar, maar niet als beweging die de aandacht opeist.
5. De CTA-knop hoveren en zien of het beeld eronder meeschaalt.
6. Alles nog eens met "beweging beperken" aan in de systeeminstellingen. De parallax hoort dan volledig stil te staan, niet alleen trager te lopen.

- [ ] **Step 3: Meld de uitkomst**

Rapporteer wat je zag bij elk van de zes punten. Wijkt de nav-breedte af van wat je verwachtte, dan is `gap-2 xl:gap-3` in `partials/navigation.antlers.html:48` de knop om aan te draaien; dat is een bewust losgelaten schroef, geen fout.
