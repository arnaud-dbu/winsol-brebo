# Overzichtspagina's `/aanbod` en `/realisaties` — Design

**Date:** 2026-07-26
**Status:** Approved for planning
**Scope:** De twee overzichtspagina's: `/aanbod` (ranges gegroepeerd per range-categorie) en `/realisaties` (projecten met een rangefilter). Inclusief het datamodel dat beide nodig hebben, de extractie van twee kaartpartials uit bestaande secties, en een Alpine-filtercomponent. Bouwt voort op `2026-07-26-pagebuilder-sections-design.md`.

## Goal

De twee overzichtspagina's afwerken volgens Figma, zonder één regel kaartmarkup of kaart-CSS te dupliceren. Wat vandaag in de page-builder-secties zit wordt uitgetrokken naar partials die zowel de slider als de nieuwe grids bedienen.

## Bron

Figma file `dgMxUtoYzYrR5FRuwPzQBn`, pagina **Design v2** (`293:2110`). De maatgevende frames zijn de nieuwste rij (`y=6510`):

- `/aanbod (dekstop)` — `454:5356`, 1744px
- `/aanbod (mobile)` — `454:5541`, 402px
- `/realisaties (dekstop)` — `457:6010`, 1744px
- `/realisaties` mobile — `457:6195`, 402px (draagt in Figma dezelfde naam als de desktopvariant)

De oudere frames `293:3002` en `297:2847` tonen dezelfde pagina's en zijn niet leidend.

## Buiten scope

- **`/aanbod/categorie`** (`293:3516`) — de range-detailpagina. Ranges routeren al naar `/aanbod/{slug}` maar hebben geen eigen template en vallen dus terug op `default`. Aparte opdracht.
- **`/realisaties/realisatie`** (`301:3280`) en **`/aanbod/categorie/product`** (`301:3494`).
- De **AanbodNav**-dropdown (`366:4827`) uit de hoofdnavigatie.

## Uitgangspunten

- **Dezelfde componenten als in de secties.** De range-kaart en de project-kaart worden uitgetrokken naar partials; slider en grid roepen dezelfde partial aan. Geen tweede CSS-implementatie.
- **Geen swiper op de overzichtspagina's.** Op mobile stapelen de kaarten; dat volgt uit een grid met één kolom, niet uit een uitgezette slider.
- **De CTA onderaan is page-builder-inhoud**, geen hardcoded blok. Beide blueprints hebben al een `page_builder`; de CTA is daar de eerste set in.
- **Geen animatie in het filter.** Zichtbaarheid is een binair `hidden`-attribuut, server- en client-side hetzelfde.
- **Bestaande pagina's veranderen niet.** Elke wijziging aan een gedeeld partial is additief via een argument met een default die het huidige gedrag bewaart.

---

## 1. Content en datamodel

### 1.1 Taxonomie `range_categories`

De drie bestaande termen worden hernoemd (slug én titel) naar de Figma-indeling:

| Oude slug | Nieuwe slug | Titel | `order` |
|---|---|---|---|
| `schrijnwerk` | `voor-je-woning` | Voor je woning | 1 |
| `buitenzonwering` | `rondom-je-woning` | Rondom je woning | 2 |
| `comfort-en-techniek` | `slim-en-comfort` | Slim & comfort | 3 |

`resources/blueprints/taxonomies/range_categories/range_categories.yaml` krijgt een veld `order` (type `integer`, verplicht). Zonder dat veld sorteert Statamic termen alfabetisch en komt "Slim & comfort" bovenaan.

De koppeling oud→nieuw is inhoudelijk arbitrair — de drie oude termen worden hergebruikt als dragers, niet vertaald. Wat telt is de eindtoestand hieronder.

### 1.2 Herindeling van de negen ranges

| Categorie | Ranges |
|---|---|
| Voor je woning | `ramen-en-deuren`, `stalen-binnendeuren`, `velux`, `airco` |
| Rondom je woning | `rolluiken`, `zonwering`, `pergolas`, `garagepoorten` |
| Slim & comfort | `somfy-smart-home` |

Vier, vier en één — exact de Figma-volgorde binnen elk blok.

### 1.3 `projects`: `product` wordt `range`

In `resources/blueprints/collections/projects/projects.yaml` vervalt het veld `product` (entries → `products`, max 1) en komt `range` (entries → `ranges`, max 1, verplicht) ervoor in de plaats. Reden: de filterknoppen en het categorielabel op de projectkaart zijn in Figma ranges, en filteren via `product` zou twee hops vergen terwijl niets een product aan een range koppelt.

De zes project-entries worden omgezet:

| Project | Range |
|---|---|
| `pergola-so-met-glazen-schuifwanden` | `pergolas` |
| `veranda-met-schuifdeuren` | `pergolas` |
| `carport-in-hout-en-aluminium` | `pergolas` |
| `zip-screens-op-nieuwbouwwoning` | `zonwering` |
| `ramen-en-voordeur-in-aluminium` | `ramen-en-deuren` |
| `rolluiken-op-rijwoning` | `rolluiken` |

De `products`-collectie en de `products`-page-builder-sectie blijven ongewijzigd; alleen de relatie vanuit projecten verdwijnt.

### 1.4 Twee range-titels uitlijnen op Figma

Twee range-entries dragen een titel die afwijkt van het ontwerp. Omdat diezelfde titel zowel op de kaart als op de filterknop van `/realisaties` terechtkomt, worden ze gelijkgetrokken:

| Slug | Huidige titel | Nieuwe titel |
|---|---|---|
| `pergolas` | Pergola's | Terrasoverkappingen & pergola's |
| `velux` | Velux dakramen | VELUX dakramen |

De slugs blijven ongewijzigd. De `short_description`-teksten worden **niet** aangepast: die van de Figma-`/aanbod`-kaarten wijken op meerdere plekken af van de bestaande content, maar dat is een copy-beslissing van de klant en geen bouwbeslissing. Zie de openstaande punten.

### 1.5 Twee page-entries

De blueprints `range_overview.yaml` en `projects_overview.yaml` bestaan al (beide `page_intro` + `page_builder`) maar hebben geen entry.

- `content/collections/pages/aanbod.md` — blueprint `range_overview`, template `range-overview`, titel "Ons aanbod", intro uit `454:5358`.
- `content/collections/pages/realisaties.md` — blueprint `projects_overview`, template `projects-overview`, titel "Realisaties", intro uit `457:6012`.

Beide krijgen in `page_builder` één `cta`-set met de Figma-inhoud ("Niet zeker welke oplossing past?"). De page-builder start dus onderaan bij álle twee de pagina's, niet enkel bij aanbod.

Routes botsen niet: `pages` routeert `{parent_uri}/{slug}` → `/aanbod`, terwijl de `ranges`-collectie `/aanbod/{slug}` gebruikt. Verschillende diepte.

---

## 2. Gedeelde bouwstenen

### 2.1 `partials/rangeCard.antlers.html`

De `<a class="range-card">` uit `sections/ranges.antlers.html`, letterlijk verplaatst. Krijgt één argument:

- `sizes` — de `sizes`-attribuutwaarde voor `{{ img }}`, default `128px` (de huidige sliderwaarde), zodat de bestaande aanroep in `sections/ranges` ongewijzigd blijft renderen.

`sections/ranges.antlers.html` roept de partial aan binnen zijn `<div class="swiper-slide">`; `/aanbod` binnen een grid-cel. `range-card.css` blijft waar het staat.

### 2.2 `partials/projectCard.antlers.html`

De `<a class="project-card">` uit `sections/projects.antlers.html`, verplaatst. Twee wijzigingen:

- Het categorielabel leest `range` in plaats van `product` (zie 1.3).
- `sizes` wordt een argument, default de huidige `(min-width: 1024px) 33vw, 90vw`.

### 2.3 `partials/rangeFilter.antlers.html`

Het filtercomponent, los van de pagina. Rendert een `<nav>` met een `aria-label`, daarin "Toon alles" gevolgd door de ranges die minstens één project hebben. Elke knop is een echte `<a href="?range={slug}">` met:

- `@click.prevent="select('{slug}')"`
- `:class` en `:aria-current` voor de actieve staat, plus dezelfde staat server-side voorgezet vanuit `{{ get:range }}`

De partial kent de projectgrid niet; hij muteert alleen `active` op de omliggende Alpine-scope.

### 2.4 `resources/css/components/range-filter.css`

Pilvorm (volledig afgerond, ~54px hoog desktop / ~37px mobile, 32px horizontale padding), actieve staat zwart met witte tekst, inactieve staat lichtgrijs. Op mobile een horizontaal scrollende rij (`overflow-x: auto`, geen zichtbare scrollbar); vanaf `lg` een verticale kolom.

De follow-up-notitie signaleert al dat `.btn--accent`, `.btn--cta` en `.btn--dark` dezelfde vormdeclaraties herhalen en om een `.btn--pill`-basis vragen. Die extractie hoort niet bij deze opdracht en wordt hier niet meegenomen; `range-filter.css` staat op zichzelf.

### 2.5 `headers/default.antlers.html` krijgt `divider`

Eén nieuw argument: `divider="true"` rendert een horizontale lijn onder de header, vanaf `lg`. In Figma zit die lijn in het Header-component zelf (component `293:3753`, 430px hoog inclusief lijn); instanties die hem niet tonen zijn 360px (`/over-ons`, `/contact`, `/page-builder`), en op beide mobile-frames staat `Line 4` op `hidden`. Vandaar: component-argument, opt-in, desktop-only.

Alle acht bestaande aanroepers geven het argument niet mee en renderen ongewijzigd.

---

## 3. `/aanbod` — `resources/views/range-overview.antlers.html`

```antlers
{{ partial:headers/default divider="true" }}

<section class="section section--default" data-section="range-overview">
    <div class="container">
        <div class="section-y-gap">
            {{ taxonomy:range_categories sort="order" }}
                {{ if entries | length }}
                    <div class="section-header-gap">
                        {{ partial:overline :label="title" }}
                        <ul class="grid grid-gutter lg:grid-cols-2">
                            {{ entries }}
                                <li>{{ partial:rangeCard sizes="(min-width: 1024px) 50vw, 90vw" }}</li>
                            {{ /entries }}
                        </ul>
                    </div>
                {{ /if }}
            {{ /taxonomy:range_categories }}
        </div>
    </div>
</section>

{{ partial:pageBuilder }}
```

De exacte tagvorm voor "termen met hun ranges, gesorteerd op `order`" wordt tijdens de implementatie geverifieerd tegen de Statamic-versie in dit project — `{{ taxonomy:range_categories }}` met een `collection`-parameter is het vertrekpunt. Het gedrag ligt vast, de tagsyntaxis is een implementatiedetail.

Wat dit oplost:

- **"Juist verdeeld over de pagina"** — elk categorieblok is een overline plus een tweekolomsgrid. De laatste categorie met één range vult de linkerhelft, zoals `454:5437` toont.
- **Meer of minder dan drie categorieën** is geen speciaal geval. De loop rendert wat er staat op volgorde van `order` en slaat categorieën zonder ranges over.
- **Mobile stapelt** omdat `lg:grid-cols-2` pas vanaf 1024px grijpt. Geen `data-slider`, geen swiper-JS op deze pagina.

---

## 4. `/realisaties` — `resources/views/projects-overview.antlers.html`

```antlers
{{ partial:headers/default divider="true" }}

<section class="section section--default" data-section="projects-overview"
         x-data="projectFilter('{{ get:range }}')">
    <div class="container">
        <div class="grid grid-gutter lg:grid-cols-[24rem_1fr] lg:gap-x-16">
            {{ partial:rangeFilter }}

            <ul class="grid grid-gutter md:grid-cols-2">
                {{ collection:projects }}
                    <li {{ if get:range && get:range != range:slug }}hidden{{ /if }}
                        :hidden="!matches('{{ range:slug }}')">
                        {{ partial:projectCard }}
                    </li>
                {{ /collection:projects }}
            </ul>
        </div>
    </div>
</section>

{{ partial:pageBuilder }}
```

### 4.1 Het filtermechanisme

De server rendert **altijd alle projecten**. De querystring `?range=` bepaalt uitsluitend welke `<li>` bij de eerste paint een `hidden`-attribuut draagt en welke pil actief staat. Alpine neemt datzelfde attribuut daarna over via `:hidden`.

Daaruit volgt:

- "Toon alles" werkt zonder request, want alle kaarten staan al in de DOM.
- `/realisaties?range=zonwering` toont het juiste resultaat, ook zonder JavaScript.
- Server en client sturen hetzelfde attribuut aan, dus geen flits wanneer Alpine boot.
- `hidden` is binair, dus per definitie geen animatie.

Zonder JavaScript zijn de knoppen gewone links die een paginalading veroorzaken en server-side hetzelfde resultaat opleveren. Er is geen scenario waarin content onbereikbaar wordt.

Met static caching op `half` bedient één gecachete `/realisaties` elk filter; de `?range=`-varianten cachen desnoods als aparte URL's zonder dat er iets breekt.

### 4.2 `resources/js/components/project-filter.js`

```js
export function projectFilter(initial = '') {
    return {
        active: initial || 'all',
        matches(slug) {
            return this.active === 'all' || this.active === slug
        },
        select(slug) {
            this.active = slug
            const url = new URL(window.location)
            slug === 'all' ? url.searchParams.delete('range') : url.searchParams.set('range', slug)
            history.replaceState({}, '', url)
        },
    }
}
```

Geregistreerd in `resources/js/site.js` naast `cookieConsent`, via `Alpine.data('projectFilter', projectFilter)`.

Een `?range=` die naar geen enkele bestaande range verwijst laat `matches()` overal `false` teruggeven en toont een lege grid. Server-side geldt hetzelfde. Dat is aanvaardbaar: het kan alleen via een handmatig gemanipuleerde URL, want de knoppen genereren enkel bestaande slugs.

### 4.3 Filterknoppen

Alleen ranges met minstens één project, plus "Toon alles" vooraan. Het filter groeit zo mee met de content en een klik levert nooit een lege grid op — wat ook verklaart waarom Figma zeven van de negen ranges toont. De volgorde volgt de `ranges`-collectie.

Het label "Toon alles" komt in `lang/nl/site.php` als `filter_all`, naast de bestaande `slider_previous` en `nav_label`.

---

## 5. Tests

Nieuw:

- `tests/Feature/Content/RangeCategoriesContentTest.php` — drie termen met de juiste slugs, `order` 1/2/3, en de 4/4/1-verdeling van de negen ranges.
- `tests/Feature/Pages/RangeOverviewPageTest.php` — `<h1>`, de divider onder de header, drie overlines in `order`-volgorde, negen range-kaarten, en de afwezigheid van `data-slider`.
- `tests/Feature/Pages/ProjectsOverviewPageTest.php` — het filter bevat "Toon alles" plus uitsluitend ranges met projecten; `?range=zonwering` markeert die pil als actief en zet `hidden` op de niet-matchende `<li>`-elementen terwijl ze wél gerenderd blijven.
- `tests/Feature/Sections/RangeCardTest.php` en `ProjectCardTest.php` — de uitgetrokken partials, inclusief de `sizes`-default.

Aan te passen:

- `CatalogContentTest::test_six_projects_exist_and_reference_a_product` → `…_reference_a_range`, met augmentatie naar een `ranges`-entry.
- `RangesContentTest::test_every_range_category_relation_resolves_to_a_real_term` — de verwachte categorietitels staan er hardcoded in (`Buitenzonwering`, `Schrijnwerk`, `Comfort en techniek`) en volgen de nieuwe indeling uit 1.1 en 1.2.
- `RangesSectionTest` en `ProjectsSectionTest` — de sectie rendert de kaart nu via een partial; het projectcategorielabel komt uit `range`.
- `PageHeaderTest` — een test voor `divider="true"` en een die bevestigt dat de lijn zonder argument niet verschijnt.

De volle PHPUnit-suite loopt niet op een standaard `memory_limit` van 128 MB (`intervention/image` in de asset-compressietest, bekend en ouder dan dit werk). Draaien met `--filter` of een verhoogde `memory_limit`.

---

## Openstaande punten voor de klant

Niet blokkerend voor deze implementatie, wel te melden:

- **Range-detailpagina.** `/aanbod/{slug}` valt terug op `default` zolang `/aanbod/categorie` niet gebouwd is. De range-kaarten op `/aanbod` linken er wel al naartoe.
- **Beeld-alt-teksten.** Assets dragen geen alt-tekst, dus de kaartafbeeldingen renderen met een lege `alt` — hetzelfde punt als in de follow-up-notitie van de page builder.
- **Aantal projecten.** Figma toont er acht, de content heeft er zes. De grid vult zich met wat er is; er komt geen paginering.
- **Kaartteksten wijken af van Figma.** De `short_description` van meerdere ranges verschilt van wat de `/aanbod`-kaarten in `454:5356` tonen — bijvoorbeeld `pergolas`: content zegt "Terrasoverkappingen met draaibare of vaste lamellen, klaar voor zon, schaduw en regen", Figma zegt "Pergola's met draaibare lamellen, veranda's en carports — op maat gemaakt". Dezelfde tekst voedt ook de `ranges`-sectie in de page builder, dus er is één bron voor beide. Welke versie wint, is aan de klant.
