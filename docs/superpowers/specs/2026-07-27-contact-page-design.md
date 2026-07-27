# Contactpagina

**Datum:** 2026-07-27
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

De contactpagina wordt opgebouwd uit de default header, één licht paneel met
de drie vestigingen en een contactbalk, twee quicklinks uit een nieuw
paginaveld, en een CTA uit de page builder.

Bron: Figma `dgMxUtoYzYrR5FRuwPzQBn`, node **`318:3481`** (`/contact`,
desktop 1744). De node-id in de opdracht (`293:3516`) wees naar
`/aanbod/categorie`; dat frame is niet gebruikt. Er is geen mobile-frame van
`/contact` in het bestand — het responsieve gedrag hieronder is afgeleid,
niet overgenomen.

## Scope

In scope: het contact-template, drie nieuwe partials, hun CSS, de
blueprint-uitbreiding, de content op de contactpagina, de openingsuren op de
locaties, de contactgegevens in de globals, en de tests.

Buiten scope: de footer-inhoud, en de `locations`-component met de
Leaflet-kaart — die staat niet op dit design. Het contactformulier verdwijnt
wél van de pagina, maar de formulier- en recaptcha-bestanden zelf blijven
ongemoeid; zie Het template.

## Het template

`resources/views/contact.antlers.html` wordt volledig herschreven:

```
{{ partial:headers/default }}
{{ partial:contactDetails }}
{{ partial:pageQuicklinks }}
{{ partial:pageBuilder }}
```

Het bestaande `{{ form:contact }}`-blok, de `{{ recaptcha }}` en de
hardcoded rij met mail/telefoon/adres verdwijnen uit het template: het
design toont op `/contact` geen formulier. `resources/forms/contact.yaml` en
`resources/views/partials/recaptcha.antlers.html` blijven wél op schijf
staan — er komt later een `/offerte`- en een herstelformulier-pagina, en die
hebben ze nodig.

De header krijgt geen `divider`-argument. In Figma is de header-instantie op
`/contact` 360px hoog, dus zonder lijn — precies de default van
`headers/default`.

## Bestanden

```
resources/views/contact.antlers.html                      herschreven
resources/views/partials/contactDetails.antlers.html      nieuw
resources/views/partials/pageQuicklinks.antlers.html      nieuw
resources/views/partials/quicklinkCard.antlers.html       nieuw, gedeeld
resources/views/partials/quicklinks.antlers.html          gebruikt quicklinkCard
resources/css/components/contact-details.css              nieuw, import in site.css
resources/css/components/quicklinks.css                   overhang + gridafstanden
resources/css/site.css                                    --color-whatsapp in @theme
resources/blueprints/collections/pages/contact.yaml        quicklinks-veld erbij
content/collections/pages/contact.md                       blueprint, text, quicklinks, CTA
content/collections/locations/*.md                         opening_hours gevuld
content/collections/quicklinks/*.md                        image gekoppeld
content/globals/globals.yaml                               contactgegevens
content/globals/default/globals.yaml                       contactgegevens
tests/Feature/Sections/ContactDetailsTest.php              nieuw
tests/Feature/Sections/PageQuicklinksTest.php              nieuw
tests/Feature/Sections/QuicklinksTest.php                  bijgesteld
tests/Feature/Sections/FooterTest.php                      bijgesteld
```

## Twee correcties op de bestaande content

**De entry gebruikt het verkeerde blueprint.** `content/collections/pages/contact.md`
staat op `blueprint: page`, terwijl `resources/blueprints/collections/pages/contact.yaml`
al bestaat en nergens gebruikt wordt. Een `quicklinks`-veld in `contact.yaml`
zou dus niets doen. De entry gaat naar `blueprint: contact`.

**De entry heeft een wees-veld.** Er staat een sleutel `intro:` met
lorem-tekst in de front matter die in geen enkel blueprint voorkomt en dus
nergens rendert. Die wordt `text:` — het handle dat `page_intro` definieert
en dat `headers/default` uitleest — met de copy uit het design:

> Een korte vraag? Bel of mail rechtstreeks het filiaal in uw buurt — u
> krijgt meteen iemand die uw situatie kent.

## contactDetails-component

### Markup

```
<section class="section section--default" data-section="contact_details">
  <div class="container">
    <div class="contact-panel">        relative isolate overflow-hidden rounded-md bg-light
      {{ svg src="shape" }}            -z-10, decoratief, aria-hidden

      <ul>                             grid, lg:grid-cols-3, gap 32
        {{ collection:locations }} → wit kaartje per vestiging

      <div class="contact-bar">        wit, rounded-md
        <ul>  WhatsApp · telefoon · e-mail
```

In Figma zijn de adressen en de contactbalk **niet** twee losse containers:
ze zitten samen in één licht paneel (`Frame 223`, node `318:3510`) met de
`shape`-blob erachter. De opdracht beschreef ze als twee containers; het
design is hier leidend, omdat de gedeelde achtergrond en de blob anders niet
kloppen.

De blob steekt ver buiten het paneel (1101 × 590 op `x = -277` binnen een
paneel van 1664). `overflow-hidden` op het paneel klipt hem aan de eigen
rand, zoals `gridCta` het al doet — zo groeit het paneel niet en kan de blob
`document.documentElement` niet verbreden. Zijn tint is wit op lage opacity
(`text-white/50` als startwaarde) en wordt visueel bijgesteld tegen het
design.

`resources/css/components/contact-details.css` bevat `.contact-panel` en
`.contact-bar` plus de positionering van de blob — dezelfde verdeling als
`locations.css`: alles wat een arbitrary value of een specificiteitsregel
nodig heeft staat in CSS, de rest blijft Tailwind-klassen in de partial.

### Maten

Frame 1744 breed. Paneel op containerbreedte (marge 40), binnenpadding 64
horizontaal en 80 verticaal. Kaartjes 490,67 breed met 32 gap. De contactbalk
is 89 hoog en staat 32 onder de kaartjesrij.

Het kaartje heeft **asymmetrische** padding: 32 horizontaal, 56 verticaal.
Daarom niet de bestaande `card-padding`-utility (`p-6 lg:p-8`), die is
symmetrisch — de utility oprekken met een tweede padding-as zou hem voor de
vijf bestaande gebruikers veranderen.

Onder `lg` stapelt alles naar één kolom en zakt het paneelpadding terug naar
`p-6`.

### Het locatiekaartje

`<h3>` met `name`, dan `<p>` met het adres samengesteld tot
`{street} {number}, {postal_code} {city}` — dezelfde samenstelling die
`locationCard` al doet — en dan de openingsuren als `<dl>`:

```html
<dl>
  <div class="flex justify-between"><dt>Di - Vr</dt><dd>10:30 - 17:30</dd></div>
  ...
</dl>
```

Een dag→tijd-paar ís een beschrijvingslijst. Twee losse `<span>`s zouden de
koppeling weggooien en een `<table>` zou een tabelstructuur suggereren die er
niet is.

`partials/locationCard` wordt hier bewust **niet** hergebruikt: dat kaartje
is zelf een `<a href="/contact">`, heeft een ronde pijlknop en toont geen
openingsuren. Dit kaartje linkt nergens heen en toont wel uren. Eén partial
met een `variant`-schakelaar zou twee ongerelateerde ontwerpen in één bestand
persen.

### De contactbalk

Drie items, gecentreerd op één rij met 112 gap op desktop, gestapeld onder
`sm`. Elk item is een `<a>` met een rond icoonvlakje van 40px (icoon 24px) en
een label:

| | icoon | vlakje | label | href |
| --- | --- | --- | --- | --- |
| WhatsApp | `icons/fill/whatsapp-logo-fill` | `bg-whatsapp`, wit icoon | `Whatsapp` | `https://wa.me/<mobile, cijfers>` |
| Telefoon | `icons/fill/phone-fill` | `bg-accent`, zwart icoon | het nummer zelf | `tel:<phone, cijfers>` |
| Mail | `icons/fill/envelope-fill` | `bg-accent`, zwart icoon | het adres zelf | `mailto:<email>` |

Het WhatsApp-groen komt als `--color-whatsapp: #25D366` in het `@theme`-blok
van `site.css`, niet als arbitrary value in de partial: het is een merkkleur
die terugkomt zodra er elders een WhatsApp-knop opduikt.

Elk item zit achter zijn eigen `{{ if }}` op de bijbehorende global. Leeg
global = item weg; alle drie leeg = geen balk. Dat is exact het patroon dat
`footer.antlers.html` al gebruikt.

### Het wa.me-nummer

`wa.me` accepteert alleen cijfers in internationaal formaat: geen `+`, geen
spaties, geen voorloopnul. Daarom wordt `globals:contact:mobile` opgeslagen
als `+32 470 00 00 00`. Dat leest netjes als label én
`{{ globals:contact:mobile | regex_replace('/[^0-9]/', '') }}` levert
`32470000000`.

Een nationaal genoteerde `0470 …` zou een ongeldige `wa.me/0470000000`
opleveren, en de nul-naar-32-vertaling in de partial proppen zou hem
stilzwijgend Belgisch maken. De `tel:`-href op het vaste nummer gebruikt
dezelfde strip.

### Failure modes

- **Locatie zonder openingsuren:** naam en adres renderen, de `<dl>` niet.
- **Locatiecollectie leeg:** geen kaartjes, de contactbalk blijft staan.
- **Alle drie de globals leeg:** geen contactbalk, het paneel toont alleen de
  kaartjes.

## quicklinkCard — de gedeelde kaart

De markup die nu in `quicklinks.antlers.html` staat, verhuist naar
`partials/quicklinkCard.antlers.html`, plus de overhang uit het design.

Dit lost **open punt 3 uit `2026-07-26-locations-quicklinks-design.md`** op:
de quicklink-node was toen niet gevonden, dus het beeld werd binnen de kaart
gebouwd. De node bestaat wel — `465:1712` op `/contact`. Daarin is de foto
138 × 129 en staat hij op `y = -57` ten opzichte van de kaartrand: ruim 40%
van de hoogte steekt boven het lichte vlak uit, links uitgelijnd op het
kaartpadding.

De overhang wordt een negatieve `margin-top` op `.quicklink-media`, geen
absolute positionering: de foto blijft in de flow, de kaart houdt zijn eigen
hoogte, en er is geen px-offset die per breakpoint bijgesteld moet worden.
`.quicklink-media` blijft verder de uitlijningsbox (`flex h-24 items-end
lg:h-32`) die kaarten met verschillend hoge foto's op één lijn houdt.

Twee gevolgen, allebei opgelost in `quicklinks.css`:

- **Geen `overflow-hidden` op de kaart.** Dat zou de overhang wegknippen. Het
  staat er nu niet; het wordt als comment vastgelegd zodat het er niet
  ongemerkt bij komt.
- **De rij-afstand moet groter zijn dan de overhang.** Gestapeld onder `lg`
  zou de foto van kaart 2 anders over de onderrand van kaart 1 vallen. Dus
  `gap-x-6 gap-y-16 lg:gap-x-8 lg:gap-y-20` op het grid, plus een `pt` op de
  `<ul>` gelijk aan de overhang, zodat de eerste rij niet tegen de `<h2>`
  botst.

## De twee quicklink-secties

`quicklinks.antlers.html` (collectie, 3 kolommen) en
`pageQuicklinks.antlers.html` (paginaveld, 2 kolommen) worden allebei een
dunne wrapper om `quicklinkCard`. Ze delen de `<h2>` "Zet de volgende stap"
als hardcoded string in beide bestanden. Dat is een bewuste duplicatie: het
alternatief is een partial van vier woorden, of één partial die impliciet
tussen twee databronnen kiest — en op de contactpagina zouden dan allebei
die bronnen bestaan.

`pageQuicklinks` rendert niets als het `quicklinks`-veld leeg of afwezig is,
zodat andere templates hem gerust mogen includen.

## Statamic-data

### Blueprint-uitbreiding

In `resources/blueprints/collections/pages/contact.yaml`, tussen
`page_intro` en `page_builder`:

```yaml
- handle: quicklinks
  field:
    type: grid
    display: Quicklinks
    mode: stacked
    add_row: '+ Add quicklink'
    fields:
      - handle: title
        field: { type: text, display: Title, required: true, validate: [required] }
      - handle: text
        field: { type: textarea, display: Text }
      - import: image
      - import: link
      - handle: link_style
        field:
          type: select
          display: 'Link Style'
          default: primary
          options: { primary: Primary, outline: Outline }
```

Eén-op-één de velden van het `quicklinks`-collectieblueprint, zoals gevraagd.
`grid` en niet `replicator`: er is maar één settype, en een replicator met
één set is een keuzemenu zonder keuze. `gridCta` gebruikt om dezelfde reden
al `grid`.

### De twee items op contact.md

| title | text | knoplabel | link_style | image |
| --- | --- | --- | --- | --- |
| Vraag offerte aan | Met Pergola SO! voorinvuld. Vrijblijvend en op maat. | Vraag offerte aan | `primary` | `quicklinks/offerte-1.png` |
| Een herstelling melden | Al klant en werkt er iets niet? Meld het via het herstelformulier. | Naar herstelformulier | `outline` | `quicklinks/herstelling.png` |

De tweede kaart heet in de Figma-laagnamen nog "Bezoek een showroom" — dat is
een verouderde naam; de gerenderde tekst in het design is leidend.

Er bestaat nog geen `/offerte`- of herstelformulier-pagina, dus beide links
wijzen voorlopig naar de contact-entry zelf. Dat is hetzelfde
placeholder-patroon als de drie bestaande collectie-quicklinks. Zie Open
punten.

### De CTA

Als `cta`-set in `page_builder` op `contact.md`:

- overline `Realisaties`
- titel `Liever eerst even rondkijken?`
- tekst: *Geen zin om meteen contact op te nemen? Bekijk onze realisaties en
  ontdek wat we voor andere klanten in uw buurt hebben gemaakt.*
- link: entry `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03` (Realisaties), label
  `Naar realisaties`
- image: een `dummy-images/test-img-*.jpg`, zoals `page-builder.md` het al
  doet

### Openingsuren

Alle drie de locaties krijgen dezelfde uren, precies zoals in het design:

```yaml
opening_hours:
  - { day: 'Di - Vr',  time: '10:30 - 17:30' }
  - { day: Zaterdag,   time: '10:00 - 16:00' }
  - { day: 'Zo & Ma',  time: Gesloten }
```

### Globals

In `content/globals/globals.yaml` én `content/globals/default/globals.yaml`:

```yaml
contact:
  mobile: '+32 470 00 00 00'
  phone: '03 000 00 00'
  email: info@winsolbrebo.be
```

`phone` en `email` staan letterlijk zo in het design; `mobile` is een even
herkenbare placeholder in het formaat dat de wa.me-strip nodig heeft.

`FooterTest` documenteert dat deze drie bewust op `null` gezet zijn omdat de
starter kit er demo-gegevens van een vreemd bureau in had staan. Die reden
vervalt: dit worden Winsols eigen (placeholder-)gegevens. De docblock van die
test wordt daarop herschreven.

### Quicklink-foto's in de collectie

`quicklinks/offerte-1.png`, `quicklinks/brochures.png` en
`quicklinks/winkel.png` bestaan al in de assets-container. Ze worden aan de
drie collectie-entries gekoppeld, wat **open punt 2 uit
`2026-07-26-locations-quicklinks-design.md`** afsluit. Dat verandert niets
aan een gerenderde pagina — `{{ partial:quicklinks }}` staat nog op geen
enkel template — maar het maakt de overhang van de gedeelde kaart zichtbaar
in de handmatige controle.

## Tests

`tests/Feature/Sections/ContactDetailsTest.php` rendert
`{{ partial:contactDetails }}` en controleert:

- drie kaartjes, elk met `name` en het samengestelde adres als één string,
  bv. `Ninoofsesteenweg 000, 1700 Dilbeek`
- de drie dag/tijd-paren als `<dt>`/`<dd>`
- de drie balkitems met hun `wa.me`-, `tel:`- en `mailto:`-href
- het wa.me-nummer als `32470000000` — dat is de enige plek in de partial
  waar een waarde getransformeerd wordt, en dus het enige dat stil kan breken

`tests/Feature/Sections/PageQuicklinksTest.php` rendert
`{{ partial:pageQuicklinks }}` met een `quicklinks`-context en controleert:

- twee kaarten, met de titels en knoplabels uit het design
- precies één `btn--accent` en één `btn--outline` — de `link_style`-mapping is
  de enige vertakking, zelfde vorm als `GridCtaSectionTest`
- niets gerenderd bij een leeg veld

`tests/Feature/Sections/QuicklinksTest.php` blijft tegen de collectie
asserteren, maar wordt bijgesteld waar de markup naar `quicklinkCard`
verhuist. De teller `substr_count($html, 'quicklink-card')` blijft werken
zolang de nieuwe klassen geen BEM-kind van die naam zijn — dezelfde valkuil
die daar al gedocumenteerd staat.

`tests/Feature/Sections/FooterTest.php` gaat van 2 naar 3 `footer__column`s
en krijgt assertions op de `tel:`- en `mailto:`-links.

**Niet getest:** de overhang, de rij-afstanden en de `shape`-blob. Dat is CSS
zonder vertakking; die wordt handmatig in de browser gecontroleerd. Bewuste
keuze, geen gat.

## Open punten

Geen van beide blokkeert de implementatie.

1. **Echte contactgegevens.** `mobile`, `phone` en `email` staan als
   placeholders in de globals tot de echte waarden bekend zijn. Ze zijn in
   het CMS aanpasbaar zonder codewijziging.
2. **Bestemming van de twee quicklinks.** Er is nog geen `/offerte`- en geen
   herstelformulier-pagina. Beide knoppen wijzen tot dan naar `/contact`.
