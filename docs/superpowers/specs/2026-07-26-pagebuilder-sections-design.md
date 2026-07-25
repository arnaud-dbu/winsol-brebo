# Page Builder Sections — Design

**Date:** 2026-07-26
**Status:** Approved for planning
**Scope:** Front-end implementatie van alle elf `page_builder` sets, plus page header, navigatie en footer, de bijhorende CSS/JS-laag en demo-content. Bouwt voort op de content-structuur uit `2026-07-24-winsol-brebo-content-structure-design.md`.

## Goal

De page builder visueel afwerken volgens het Figma-ontwerp, met een showcase-pagina op de `page`-blueprint die alle secties toont. De opbouw is DRY: gedeelde bouwstenen en spacing-utilities in plaats van elf losstaande implementaties.

## Bron

Figma file `dgMxUtoYzYrR5FRuwPzQBn`, pagina **pagebuilder** (`451:2675`):

- Desktop `/page-builder` — `451:2676`, 1744px breed
- Mobile `/page-builder (mobile)` — `451:3003`, 402px breed

Design v2 (`293:2110`) dient als referentie voor bestaande vormgeving en voor de `/aanbod`-pagina met de range-namen.

## Uitgangspunten

- **Bestaande structuur blijft.** De elf sets in `resources/fieldsets/page_builder.yaml` dekken de Figma-blokken volledig; er worden geen velden bijgemaakt tenzij de implementatie een gat blootlegt. Wijzigt er toch iets aan een blueprint, dan wordt dat expliciet in het plan opgenomen.
- **Figma-blok → set:** `range` → `ranges`, `icon_features` → `features`, `grid_features` → `grid_cta`, `text_image (bg)` → `text_image` met `background: true`. De rest heet gelijk.
- **Tekst** komt uit de desktopvarianten in Figma. Op mobile zijn sommige componenten meermaals geplakt; dat toont enkel de herhaling en is geen aparte inhoud.
- **Single site.** Meertaligheid wordt voorbereid, niet opgezet.

## 1. Architectuur

### Bestandsstructuur

```
resources/views/partials/
  pageBuilder.antlers.html          uitgebreid naar alle 11 sets
  sectionHeader.antlers.html        herschreven — args: is_centered, is_inverse, tag, btn_variant
  card.antlers.html                 herschreven — gedeeld door cards/projects/products
  featureList.antlers.html          nieuw — het "✓ label"-lijstje
  slider.antlers.html               nieuw — Swiper-markup, navigatie en pagination
  overline.antlers.html             nieuw — label + streepje
  headers/default.antlers.html      herschreven volgens Figma Header
  navigation / mobileNavigation / footer   herschreven volgens Figma
  sections/
    text, textImage, ranges, cards, projects, technicalDetails,
    features, gridCta, imageGallery, cta, products   (.antlers.html)
```

Verwijderd: `sections/{reviews,list,images,cases,callToAction,collapses}.antlers.html`, `blockHeader.antlers.html` (leeg), `resources/fieldsets/collapses.yaml`, `resources/css/components/collapse.css` en `resources/js/components/collapses.js`. Gecontroleerd: geen enkele blueprint of fieldset importeert `collapses`, en het nieuwe `page_builder` heeft er geen set voor. De imports van `collapse.css` in `site.css` en van `collapses.js` in `site.js` worden mee verwijderd.

### CSS-laag

Aanpak: **token-first met een dunne component-laag**. Layout blijft Tailwind-utilities in de partial; alleen terugkerende patronen krijgen een class.

- `resources/css/site.css` (`@theme`): de `--text-*` schaal wordt fluid met `clamp()` over viewportbereik 640px→1536px, zodat bestaande `text-3xl`-achtige classes automatisch meeschalen. Ontbrekende Figma-kleuren worden toegevoegd (o.a. het donkere overlaygrijs `#292d2d`).
- `resources/css/base/typography.css`: basisstijlen voor `h1`–`h4` en `p` bovenop de fluid tokens.
- `resources/css/base/spacing.css`: bestaande `section-y-gap`, `section-x-gap`, `section-header-gap` blijven. Nieuw: `grid-gutter` (kolomafstand), `card-padding`, `slider-bleed` (track buiten de container).
- `resources/css/components/`: `overline.css`, `card.css`, `slider.css`; `button.css` wordt uitgebreid met de Figma-varianten (geel primair, inverse op donkere achtergrond).

**Regel:** typografie fluid, spacing op breakpoints.

### Herbruikbare bouwstenen

| Partial | Gebruikt door | Argumenten |
|---|---|---|
| `sectionHeader` | 9 van de 11 secties | `is_centered`, `is_inverse`, `tag` (h1/h2/h3), `btn_variant` |
| `overline` | 8 secties | `label`, `is_inverse` |
| `card` | `cards`, `projects`, `products` | `layout` (vertical/horizontal), `image`, `title`, `text`, `features`, `link` |
| `featureList` | `text_image`, `cards` | `items` |
| `slider` | `ranges`, `image_gallery`, `cards`, `projects`, `products` | `per_view`, `from` (breakpoint), `pagination`, `navigation`, `bleed` |

### Sliders

Swiper wordt via npm toegevoegd. Eén initializer `resources/js/components/sliders.js`:

- zoekt `[data-slider]` en leest opties uit `data-slider-*` attributen (`per-view` per breakpoint, `pagination`, `navigation`, `bleed`);
- `data-slider-from="md"` betekent: alleen initialiseren onder die breakpoint, en erboven de instance weer vernietigen (matchMedia-listener). Zo zijn `cards`, `projects` en `products` een grid op desktop en een slider op mobile;
- Swiper wordt dynamisch geïmporteerd zodat pagina's zonder slider de bundle niet laden.

## 2. De elf secties

| Set | Figma desktop / mobile | Desktop | Tablet | Mobile |
|---|---|---|---|---|
| `text_image` | `451:2679` / `451:3026` | beeld links (776px), tekstkolom rechts, verticaal gecentreerd | 2 kolommen, smallere gutter | gestapeld, beeld boven (16:9) |
| `text_image` (background) | `451:2944` / `451:3302` | tekstpaneel links met achtergrondvlak, beeld rechts, beide 812px | idem, lagere hoogte | tekstpaneel boven, beeld eronder |
| `ranges` | `451:2700` / `451:3048` | **slider**, track 2084px; kaart = png links + titel/tekst rechts | slider, ±2,5 zichtbaar | slider, ±1,15 zichtbaar |
| `cards` | `451:2738` / `451:3085` | 2×2 grid, horizontale kaart (beeld 261px + tekstblok 527px) | 2 kolommen | **slider**, verticale kaart (beeld boven) |
| `text` | `451:2816` / `451:3158` | 2 kolommen: grote intro links, body rechts | 2 kolommen | gestapeld |
| `projects` | `451:2820` / `451:3162` | 3 kaarten naast elkaar: beeld, categorie, titel, pijl | 2 kolommen | **slider**, ±1,1 zichtbaar |
| `technical_details` | `451:2821` / `451:3195` | spectabel links (612px, key/value met scheidingslijn), titel/tekst/knop rechts | gestapeld: kop boven, tabel onder | gestapeld, key en value onder elkaar |
| `features` | `451:2851` / `451:3222` | gecentreerde kop + 4 kolommen, icoon boven tekst | 2×2 | 1 kolom, icoon links naast tekst |
| `grid_cta` | `451:2887` / `451:3257` | beeld links overlappend, 2 gestapelde panelen rechts, `shape.svg` bleedt links buiten beeld | beeld boven, panelen onder | beeld boven, panelen gestapeld, kleinere shape |
| `image_gallery` | `451:2902` / `451:3272` | **slider** met bleed buiten de container (track 3724px), pagination-dots | 2 zichtbaar | ±1,15 zichtbaar + dots |
| `cta` | `451:2932` / `451:3293` | full-bleed foto, donker overlaypaneel onderin met overline, titel en knop | idem | overlay over volle breedte |
| `products` | `451:2949` / `451:3307` | gecentreerde kop + 2 rijen productkaarten | 2 kolommen | **slider** |

Afgeleid uit de Figma-metadata: alleen `ranges` en `image_gallery` lopen op desktop buiten het canvas en zijn daar dus een echte slider. `cards`, `projects` en `products` zijn op desktop een grid en worden pas onder `md` een slider.

`sectionHeader` staat gecentreerd bij `cards`, `features` en `products`; links bij de overige.

Alle beelden lopen via de bestaande `{{ img }}`-component met expliciete `sizes` per breakpoint.

## 3. Content en assets

Reeds aanwezig in de `assets`-container: `ranges/*.png` (9 stuks), `winsol-team.png`, `dummy-images/test-img-1..19.jpg`. `shape.svg` staat in `resources/svg/`.

Aan te maken:

- **Showcase-pagina** `content/collections/pages/page-builder.md` op de `page`-blueprint, met alle elf sets gevuld met de desktopteksten uit Figma.
- **9 `ranges`-entries**, één per png: Pergola's, Ramen en deuren, Rolluiken, Zonwering, Garagepoorten, Velux, Airco, Somfy Smart Home, Stalen binnendeuren. Elk met `image` → `ranges/<slug>.png`, korte en lange beschrijving, en een term uit `range_categories`.
- **6 `projects`-entries** (fictieve realisaties, onder meer "Pergola SO! met glazen schuifwanden", "Zip-screens op nieuwbouwwoning", "Ramen en voordeur in aluminium") met beelden uit `dummy-images/`.
- **6 `products`-entries** voor het blok "Zes soorten terrasoverkapping".
- De bijhorende termen in de `range_categories`-taxonomie.

`winsol-team.png` wordt gebruikt in `grid_cta`. `shape.svg` wordt daar inline ingesloten zodat de vorm meekleurt met de tokens.

## 4. Meertaligheid

De site blijft single-site. Alles wat de redacteur invult komt uit de blueprint. Alles wat vast in de markup staat — slider-labels, "Lees meer", aria-labels, `alt`-fallbacks — komt in `lang/nl/site.php` en wordt via `{{ trans:site.x }}` uitgelezen. Geen hardcoded NL in partials, zodat een tweede site later enkel configuratie is.

## 5. Header, navigatie en footer

- `partials/headers/default.antlers.html` krijgt de Figma Header: titel plus intro, links uitgelijnd, met de bestaande `page_intro`-velden.
- `navigation` en `mobileNavigation` worden herschreven volgens Figma, gevoed vanuit de bestaande `main`-navigatiestructuur.
- `footer` krijgt de drie kolommen (Aanbod / Bedrijf / Contact), copyright en legal-links, gevoed vanuit navigatie en globals in plaats van hardcoded lijsten.

## 6. Verificatie

De repo heeft geen view-testsuite, dus verificatie is handmatig en expliciet:

1. `npm run build` slaagt zonder waarschuwingen.
2. `php please stache:clear` daarna, showcase-pagina laadt zonder fouten.
3. De pagina wordt bekeken op 390px, 834px en 1744px; elke sectie wordt naast de bijbehorende Figma-node gelegd.
4. Geen horizontale overflow op `body`, browserconsole vrij van fouten.
5. De bestaande `phpunit`-suite blijft groen.

## 7. Volgorde van bouwen

1. Fundament: fluid tokens, `typography.css`, `spacing.css`, `button.css`, `overline`, `sectionHeader`, `card`, `featureList`, `slider` + `sliders.js` (Swiper).
2. Secties van eenvoudig naar complex: `text` → `text_image` → `technical_details` → `features` → `cards` → `projects` → `products` → `cta` → `grid_cta` → `ranges` → `image_gallery`.
3. Demo-content: taxonomie-termen, ranges, products, projects, showcase-pagina.
4. Header, navigatie, footer.
5. Opruimen van ongebruikte partials, CSS en JS.

## Buiten scope

- Nieuwe velden of blueprints, tenzij de implementatie een gat blootlegt.
- Meertalige content of een tweede site.
- De `services_overview`-, `contact`- en `invoice`-pagina's; die hebben geen page builder of vallen buiten deze opdracht.
