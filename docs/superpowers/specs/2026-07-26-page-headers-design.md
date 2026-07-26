# Page Headers — Design

**Date:** 2026-07-26
**Status:** Approved for planning
**Scope:** Vier headers, elk in een eigen partial: de home-hero (inclusief value proposition), en de detail-headers voor range, product en project. Plus de bedrading van drie collections die vandaag nog geen show-template hebben. Bouwt voort op `2026-07-26-pagebuilder-sections-design.md`.

## Goal

Elk paginatype krijgt zijn eigen header volgens Figma, als zelfstandig component. Vandaag rendert álles `headers/default.antlers.html` — een starter-kit-placeholder — en vallen `ranges`, `products` en `projects` zelfs zonder eigen template door naar `default.antlers.html`.

## Bron

Figma file `dgMxUtoYzYrR5FRuwPzQBn`, pagina **Design v2** (`293:2110`):

| Header | Desktop (1744px) | Mobile (402px) |
|---|---|---|
| home-hero | `293:2696` | — |
| value proposition | `293:2705` | — |
| range | `293:3540` | `457:6978` |
| product | `301:3495` | — |
| project | `301:3304` | — |

Alleen de range-header is voor beide breedtes getekend. Voor de andere drie wordt het mobiele gedrag afgeleid uit het desktopframe en de patronen die de bestaande secties al gebruiken; elke afleiding staat hieronder expliciet benoemd.

> Het mobiele range-frame is tijdens het uitlezen uit de file verdwenen (`get_design_context` gaf "node not found" op `457:6977`/`457:6978`, nadat metadata en screenshot er eerder wél uit kwamen). De maten hieronder komen uit die eerdere uitlezing. Verifieer ze tegen de file voor je ze vastlegt.

## Uitgangspunten

- **Vier partials, geen gedeelde header-abstractie.** De headers delen visueel bijna niets: één heeft een witte kaart op een foto, één een watermerk plus productbeeld, één gecentreerde tekst op een donkere overlay, één gecentreerde tekst op wit. Een gemeenschappelijke shell zou vier keer worden doorbroken.
- **Geen nieuwe varianten op bestaande gedeelde partials.** De follow-ups van de page builder noteren al dat `sectionHeader` en `overline` te veel bereikbare varianten hebben. Deze headers hangen daar niets aan.
- **Inspringing volgt de site, niet Figma.** De headerframes springen op desktop 56px in; `container` houdt 40px aan (`lg:px-10`) en dat gebruikt elke bestaande sectie. De headers gebruiken `container`, zodat titels verticaal uitlijnen met de content eronder. Mobiel zijn ze toch al gelijk (20px).
- **Blueprints blijven ongemoeid.** Alle velden die de headers lezen bestaan al — op één na, zie *Afhankelijkheid* hieronder.

## Afhankelijkheid: `project.range`

De project-header toont boven de H1 een label met de range-naam ("TERRASOVERKAPPING"). De `projects`-blueprint linkt vandaag naar een `product`, en `products` heeft geen relatie met een range — dat label is met de huidige velden dus niet te vullen.

Die relatie wordt in een parallelle sessie rechtgetrokken: `projects` gaat naar `range` in plaats van `product`. `headers/project.antlers.html` wordt tegen die eindsituatie geschreven en leest `range.title`. Deze spec wijzigt de blueprint **niet** — dat blijft eigendom van die branch.

Gevolg tot de merge: het label rendert niet. Dat is geen defect maar de ontbrekende relatie, en het wordt als zodanig getest (het hele label-element verdwijnt bij een lege range, er blijft geen leeg blok staan).

## 1. Architectuur

### Bestanden

```
resources/views/partials/headers/
  hero.antlers.html       herschreven (nu starter-kit placeholder)
  range.antlers.html      nieuw
  product.antlers.html    nieuw
  project.antlers.html    nieuw
  default.antlers.html    ongewijzigd

resources/views/
  ranges/show.antlers.html      nieuw
  products/show.antlers.html    nieuw
  projects/show.antlers.html    nieuw
```

`default.antlers.html` blijft staan: `articles`, `cases`, `legal` en `contact` hangen eraan.

### Bedrading

`ranges`, `products` en `projects` hebben geen `template:` in hun collection-yaml en vallen daardoor door naar `default.antlers.html`. Ze volgen het patroon dat `articles` al gebruikt:

```yaml
# content/collections/ranges.yaml
template: ranges/show
```

Idem voor `products.yaml` → `products/show` en `projects.yaml` → `projects/show`.

Elk show-bestand is twee regels, net als `home.antlers.html`:

```
{{ partial:headers/range }}
{{ partial:pageBuilder }}
```

### CSS

Eén token en één knopvariant erbij; de rest is inline utilities, zoals de bestaande sectie-partials.

**`resources/css/site.css` (`@theme`)** — een display-schaal voor de drie detail-headers:

```css
/* Fluid: mobiel (640px) → desktop (1536px), zelfde bereik als de rest van de schaal */
--text-display: clamp(2.4375rem, 0.786rem + 4.129vw, 4.75rem); /* H1-header  39 → 76 */
```

De site-`h1` is `--text-4xl` (39 → 61px) en blijft dat. De home-hero gebruikt die standaard; range, product en project gebruiken `--text-display`. Zonder token zou `text-6xl` (vast 76px) op drie call sites landen en op mobiel te groot uitvallen.

**`resources/css/components/button.css`** — `.btn--outline`:

```css
.btn--outline {
    @apply rounded-full border border-black px-8 py-5 font-semibold text-base text-black;
}
```

Zelfde vorm en padding als `.btn--accent` en `.btn--dark`, maar met rand in plaats van vulling. Dit is precies het vierde geval dat de follow-ups voorspelden ("extract een `.btn--pill` base"). Die refactor hoort **niet** in deze diff — hij raakt vier bestaande secties. De variant wordt toegevoegd en de follow-up blijft openstaan.

**`resources/css/components/header.css`** — nieuw, voor de headertypografie van alle vier de headers plus de value-proposition-typografie (zie §2.2); geen aparte `value-proposition.css`, want de scheidingslijnen zelf hebben geen component-CSS nodig — ze zijn inline `border-*`-utilities op de `<li>`'s, dus de enige reden die een los bestand zou rechtvaardigen (de scheidingslijnen) bleek niet nodig.

## 2. De headers

### 2.1 `hero` — home (`293:2696`)

Full-bleed foto van 1055px hoog, `rounded-md` (6px), 40px padding, inhoud onderaan links (`items-end`).

Op het beeld een verloop voor leesbaarheid van de nav:

```
linear-gradient(180deg, rgba(0,0,0,0.5) 0%, rgba(18,27,34,0) 26.471%)
```

Daarop een witte kaart (`293:2697`) van 620px breed, `rounded-md`, 56px padding, 32px gap:

| Element | Figma | Codebase |
|---|---|---|
| H1 (`293:2699`) | 61px semibold, lh 1.1, `#121b22` | site-`h1` (`--text-4xl`), ongewijzigd |
| Tekst (`293:2700`) | 20px regular, lh 1.5 | `p` (`--text-base`), ongewijzigd |
| Knop (`293:2702`) | rand 1px `#121b22`, px 32 / py 20, radius 56px, label 20px semibold, tracking 0.4px | `.btn--outline`, via `partial:link` |

Velden: `title`, `text`, `link`, `image`.

**Mobiel (afgeleid):** de kaart gaat naar volle breedte binnen `container` en de padding zakt van 56px naar de `card-padding`-utility (`p-6 lg:p-8`), zodat hij niet aan een 620px vaste breedte vastzit. Beeldhoogte wordt een aspect-ratio in plaats van 1055px vast.

**Randgeval:** `link` is leeg (zie §3) — de `link`-partial lust over `link` en rendert dan niets. De kaart moet daar niet op klappen: de `gap-32` mag geen loze witruimte onderaan achterlaten.

### 2.2 Value proposition — in dezelfde partial (`293:2705`)

Direct onder het beeld, in `hero.antlers.html` zelf. `bg-black` (`#121b22`), 136px hoog, `px-40` — dat is precies `container`'s `lg:px-10`, dus de strip gebruikt gewoon `container`.

Links het label (`293:2707`): 31px semibold wit, lh 1.1 → `--text-xl` (20 → 31px), blok van 303px. Bron: `value_proposition.title`.

Rechts drie cellen (`flex-1`, gap 24px, px 40 / py 28), elk:

| Element | Figma | Codebase |
|---|---|---|
| Icoon | 40×40, accent-geel | `icon`-veld (Phosphor-set uit `resources/svg/icons`) |
| Titel | 20px semibold wit, `leading-none` | `--text-base` + `font-semibold` |
| Tekst | **16px** regular wit, lh 1.5 | **vast `1rem`** — zie hieronder |

De body-tekst is 16px op een 1744px-frame. `--text-base` klimt daar juist naar 20px, dus `text-base` is hier fout: het moet een vaste `1rem` zijn, geen fluid token.

Tussen en na de cellen staan verticale 1px-lijnen: één vóór elke cel plus één na de laatste, dus vier in totaal. (Figma heeft er aan de rechterrand twee bovenop elkaar staan — `293:2733` en `293:2734` — dat is een duplicaat, geen ontwerp.) Deze lijnen zijn de enige reden voor `value-proposition.css`.

Figma zet de gap tussen titel en tekst op 8px in de eerste cel en 12px in de andere twee. Genormaliseerd naar 12px.

Velden: `value_proposition.title` en `value_proposition.items[icon, title, text]`.

**Mobiel (afgeleid):** de verticale lijnen verdwijnen en de items stapelen onder het label; scheiding via horizontale randen in plaats van verticale.

**Randgeval:** zonder `value_proposition` verdwijnt de hele strip, niet alleen de inhoud.

### 2.3 `range` — range-detail (`293:3540` / `457:6978`)

`bg-light` (`#f1f6f8`), `rounded-md`. Visueel het lastigste van de vier, omdat twee achtergrondlagen tegengesteld overloopgedrag hebben:

- **Het Winsol-W-watermerk** (`360:3243`, 1134×512, `left:-321px` `top:119px`) wordt geklipt — `overflow-clip` staat expliciet op het frame.
- **De range-png** (`361:4036`, 816×537, `left:-141px` `top:162px` t.o.v. de headerbovenkant) mag juist níet geklipt worden: hij steekt links buiten beeld én ~135px onder de header uit, over de eerste page-builder-sectie heen. In Figma staat hij daarom in de sectie eróónder geparenteerd, niet in de header.

Dat past niet in één box. De opbouw wordt dus:

```
<section>                      geen overflow-clip; de png mag eruit
  <div overflow-hidden>        alleen het W-watermerk
  <img>                        range-png, absoluut, lagere z-index dan de tekst
  <div container>              tekstblok
```

De png hoort bij de header (bevestigd), niet bij de sectie eronder — één component bezit het beeld. Randvoorwaarde: de eerste page-builder-sectie mag geen ondoorzichtige achtergrond over de png heen leggen. Dat is in de huidige secties het geval (wit), maar het is een echte koppeling en wordt als zodanig genoteerd.

Tekst rechts uitgelijnd (`justify-end`), blok van 864px, 38px gap:

| Element | Figma | Codebase |
|---|---|---|
| H1 (`293:3544`) | 76px semibold zwart, lh 1.1 | `--text-display` |
| Intro (`293:3545`) | 25px regular zwart, lh 1.5 | `--text-lg` (18 → 25px) |

Verticale ruimte: `pt-152` / `pb-112`. De 152px bovenmarge houdt rekening met de 105px hoge nav die er in Figma overheen zweeft (zie §4).

Velden: `title`, `short_description`, `image`. `long_description` blijft voor de sectie eronder.

**Mobiel (`457:6978`, 402px):** het W-watermerk 609×275 op `left:-218px` `top:139px`; de png 229×150 op de 20px-marge, ín de flow bóven de titel; daaronder H1 (39px) en intro. Alles op `container`'s 20px.

**Randgeval:** zonder `image` verdwijnt de png en houdt de header zijn hoogte — het W-watermerk en de tekst dragen het frame dan alleen.

### 2.4 `product` — product-detail (`301:3495`)

Full-bleed foto van 752px, `rounded-md`, met twéé donkere lagen:

1. Een radiaal verloop over het hele vlak, `opacity: 0.7`, van zwart naar `rgba(0,0,0,0.5)` — dit maakt het midden donkerder waar de tekst staat.
2. Hetzelfde nav-verloop als de hero, maar zwaarder: `linear-gradient(180deg, rgba(0,0,0,0.7) 0%, rgba(18,27,34,0) 26.471%)`.

Gecentreerd blok (`301:3496`) van 790px, 32px gap, 56px padding:

| Element | Figma | Codebase |
|---|---|---|
| H1 (`301:3498`) | 76px semibold wit, gecentreerd | `--text-display` |
| Intro (`301:3499`) | 25px regular wit, gecentreerd | `--text-lg` |

Geen knop.

Velden: `title`, `text`, `image`.

**Mobiel (afgeleid):** vaste hoogte wordt een aspect-ratio, blokbreedte volgt `container`, padding zakt naar de mobiele marge.

### 2.5 `project` — project-detail (`301:3304`)

Witte achtergrond. Gecentreerd tekstblok (`301:3305`, 866px breed, 24px gap), daaronder een full-width beeld.

| Element | Figma | Codebase |
|---|---|---|
| Label (`301:3307`) | 20px semibold uppercase, `rgba(18,27,34,0.5)`, tracking 0.4px | `--text-base` + `font-semibold uppercase tracking-[0.4px] text-black/50` |
| H1 (`301:3308`) | 76px semibold `#121b22`, gecentreerd | `--text-display` |
| Intro (`301:3309`) | 25px regular `#121b22`, gecentreerd | `--text-lg` |
| Beeld (`301:3310`) | 1664×836, `rounded-md`, 40px inspringend, sluit aan op de onderrand | `container` + `{{ img }}` |

Het label is **niet** de `overline`-component: die is 12→16px met `letter-spacing: 0.125em` en rendert altijd een streepje-span. Dit label is 20px, tracking 0.4px, halftransparant zwart en heeft geen streepje. Het krijgt dus gewone utilities, geen nieuwe `overline`-variant.

Velden: `title`, `text`, `image`, en `range.title` voor het label (zie *Afhankelijkheid*).

**Mobiel (afgeleid):** tekstblok volgt `container`, beeld behoudt zijn aspect-ratio.

## 3. Content

In `content/collections/pages/home.md` worden ingevuld: `title`, `text`, `image` (verwijzend naar een bestaand `dummy-images/`-asset) en de volledige `value_proposition`-groep — titel "Waarom Winsol Brebo" plus drie items met de Phosphor-iconen Flag, Ruler en Headset. Copy komt letterlijk uit Figma.

De verweesde `intro` van de starter kit gaat weg: die staat niet in de `home`-blueprint en wordt door niets gerenderd.

Twee dingen blijven bewust open in plaats van verzonnen:

- **De knop `Ontdek ons aanbod` heeft geen bestemming.** Er is geen aanbod-overzichtspagina: `range_overview.yaml` bestaat als blueprint, maar er is geen entry, en `/aanbod` is geen route — `ranges` routeert op `/aanbod/{slug}`. `link` blijft leeg; de `link`-partial rendert dan niets en de hero blijft heel. Zodra die pagina bestaat is het één veld invullen.
- **De copy wisselt tussen `je` en `uw`.** De home-hero zegt "je woning", de range- en product-headers zeggen "uw terras". Zo staat het in Figma; het wordt letterlijk overgenomen en als open punt genoteerd.

De bestaande range-, product- en projectentries worden niet aangeraakt: hun tekstvelden zijn al gevuld.

## 4. Open punten

Genoteerd, niet opgelost in deze diff:

- **De nav zweeft niet.** In Figma ligt de nav óver de home-hero, de range- en de product-header (vandaar de bovenverlopen en, op de foto-headers, een wit logo). In code is `navigation.antlers.html` in de flow met donkere tekst en `logo.svg`. De headers worden zo gebouwd dat ze in beide gevallen kloppen; de nav-wijziging zelf valt buiten deze scope. Zolang de nav in de flow staat is het bovenverloop cosmetisch.
- **`project.range`** bestaat pas na de merge van de parallelle branch (zie *Afhankelijkheid*).
- **De aanbod-overzichtspagina** ontbreekt, waardoor de hero-knop geen doel heeft.
- **`.btn--pill` base** — `.btn--outline` is de vierde knop met identieke vorm-declaraties. De follow-up om die te extraheren blijft open.
- **Alt-teksten** ontbreken nog op alle assets; beelden renderen met een lege `alt`. Dit stond al in de page-builder-follow-ups.
- **Koppeling header ↔ eerste sectie** op de range-pagina: de png steekt onder de header uit en rekent erop dat de sectie eronder geen dekkende achtergrond heeft.

## 5. Tests

Vier bestanden onder `tests/Feature/Sections/`, in de bestaande `SectionTestCase`-harnas. In de stijl van de laatste commits: asserten op echte gerenderde inhoud, niet op het aantal elementen.

**`HeroHeaderTest`**
- Titel, tekst en beeld renderen.
- De knop verschijnt met een gevulde `link` en verdwijnt zonder — de kaart blijft intact.
- De value-prop-items lussen echt: drie titels, drie teksten, drie iconen.
- Zonder `value_proposition` verdwijnt de hele strip.

**`RangeHeaderTest`**
- `title` en `short_description` renderen; `long_description` niet.
- De png verschijnt alleen bij een gevulde `image`.
- Het W-watermerk zit ín de klippende laag en de png erbuiten — dit is de kern van het component en de enige assertie die de twee overflow-regimes vastlegt.

**`ProductHeaderTest`**
- Titel, tekst en beeld renderen; beide overlays aanwezig.

**`ProjectHeaderTest`**
- Titel, tekst en beeld renderen.
- Het label rendert `range.title`.
- Zonder range verdwijnt het hele label-element — geen leeg blok. Dit is de situatie tot de parallelle branch binnen is en dus de tak die vandaag draait.

De volledige suite loopt vast op een standaard PHP-geheugenlimiet (`intervention/image` in de asset-compressietest, staat al in de page-builder-follow-ups). Draai gericht met `--filter`.
