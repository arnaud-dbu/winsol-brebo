# Locations- en quicklinks-component

**Datum:** 2026-07-26
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

Twee vaste componenten die op templates geïncludeerd worden met
`{{ partial:locations }}` en `{{ partial:quicklinks }}`. Beide zijn
zelfstandig: ze nemen geen argumenten en lezen hun eigen collectie, zodat
een include overal identiek is.

Bronnen: Figma `dgMxUtoYzYrR5FRuwPzQBn`, node `293:3935` (locations,
desktop 1744) en de design-screenshots van beide componenten.

## Scope

In scope: de twee partials, hun CSS en JS, de blueprint-uitbreiding, de
entries in beide collecties, en de tests.

Buiten scope: de partials daadwerkelijk includen op een paginatemplate.
Dat gebeurt later, handmatig. Er wijzigt in deze opdracht dus geen enkele
paginatemplate.

## Bestanden

```
resources/views/partials/locations.antlers.html          nieuw
resources/views/partials/quicklinks.antlers.html         nieuw
resources/css/components/locations.css                   nieuw, import in site.css
resources/css/components/quicklinks.css                  nieuw, import in site.css
resources/css/components/button.css                      `.btn--outline` erbij
resources/js/components/locations-map.js                 nieuw, import in site.js
resources/blueprints/collections/locations/locations.yaml `latitude` + `longitude` erbij
resources/blueprints/collections/pages/contact.yaml       `quicklinks`-veld verwijderd
content/collections/locations/*.md                        drie entries
content/collections/quicklinks/*.md                       drie entries
tests/Feature/Sections/LocationsTest.php                  nieuw
tests/Feature/Sections/QuicklinksTest.php                 nieuw
package.json                                              `leaflet` als dependency
```

## Leaflet laden

`locations-map.js` volgt het patroon van `sliders.js`: het zoekt
`[data-locations-map]` en doet niets als dat element ontbreekt. Leaflet
zelf komt binnen via een **dynamische** `import('leaflet')` die pas
afgevuurd wordt wanneer een IntersectionObserver de kaartcontainer in
beeld ziet komen. Vite splitst Leaflet en `leaflet.css` daardoor in een
eigen chunk.

Dat is de reden voor de dynamische import: een statische import bovenaan
`site.js` zou ~45 kB gzip toevoegen aan élke pagina van de site, ook de
pagina's zonder kaart. Een Alpine-component (`Alpine.data`) is overwogen
en afgewezen — Leaflet is imperatief, Alpine voegt hier niets toe en
verstopt de laadstrategie.

## Data-overdracht naar de JS

Elke locatiekaart is al de hover-trigger, dus de coördinaten hangen aan
het kaartje zelf: `data-location-lat` en `data-location-lng` op de `<a>`.
De JS leest ze daar. Er komt géén losse `data-locations='[…]'`-blob op de
container, want die kan uit sync raken met de gerenderde lijst.

De pin bestaat maar één keer, als `resources/svg/map-pin.svg`. De partial
rendert hem in een `<template data-map-pin hidden>{{ svg src="map-pin" }}</template>`;
de JS gebruikt die `innerHTML` als `L.divIcon` (`iconSize [25, 33]`,
`iconAnchor [12.5, 33]`). Zo staat de path-data niet ook nog eens als
string in JavaScript.

## Locations-component

### Markup

```
<section class="section section--default" data-section="locations">
  <div class="container">
    <div class="section-y-gap">
      {{ partial:sectionHeader }}  — is_centered, overline "Bezoek ons",
                                     title "Liever eerst zien en voelen?"
      <div>  grid, lg:grid-cols-2, gap 32
        <ul>   drie <li> met elk één <a href="/contact"> kaartje
        <div data-locations-map aria-hidden="true">
      <template data-map-pin hidden>
```

Maten uit Figma `293:3935` (frame 1744 breed): container-marge 40, twee
kolommen van 816 met 32 gap, kaartjes 816 × 147 met 40 padding rondom,
ronde pijlknop ~35 px rechts uitgelijnd, map 816 × 506.

De teksten "Bezoek ons" en "Liever eerst zien en voelen?" staan hardcoded
in de partial en worden als argumenten aan `{{ partial:sectionHeader }}`
meegegeven. Dat is bewust geen eigen `<h2>` met een eigen overline: de
bestaande sectionHeader kent de overline- en centreerlogica al, en die
nabouwen zou een tweede bron van waarheid maken.

De kaartjes zijn zelf de link (`<a href="/contact">`), dus de pijlknop is
een `<span>` — een `<button>` of tweede `<a>` erin zou een link in een
link zijn. Het adres wordt in de partial samengesteld uit de vier losse
velden tot `{street} {number}, {postal_code} {city}`.

De pijl in het design wijst diagonaal naar rechtsboven (Figma "Arrow 2",
13,7 × 13,7 — een vierkant, dus 45°). De bestaande `resources/svg/arrow.svg`
wijst horizontaal. Hergebruik hem met een `-rotate-45` op de `<span>`
in plaats van een tweede pijl-SVG toe te voegen: één bron, en de pijl
blijft meebewegen als de huisstijl hem ooit vervangt.

De map-container staat **ná** de lijst in de DOM. Onder `lg` stapelt het
grid daardoor vanzelf naar kaartjes-boven-kaart, zonder `order`-omkering.
Dat is de gekozen mobiele volgorde: de drie `/contact`-links staan meteen
in beeld en de kaart is illustratie eronder.

### Kaartgedrag

**Niet-interactief.** `dragging`, `scrollWheelZoom`, `doubleClickZoom`,
`touchZoom`, `keyboard` en `zoomControl` staan allemaal uit. Het design
toont geen zoomknoppen en de kaart is illustratie; hover-zoom is de enige
beweging. Dat voorkomt meteen dat de pagina niet meer scrollt zodra de
muis boven de kaart hangt.

**Startbeeld:** `fitBounds` over de pins met padding, niet het centrum
`50.8394, 4.4469` zoom 10 uit Figma. Zo blijft het beeld kloppen als er
een vestiging bijkomt of verhuist.

**Hover-zoom**, alleen achter `matchMedia('(hover: hover) and (pointer: fine)')`
— op touch gebeurt er niets:

- `mouseenter` of `focusin` op een kaartje → `flyTo([lat, lng], 13)`
- `mouseleave` of `focusout` → terug naar `flyToBounds` over alle pins

`focusin`/`focusout` staan er expliciet bij zodat tab-navigatie hetzelfde
doet als de muis.

**`prefers-reduced-motion: reduce`** → `setView` en `fitBounds` in plaats
van `flyTo` en `flyToBounds`: springen in plaats van animeren.

**Tiles:** CARTO Voyager,
`https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png`
met `subdomains: 'abcd'` en de verplichte attributie
`© OpenStreetMap contributors © CARTO`, die Leaflet zelf rechtsonder
rendert. Voyager is gekozen na een visuele vergelijking met het design
tegen OpenStreetMap-standaard, CARTO Positron en OpenTopoMap.

### Failure modes

- **Locatie zonder coördinaten:** geen pin, wél zijn kaartje in de lijst,
  en geen `data-location-lat`/`-lng` op dat kaartje.
- **Geen enkele locatie met coördinaten:** de JS slaat de kaart over. De
  drie `/contact`-links blijven werken.
- **Tegelserver traag of onbereikbaar:** de kaartcontainer heeft `bg-light`
  en een vaste aspect-ratio (816/506 vanaf `lg`), dus er is geen layout
  shift en bij uitval staat er een neutraal vlak in plaats van een kapot
  blok.

## Quicklinks-component

### Markup

```
<section class="section section--default" data-section="quicklinks">
  <div class="container">
    <div class="section-y-gap">
      <h2> "Zet de volgende stap"   — hardcoded, gecentreerd
      <ul> grid, gap 32, lg:grid-cols-3
        {{ collection:quicklinks }} → kaart per entry
```

Hier staat wél een kale `<h2>` en niet `{{ partial:sectionHeader }}`: het
design toont geen overline, geen tekst en geen link boven dit blok, alleen
de titel. De sectionHeader aanroepen met één argument zou drie ongebruikte
takken meeslepen voor niets.

Per kaart: `bg-light`, `rounded-md`, de bestaande `card-padding`-utility,
beeld bovenin via `{{ img :src="image" }}` met `object-contain` (het zijn
uitgeknipte producten op transparant, geen bleed-foto's), dan `<h3>`
`title`, `<p>` `text`, en de knop.

De knop gaat door `{{ partial:link }}`, zodat entry/url/mail/phone alle
vier blijven werken. `link_style` mapt op de klasse:

| `link_style` | klasse | status |
| --- | --- | --- |
| `primary` | `btn btn--accent` | bestaat al — gele pill, `rounded-full px-8 py-5` |
| `outline` | `btn btn--outline` | **nieuw** — zelfde vorm en padding, `border border-black bg-transparent text-black` |

Die mapping is een expliciete `if/else` die twee volledig uitgeschreven
klassenstrings oplevert, nooit stringinterpolatie: Tailwind's scanner
vindt runtime-samengestelde klassenamen niet. Dezelfde valkuil staat al
gedocumenteerd in `sectionHeader.antlers.html` en `gridCta.antlers.html`.

### Bron van de entries

De component leest altijd `{{ collection:quicklinks }}` — alle entries,
in collectie-volgorde, overal identiek. Daarmee wordt het bestaande
`quicklinks`-veld op de contact-blueprint (`entries` → `quicklinks`)
overbodig; dat veld verdwijnt, zodat er geen dood veld in de CP blijft
staan dat niets meer doet.

### Bewuste keuzes

**Geen slider.** `sections/cards` en `sections/ranges` gebruiken swiper,
maar dat zijn open lijsten van wisselende lengte. Hier zijn het er altijd
drie, en het zijn de belangrijkste CTA's van de pagina — die horen alle
drie in beeld te staan, niet twee ervan achter een swipe. Onder `lg`
stapelen ze.

**Geen limiet op het aantal entries.** Bij een vierde entry loopt het
grid door op een tweede rij. Het blueprint dwingt geen maximum af, en een
vierde entry stilletjes verbergen is erger dan hem tonen.

## Statamic-data

### Blueprint-uitbreiding

In `resources/blueprints/collections/locations/locations.yaml`, naast de
adresvelden:

```yaml
- handle: latitude
  field: { type: float, display: Latitude,  instructions: 'Uit Google Maps, bv. 50.8571' }
- handle: longitude
  field: { type: float, display: Longitude, instructions: 'Uit Google Maps, bv. 4.2596' }
```

Bewust **niet** `required`: een locatie zonder coördinaten hoort in de
lijst te blijven staan (zie Failure modes), en `required` zou het opslaan
blokkeren op precies het moment dat een redacteur een nieuwe vestiging
aanmaakt. Geocoderen via een externe API is overwogen en afgewezen —
externe afhankelijkheid en rate limits voor drie zelden wijzigende
adressen.

### Entries

Beide collecties zijn nu leeg. Aanmaken via de Statamic MCP, in de
volgorde van het design.

`locations`:

| name | street | number | postal_code | city | latitude | longitude |
| --- | --- | --- | --- | --- | --- | --- |
| Winsol Dilbeek | Ninoofsesteenweg | 000 | 1700 | Dilbeek | 50.8500 | 4.2600 |
| Winsol Sint-Pieters-Leeuw | Bergensesteenweg | 000 | 1600 | Sint-Pieters-Leeuw | 50.7800 | 4.2400 |
| Winsol Aartselaar | Antwerpsesteenweg | 000 | 2630 | Aartselaar | 51.1300 | 4.3800 |

De huisnummers staan als `000` in het design zelf — dat zijn placeholders
en die nemen we letterlijk over. Een verzonnen huisnummer zou een echt
gebouw kunnen aanwijzen; `000` is onmiskenbaar nog in te vullen. Zie Open
punten.

`quicklinks`:

| title | text | knoplabel | link_style |
| --- | --- | --- | --- |
| Vraag offerte aan | Met Pergola SO! voorinvuld. Vrijblijvend en op maat. | Vraag offerte aan | `primary` |
| Vraag brochure aan | Ontvang de volledige brochure met opties en kleuren in uw bus of mailbox. | Brochure aanvragen | `outline` |
| Bezoek een showroom | Met Pergola SO! voorinvuld. Vrijblijvend en op maat. | Plan een bezoek | `outline` |

Teksten en labels letterlijk uit het design-screenshot. De entries worden
zonder `image` aangemaakt; de foto's (`offerte-1`/`offerte-2`, `brochure`,
`winkel`) worden in het CMS gekoppeld zodra het assets-pad bekend is. Zie
Open punten.

## Tests

De partials lezen hun collectie zelf en kunnen dus niet via de
`$context`-array van `SectionTestCase::render()` gevoed worden. Maar die
helper draait tegen dezelfde content-directory als de site, dus de
aangemaakte entries zíjn de fixtures — precies zoals `RangeOverviewPageTest`
al tegen de echte ranges assert.

`tests/Feature/Sections/LocationsTest.php` rendert `{{ partial:locations }}`
en controleert:

- drie kaartjes, alle drie met `href="/contact"`
- de hardcoded kop: "Bezoek ons" en "Liever eerst zien en voelen?"
- het samengestelde adres als één string, bv. `Ninoofsesteenweg 000, 1700 Dilbeek`
- `data-location-lat` en `data-location-lng` op elk kaartje — dat is het
  contract met de JS en de enige reden dat de coördinaten in de HTML staan
- de map-container staat ná de lijst in de DOM en is `aria-hidden`
- `<template data-map-pin>` bevat de path-data uit `map-pin.svg`
- een locatie zonder coördinaten levert wél een kaartje maar géén
  `data-location-lat` op

`tests/Feature/Sections/QuicklinksTest.php` rendert `{{ partial:quicklinks }}`
en controleert:

- drie kaarten en de titel "Zet de volgende stap"
- precies één `btn--accent` en twee `btn--outline` — de `link_style`-mapping
  is de enige vertakking in de partial, dus dat is wat vastgepind hoort te
  worden (zelfde vorm als `GridCtaSectionTest`)
- de knoplabels en de teksten uit het design

**Niet getest:** het Leaflet-gedrag zelf. Er is geen JS-testopstelling in
dit project (geen vitest, geen Playwright), en er één optuigen voor twee
event-listeners kost meer onderhoud dan het oplevert. Hover-zoom, tegels
en `prefers-reduced-motion` worden handmatig in de browser gecontroleerd.
Dit is een bewuste keuze, geen gat.

## Open punten

Geen van beide blokkeert de implementatie.

1. **Echte huisnummers** voor de drie vestigingen. Tot die er zijn staat
   `000` in de entries, zoals in het design.
2. **Assets-pad** van de quicklink-foto's (`offerte-1`/`offerte-2`,
   `brochure`, `winkel`). Niet teruggevonden in de root van de
   `assets`-container. Tot dan worden de beelden in het CMS gekoppeld.
3. **Figma-node van de quicklink-component** (component "quicklink" in
   design v2, niet gevonden in file `dgMxUtoYzYrR5FRuwPzQBn`). Op het
   screenshot lijkt de productfoto net over de bovenrand van het lichte
   vlak te steken. Zonder die node wordt het beeld bínnen de kaart
   gebouwd; met de node kan de exacte overlap alsnog nagebouwd worden.
