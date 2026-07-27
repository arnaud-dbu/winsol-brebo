# Servicepagina

**Datum:** 2026-07-27
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

De servicepagina (`/service`) opmaken volgens Figma: default header, een
ankerbalk die naar de secties springt, vier text-image-secties en onderaan een
opgemaakt herstellingsformulier.

Bron: Figma `dgMxUtoYzYrR5FRuwPzQBn`, pagina *Design v2*, frame **`318:2955`
(`/service`, desktop 1744)**.

> **Let op bij het narekenen.** De node-id in de oorspronkelijke briefing
> (`293:3516`) is `/aanbod/categorie`, niet deze pagina. Er is geen
> mobile-frame voor `/service`; het gedrag onder `lg` is in dit ontwerp
> afgeleid, niet overgetekend.

## Scope

In scope: de pagina-entry, het template, twee nieuwe partials, twee nieuwe
CSS-bestanden, het herschreven `form.css`, één blueprint-veld, het
formulier-blueprint, twee nieuwe argumenten op `textImage`, en de tests.

Buiten scope: het formulier laten wérken (verzending, e-mail, recaptcha,
succespad), drag-and-drop-feedback in JavaScript, een navigatie-item, en de
`.btn--pill`-refactor. Zie [Buiten scope](#buiten-scope) voor de motivering.

## Bestanden

```
content/collections/pages/service.md                        nieuw
resources/views/service.antlers.html                        nieuw
resources/views/partials/sectionNav.antlers.html            nieuw
resources/views/partials/sections/reparation.antlers.html   nieuw
resources/views/partials/sections/textImage.antlers.html    `anchor` + `text_first` erbij
resources/css/components/section-nav.css                    nieuw, import in site.css
resources/css/components/form.css                           herschreven
resources/css/base/global.css                               `scroll-behavior` erbij
resources/blueprints/collections/pages/services_overview.yaml  `image` op `reparation`
resources/blueprints/forms/herstelling.yaml                 nieuw
resources/forms/herstelling.yaml                            nieuw
tests/Feature/Sections/ServiceNavTest.php                   nieuw
tests/Feature/Sections/ReparationSectionTest.php            nieuw
tests/Feature/Sections/TextImageSectionTest.php             uitgebreid
tests/Feature/Content/ServicePageTest.php                   nieuw
```

## 1. Datamodel

`resources/blueprints/collections/pages/services_overview.yaml` bestaat al en
klopt: `page_intro`, een `services`-replicator (`overline` / `title` / `text` /
`image`) en een `reparation`-group (`overline` / `title` / `text`). Bewust
zónder `page_builder`.

**Eén wijziging:** `import: image` toevoegen aan de `reparation`-group, voor de
gereedschapskoffer. Dat wijkt één veld af van
`2026-07-24-winsol-brebo-content-structure-design.md`, dat de group op drie
velden vastlegt.

De `services`-replicator blijft de bron van de vier secties. Overwogen en
verworpen: het veld vervangen door `page_builder` met `text_image`-sets. Dat is
letterlijker hergebruik, maar de ankers zouden dan uit willekeurige
page-builder-sets moeten komen en het gaat rechtstreeks in tegen de "No
page_builder"-regel uit de contentstructuur-spec.

### Entry

`content/collections/pages/service.md`, blueprint `services_overview`, template
`service`, slug `service`. Copy uit Figma:

| Veld | Waarde |
|---|---|
| `title` | Service |
| `text` | Van eerste advies tot lang na de plaatsing — u kunt op ons rekenen voor de hele levensduur van uw installatie. |

Vier services, in deze volgorde:

| # | `overline` | `title` |
|---|---|---|
| 1 | Advies | Advies op maat |
| 2 | Installatie | Vakkundige installatie |
| 3 | Onderhoud | Onderhoud en nazicht |
| 4 | Garantie | Garantie en nazorg |

De bijbehorende teksten (Figma `318:3007`, `318:3028`, `318:3051`, `318:3072`):

1. Voor u iets beslist, denken we met u mee. We komen gratis langs, meten alles
   correct op en bekijken uw woning ter plaatse. Op basis daarvan stellen we de
   oplossing voor die past bij uw situatie, smaak en budget. U krijgt eerlijk
   advies en een heldere offerte, zonder verrassingen achteraf.
2. Uw installatie wordt geplaatst door ons eigen team — geen onderaannemers.
   Onze vakmensen kennen hun werk en behandelen uw woning met respect. We werken
   netjes en ruimen na de plaatsing alles op. Bij de oplevering tonen we hoe
   alles werkt, zodat u meteen zorgeloos kunt genieten.
3. Een goed onderhouden installatie gaat jaren langer mee. We komen op afspraak
   langs voor periodiek nazicht, afstelling en smering van de bewegende delen.
   Kleine herstellingen pakken we meteen mee, voor ze grote problemen worden. Zo
   blijft alles vlot en veilig werken, seizoen na seizoen. En zit u toch met
   iets, dan staan we snel ter plaatse.
4. Op elke installatie geldt zowel fabrieks- als plaatsingsgarantie. En ook ná
   de plaatsing blijven we gewoon bereikbaar. U heeft één vast aanspreekpunt in
   uw buurt, ook jaren later. Heeft u een vraag of een probleem, dan volgen we
   het vlot voor u op. Zo staat u nooit alleen met uw aankoop.

`reparation`: overline "Herstelling", title "Iets stuk of werkt iets niet
meer?", text "Voor bestaande klanten met een probleem. Beschrijf kort wat er
aan de hand is — we nemen contact op om langs te komen.", image
`quicklinks/herstelling.png`.

**Beelden.** De vier servicefoto's staan in Figma als lege
`winsol 2`-placeholders; er is geen echte asset. Koppel voorlopig
`dummy-images/test-img-*.jpg`, zoals `aanbod.md` al doet. `herstelling.png`
staat wél klaar, in de assets-container (R2) onder `quicklinks/`.

De vijf checklists in het design (`Frame 21`, nodes `318:3008`, `318:3029`,
`318:3052`, `318:3073`) staan op `hidden` en worden niet gebouwd. `textImage`
heeft er wel een `features`-argument voor, mocht dat later terugkomen.

## 2. Template

`resources/views/service.antlers.html`:

```
{{ partial:headers/default divider="true" }}
{{ partial:sectionNav }}
{{ services }}
    {{ partial:sections/textImage :anchor="overline | slugify" :text_first="index % 2 == 0" }}
{{ /services }}
{{ partial:sections/reparation }}
```

`divider="true"` op de bestaande header levert de lijn boven de balk. Die
divider is al `hidden lg:block`, wat samenvalt met de eis dat de balk pas vanaf
`lg` verschijnt: onder `lg` verdwijnen lijn en balk samen, niet los van elkaar.

Twee dingen in die aanroep moeten tijdens de implementatie bevestigd worden;
allebei zijn het uitvoeringsdetails, geen ontwerpkeuzes:

- **`index % 2 == 0`** — of Antlers' modulo-operator zo werkt. Alternatief:
  `index | mod:2`.
- **`overline | slugify`** — of een modifier in een `:`-parameter evalueert.
  Alternatief: de slug één regel eerder in een variabele zetten. Let op dat die
  variabele dan een naam krijgt die niet in de cascade botst; Antlers-toewijzingen
  schrijven in de gedeelde cascade, niet in een partial-lokale scope.

## 3. Ankerbalk — `partials/sectionNav.antlers.html`

Vaste component zonder argumenten, die zelf `{{ services }}` uit de cascade
leest. Zelfde patroon als `partials/quicklinks.antlers.html`, dat zijn eigen
collectie leest.

- `<nav aria-label="Op deze pagina" class="hidden lg:block">`, met daarin een
  `<ul>`.
- Per service mét overline één pill: label is de overline,
  `href="#{{ overline | slugify }}"`, plus een ↓-icoon
  (`{{ icon src="arrow-down" }}`). Een service zonder overline valt weg — dat is
  een expliciete `{{ if overline }}`-guard, geen toevalligheid, want zonder
  overline is er ook geen anker om naartoe te springen.
- Rechts uitgelijnd één donkere pill: moersleutel (`{{ icon src="wrench" }}`) +
  "Herstelling melden", `href="#herstelling"`.

Het label van die laatste knop staat hardgecodeerd in het partial, net zoals
`partials/quicklinks.antlers.html` zijn `<h2>` hardcodeert.

### Waarom een eigen naam en eigen CSS

**Niet `quicklinks`.** Die naam is bezet: `content/collections/quicklinks`,
`partials/quicklinks.antlers.html` en `quicklinks.css` zijn de drie
CTA-kaarten. Dit is anker-navigatie binnen één pagina, iets anders.

**Niet `.btn--outline`.** Die heeft `border-black`; de pills in het design
hebben een lichte rand die overeenkomt met de headerlijn (`border-black/12`).
Nieuwe `resources/css/components/section-nav.css` met `.section-nav__link` en
`.section-nav__link--report`, beide `rounded-full`.

Dit is daarmee de vijfde pill-vormige knop met dezelfde vormdeclaraties. De
follow-up om een `.btn--pill`-basis te extraheren staat open in
`2026-07-26-pagebuilder-sections-followups.md`; die raakt vier bestaande
secties en hoort in een eigen diff. Deze spec maakt die follow-up dringender,
maar voert hem niet uit.

## 4. `textImage` uitbreiden

Twee nieuwe optionele argumenten. Zonder beide rendert het partial
byte-identiek aan vandaag, zodat de acht bestaande aanroepers ongemoeid
blijven.

### `anchor`

Rendert `id="{{ anchor }}"` op de `<section>`.

Bewust níet `id` genoemd. Binnen een replicator-loop is `{{ id }}` de set-id die
Statamic zelf toekent; een argument met die naam zou daar stil door overschreven
worden. Dezelfde klasse van cascade-bug als beschreven in
`sectionHeader.antlers.html` en `CardLayoutCascadeTest`.

### `text_first`

Laat `order-last` weg van de tekstkolom, zodat de tekst links komt en het beeld
rechts.

Vandaag hangt die volgorde uitsluitend aan de `background`-toggle: met
achtergrond staat de tekst links in een `bg-light`-kaart, zonder achtergrond
krijgt de tekstkolom `order-last` en staat het beeld links. Het design wisselt
af zónder achtergrondvlak, dus de twee moeten ontkoppeld worden. De nieuwe
conditie is `background || text_first`.

Resultaat op de pagina: sectie 1 en 3 beeld links, 2 en 4 tekst links — precies
het design, zonder dat de redacteur per sectie een toggle moet zetten.

## 5. Vloeiend scrollen

`scroll-behavior: smooth` op `html` in `base/global.css`, uitgezet onder
`@media (prefers-reduced-motion: reduce)`. Plus `scroll-margin-top` op de
ankersecties, zodat het anker niet tegen de bovenrand plakt.

Geen JavaScript. De balk is gewone `<a href="#…">`, dus de sprong werkt ook
zonder JS; alleen de animatie valt dan weg. De sitenavigatie is niet sticky
(`partials/navigation.antlers.html` staat in de normale flow), dus er hoeft geen
headerhoogte gecompenseerd te worden.

## 6. Herstellingssectie — `partials/sections/reparation.antlers.html`

Volle `bg-light`-band, `id="herstelling"` — het doelwit van de donkere pill.

Achtergrond: de bestaande `{{ svg src="watermark" }}` (dezelfde die
`headers/range.antlers.html` gebruikt), in een
`pointer-events-none absolute inset-0 -z-10 overflow-hidden`-wrapper met
`aria-hidden`.

Twee kolommen vanaf `lg`, daaronder gestapeld:

- **Links:** overline, `<h2>`, tekst, en daaronder het koffer-beeld uit het
  nieuwe `image`-veld. In Figma steekt dat beeld links buiten het raster
  (`361:4112`, `x: -97`). Dat wordt een negatieve marge die pas vanaf `lg`
  aanslaat, zodat er onder `lg` geen horizontale overflow kan ontstaan.
- **Rechts:** een `bg-white rounded-md`-kaart met ruime padding, met daarin het
  formulier.

## 7. Formulier

### `resources/blueprints/forms/herstelling.yaml`

Eén tab, **twee secties**. Statamic's form-tag exposeert `{{ sections }}` met
`display`, `instructions` en `fields`
(`vendor/statamic/cms/src/Forms/Tags.php:294`), dus de scheidingslijn uit het
design volgt uit de blueprintstructuur en hoeft niet met een marker in het
template gezocht te worden.

| Sectie | Handle | Type | `width` | Label | Placeholder |
|---|---|---|---|---|---|
| Probleem | `product` | text | 50 | Welk product gaat het over? | bv. Pergola SO!, rolluik, raam… |
| | `installed` | text | 50 | Ongeveer wanneer geplaatst? | bv. 2021 |
| | `problem` | textarea | 100 | Wat is er aan de hand? | Beschrijf het probleem zo concreet mogelijk. |
| | `branch` | select | 100 | Naar welk filiaal? | Kies een filiaal… |
| | `photo` | assets | 100 | Foto van het probleem (optioneel) | Sleep een foto hierheen of klik om te uploaden |
| Contact | `email` | text (`input_type: email`) | 100 | E-mail | naam@voorbeeld.be |
| | `name` | text | 50 | Naam | Voor- en achternaam |
| | `phone` | text | 50 | Telefoon | +32 … |

Geen `validate`-regels: het formulier hoeft nog niet te werken, en verplichte
velden zonder verwerking leveren alleen een dood foutpad op.

### `resources/forms/herstelling.yaml`

Titel "Herstelling", geen `email:`-blok.

### Rendering

Generiek, in het `reparation`-partial:

```
{{ form:herstelling }}
    {{ sections }}
        <div class="form-section">
            {{ fields }} … {{ /fields }}
        </div>
    {{ /sections }}
{{ /form:herstelling }}
```

Per veld een `.form-field`, met `.form-field--half` bij `width == 50`. Die
klasse wordt met een expliciete `{{ if }}` gekozen en nooit geïnterpoleerd:
Tailwind's scanner vindt runtime-samengestelde klassenamen niet. In dit project
staat dezelfde waarschuwing al in `quicklinks.antlers.html`,
`sectionHeader.antlers.html` en `overline.antlers.html`.

`{{ width }}` is beschikbaar in de veldloop — `Field::toArray()` zet hem
expliciet, met `100` als default
(`vendor/statamic/cms/src/Fields/Field.php:377`).

**Eén uitzondering: `branch`.** Dat veld wordt niet door `{{ field }}`
gerenderd maar door een handgeschreven `<select>` met een
`{{ collection:locations }}`-loop, zodat de drie filialen uit één bron komen.
Een Entries-veld kan dit niet: alleen Select, Radio, Checkboxes en ButtonGroup
geven opties door aan de frontend
(`vendor/statamic/cms/src/Fieldtypes/HasSelectOptions.php:203`).

**Foto-upload.** Een `<input type="file">` met `opacity-0`, absoluut over de
hele streepjeszone, met het upload-icoon en de tekst eronder. Browsers
accepteren drag-and-drop rechtstreeks op een file-input, dus slepen én klikken
werken zonder JavaScript. Wat zo niet werkt: een highlight tijdens het slepen en
de bestandsnaam na de keuze. Zie [Buiten scope](#buiten-scope).

**Verzendknop:** `.btn .btn--accent`, label "Herstelling melden". Die klasse
matcht het design exact (`#f8d71c`, `rounded-full`).

## 8. `form.css` herschrijven

Het huidige bestand is drie regels ongestileerde standaard op de `form`-tag.
Vervangen door herbruikbare klassen in plaats van elementselectors, zodat het
contactformulier er later van kan profiteren zonder dat dít ontwerp dat template
aanraakt.

| Klasse | Rol |
|---|---|
| `.form` | verticale ritmiek |
| `.form-section` | groep velden |
| `.form-section + .form-section` | de scheidingslijn, `border-black/10` |
| `.form-grid` | de tweekolomsrij |
| `.form-field` / `.form-field--half` | veldbreedte |
| `.form-label` | vet label boven het veld |
| `.form-control` | de invoervulling: `#f5f5f5`, `rounded-md`, geen rand |
| `.form-control--textarea` | hogere variant |
| `.form-select` | idem plus caret |
| `.form-dropzone` | streepjesrand `#bfbfbf`, transparante vulling |
| `.form-error` | foutmelding onder het veld |

Kleuren gemeten uit het Figma-render van node `318:3097`: kaart `#ffffff`,
invoervulling `#f5f5f5`, streepjesrand `#bfbfbf`, knop `#f8d71c`. De
invoervulling is een neutraal grijs en nadrukkelijk **niet** `--color-light`
(`#f1f6f8`), dat blauw zweemt op wit.

## 9. Tests

Volgens de bestaande conventie (`tests/Feature/Sections/`,
`tests/Feature/Content/`, met `SectionTestCase` als harnas).

**`ServiceNavTest`**
- de pills volgen de overlines, in volgorde
- elke `href` matcht de `id` van de bijbehorende sectie
- een service zónder overline levert geen pill op
- de balk draagt de `lg`-gating
- de herstellingsknop wijst naar `#herstelling`

**`ReparationSectionTest`**
- beide formuliersecties renderen, met de scheidingsklasse ertussen
- `branch` bevat de drie filialen uit `locations`
- `.form-field--half` staat op precies vier velden
- de sectie draagt `id="herstelling"`
- de sectie rendert zonder `image` (guard rond de koffer)

**`TextImageSectionTest`** uitbreiden
- `text_first` wisselt de kolomvolgorde
- `anchor` rendert het `id`
- zónder de nieuwe argumenten is de uitvoer ongewijzigd — dit is de test die
  bewijst dat de acht bestaande aanroepers niet raken

**`ServicePageTest`**
- de entry hangt aan blueprint `services_overview` en template `service`
- de `reparation`-group draagt het nieuwe `image`-veld

## Buiten scope

**Het formulier verwerkt niets.** Zonder `email:`-blok verstuurt het niets,
maar Statamic zou een POST wél als inzending wegschrijven. Dit is het enige punt
waar "moet nog niet werken" schuurt met "mag al in Statamic opgemaakt worden".
Het formulier mag niet live gaan voor de verwerking geregeld is, anders slokt het
inzendingen in stilte op. Dit hoort in de follow-ups van de implementatie.

**Geen recaptcha**, geen succes- of foutafwerking voorbij `.form-error`.

**Geen drag-and-drop-feedback.** Highlight tijdens het slepen en het tonen van
de gekozen bestandsnaam vergen JavaScript.

**Geen navigatie-item.** `content/trees/navigation/main.yaml` bevat vandaag
alleen *Over ons*, *Projecten* en *Contact*; Aanbod en Realisaties ontbreken er
ook. Alleen Service toevoegen maakt een verouderde nav inconsistenter, niet
beter. De pagina is bereikbaar op `/service`; de nav opschonen is eigen werk.

**Geen `.btn--pill`-refactor.** Zie sectie 3.

**Geen mobile-ontwerp uit Figma.** Dat frame bestaat niet. Het gedrag onder
`lg` volgt uit de bestaande responsieve utilities (`section-x-gap` stapelt onder
`sm`) en uit de expliciete keuze om de ankerbalk te verbergen.
