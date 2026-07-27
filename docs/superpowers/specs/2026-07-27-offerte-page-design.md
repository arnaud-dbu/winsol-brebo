# Offertepagina

**Datum:** 2026-07-27
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

Een nieuwe pagina `/offerte` met een offerteformulier dat zijn producten uit
de ranges-collectie haalt en zijn filialen uit de locations-collectie. Onder
het formulier start de pagebuilder met één CTA.

Bron: Figma `dgMxUtoYzYrR5FRuwPzQBn`, node **`318:3956`** (`/offerte`,
desktop 1744×2550). Let op: de node-id in de oorspronkelijke briefing
(`293-3516`) wijst naar `/aanbod/categorie`, niet naar deze pagina. Er is
géén mobile-frame voor `/offerte` in het bestand; de layout onder `lg` is in
dit ontwerp bepaald en hieronder vastgelegd.

## Scope

In scope: de entry en zijn blueprint, de template, het form met zijn
blueprint en configuratie, twee eigen fieldtypes, de CSS, de Alpine-component
voor de upload, de CTA-inhoud, het omzetten van twee bestaande links naar
`/offerte`, en de tests.

Buiten scope, met redenen onderaan in "Open punten": reCAPTCHA repareren,
notificaties routeren per filiaal, en een product voorinvullen via de URL.

## Bestanden

```
content/collections/pages/offerte.md                      nieuw
resources/blueprints/collections/pages/offerte.yaml       nieuw
resources/views/offerte.antlers.html                      nieuw
resources/forms/offerte.yaml                              nieuw
resources/blueprints/forms/offerte.yaml                   nieuw
app/Fieldtypes/RangeCheckboxes.php                        nieuw
app/Fieldtypes/LocationSelect.php                         nieuw
app/Providers/AppServiceProvider.php                      beide fieldtypes registreren
resources/css/components/offerte-form.css                 nieuw
resources/css/site.css                                    import erbij
resources/js/components/offerte-upload.js                 nieuw
resources/js/site.js                                      Alpine.data-registratie erbij
content/collections/quicklinks/vraag-offerte-aan.md       link → offerte-entry
content/collections/pages/realisaties.md                  cta-link → offerte-entry
tests/Unit/Fieldtypes/RangeCheckboxesTest.php             nieuw
tests/Unit/Fieldtypes/LocationSelectTest.php              nieuw
tests/Feature/Content/OffertePageTest.php                 nieuw
tests/Feature/Content/OfferteFormBlueprintTest.php        nieuw
tests/Feature/Sections/OfferteFormTest.php                nieuw
```

Alles wat bij deze pagina hoort heet `offerte`, ook in de code: dezelfde naam
als de template, het form-handle en de entry. De enige uitzonderingen zijn de
twee fieldtypes, die naar hun bróncollectie genoemd zijn omdat ze buiten deze
pagina herbruikbaar zijn.

## Het formulier

### Velden

Engelse handles, zoals overal in dit project; Nederlandse labels, zoals in
Figma.

| handle | type | label | verplicht |
|---|---|---|---|
| `products` | `range_checkboxes` | Voor welke producten? | ja |
| `location` | `location_select` | Naar welk filiaal? | nee |
| `name` | text | Naam | ja |
| `phone` | text (`input_type: tel`) | Telefoon | nee |
| `email` | text (`input_type: email`) | E-mail | ja, `email` |
| `postal_code` | text | Postcode | nee |
| `project` | textarea | Vertel kort over uw project | nee |
| `attachment` | assets, container `private`, `max_files: 1` | Foto of plan toevoegen (optioneel) | nee |

`products` is verplicht met minstens één keuze; naam en e-mail zijn de
minimale set om te kunnen antwoorden. De rest is optioneel, zodat de drempel
laag blijft.

De handle `email` is niet vrij te kiezen: `resources/forms/offerte.yaml`
gebruikt hem in `reply_to: '{{ email }}'`. `postal_code` volgt bewust de
naamgeving van hetzelfde veld in de locations-blueprint.

### Uploads gaan naar de private container

`attachment` schrijft naar de bestaande `private`-container (disk `r2`), niet
naar `assets`. Klantfoto's en bouwplannen horen niet op een raadbare publieke
URL. De container bestaat al (`content/assets/private.yaml`) en vraagt geen
wijziging.

### Notificatie

`resources/forms/offerte.yaml` volgt `contact.yaml`: één `to`-adres, voorlopig
de placeholder `hello@stuw.agency` die daar ook staat, `reply_to: '{{ email }}'`
en een onderwerp met de naam van de aanvrager erin. Het gekozen filiaal staat
gewoon in de mailinhoud.

## De twee fieldtypes

Statamic-formblueprints zijn statische YAML. Om "de producten zijn verbonden
aan de ranges collectie" waar te maken zonder die lijst een tweede keer te
onderhouden, leveren twee eigen fieldtypes hun opties uit de collecties.

**`app/Fieldtypes/RangeCheckboxes.php`** erft van
`Statamic\Fieldtypes\Checkboxes` en overschrijft twee methodes:

- `getOptions()` levert de ranges als `slug => title`, gesorteerd op het
  bestaande `order`-veld. Dat veld is beschreven als "volgorde binnen de
  categorie", maar loopt in de praktijk uniek van 1 tot 9 over alle negen
  ranges, dus het werkt als globale volgorde. De volgorde in Figma
  (Zonwering, Ramen en deuren, Rolluiken, …) volgen we níét: die is
  willekeurige vulling en zou de lijst opnieuw hardcoderen.
- `rules()` voegt een `in:`-regel toe met diezelfde slugs, zodat een
  vervalste POST geen willekeurige tekst de notificatiemail in duwt.

**`app/Fieldtypes/LocationSelect.php`** doet hetzelfde op
`Statamic\Fieldtypes\Select`, met de drie locaties in hun structuurvolgorde en
`name` als label.

Beide worden geregistreerd in `AppServiceProvider::boot()`, naast de
bestaande `Sets::useIcons`-regel.

### Waarom één overschreven methode volstaat

`Statamic\Fieldtypes\HasSelectOptions` leidt alles af van `getOptions()`, en
die aanroepen lopen via `$this->`, dus een override in een subklasse wordt
gebruikt:

- `extraRenderableFieldData()` geeft de opties als `waarde => label` door aan
  de `{{ fields }}`-loop in Antlers;
- `getLabel()` wordt gebruikt door `preProcessIndex()` en `augment()`, waardoor
  de CP-submissielijst en de notificatiemail "Rolluiken" tonen in plaats van
  `rolluiken`.

De opgeslagen waarde is de slug, niet de entry-id: dat blijft leesbaar in de
mail en in een CSV-export, en overleeft het opnieuw aanmaken van een entry.

## De pagina

### Entry en blueprint

`content/collections/pages/offerte.md`, slug `offerte`, template `offerte`,
blueprint `offerte`.

`resources/blueprints/collections/pages/offerte.yaml` bevat `page_intro` (de
H1 en de intro links), een `image`-veld (het stilleven), `page_builder`, en de
gebruikelijke preview-, seo- en sidebar-tabs zoals `page.yaml`.

Het `image`-veld staat in de entry op `quicklinks/offerte-2.png` — de rolmaat
met het offerteblad, 976×976, bestaat in de `assets`-container.

### Template

`resources/views/offerte.antlers.html` gebruikt **geen**
`{{ partial:headers/default }}`. Die partial zet titel en tekst gecentreerd in
een smalle kolom; hier staan ze links naast het formulier. In plaats daarvan:
één `.section .section--default` met een `container`, daarin een grid, gevolgd
door `{{ partial:pageBuilder }}`.

Vanaf `lg`: twee kolommen, links de H1, de intro en het stilleven, rechts de
formkaart. De verhouding is 4/12 voor de linkerkolom en 7/12 voor de
formkaart, met de resterende kolom als tussenruimte — afgemeten op het
1744px-frame, waar de kaart ongeveer 55% van de containerbreedte inneemt.
Bij implementatie worden typografie en padding nog geverifieerd met
`get_design_context` op node `318:3956`; die kan de verhouding bijstellen maar
verandert de opzet niet.

Onder `lg`: één kolom in de volgorde H1 → intro → formkaart → stilleven. Het
beeld is decoratief en het formulier is het doel van de pagina, dus dat staat
eerst; het stilleven vult de ruimte boven de CTA.

### Het formulier wordt met de hand uitgeschreven

Niet via de generieke `{{ fields }}`-loop. Het ontwerp heeft pillen, een
tweekolomsraster voor naam/telefoon en e-mail/postcode, en een uploadvlak;
dat door één loop persen kost meer vertakkingen dan het uitschrijven zelf.
Herhaalde waarden en foutmeldingen komen uit `{{ old:handle }}` en
`{{ error:handle }}`, die Statamic binnen de `{{ form:offerte }}`-tag
aanbiedt.

Wat we daarmee opgeven, expliciet: een veld toevoegen vraagt zowel een
blueprint- als een templatewijziging. Dat is aanvaard omdat dit formulier een
vaste, ontworpen vorm heeft en geen open lijst is.

### Succesbeeld

Na een geslaagde verzending vervangt een bevestigingsblok de formulierinhoud
binnen dezelfde kaart. Geen extra route en geen extra entry; de bezoeker
blijft in dezelfde context. Dit gebruikt Statamic's `{{ if success }}`, zoals
`contact.antlers.html` al doet.

De tekst, vast in de template (geen blueprint-veld — het is één zin die niet
per pagina verschilt):

> **Uw aanvraag is verstuurd**
> Een lokale expert bekijkt uw vraag en neemt binnen twee werkdagen contact
> op. Ondertussen kunt u alvast rondkijken bij wat we eerder plaatsten.
> [Naar realisaties]

### Spambescherming

Hetzelfde honeypot-veld als op `/contact`. Zie "Open punten" voor reCAPTCHA.

## Opmaak

Nieuw bestand `resources/css/components/offerte-form.css`, geïmporteerd in
`site.css`. `components/form.css` blijft ongemoeid.

Dat is een bewuste keuze en geen nalatigheid: `form.css` stylet `form` en
`fieldset input, textarea` globaal, met zwarte randen — terwijl dit ontwerp
randloze witte velden op een `bg-light` kaart heeft. Door het formulier zelf
te renderen zonder `fieldset` raakt alleen `form { @apply space-y-4 }` deze
pagina, en dat is een no-op bij één wrapper-child. Dat globale bestand
herschrijven zou `/contact` meetrekken en hoort in een eigen diff.

De productpillen leunen op hetzelfde patroon als `.range-filter__btn`
(rounded-full, actief = `bg-black text-white`), maar krijgen een eigen klasse.
Het zijn checkboxes met een vinkje in de actieve staat, geen filterlinks: de
markup, de toestand en het gedrag verschillen, en `.range-filter__btn`
hergebruiken zou beide componenten aan elkaar vastklinken.

De kaart zelf: `bg-light`, afgeronde hoeken, `card-padding` als basis.

## De upload

Een echte `<input type="file">` onder een `<label>` dat als het gestippelde
vlak uit Figma oogt. Dat werkt volledig zonder JavaScript en met het
toetsenbord.

`resources/js/components/offerte-upload.js` legt daar een kleine
Alpine-component overheen die drag-and-drop toevoegt en de gekozen
bestandsnaam toont. Hij wordt geregistreerd met
`Alpine.data('offerteUpload', …)` in `site.js`, naast `cookieConsent` en
`projectFilter`. Zonder JavaScript blijft klikken werken; de zone verliest dan
alleen het slepen.

## Pagebuilder en CTA

Onder het formulier start `{{ partial:pageBuilder }}`. In `offerte.md` komt
één `cta`-set:

```yaml
overline: 'In de kijker'
title: 'Nog niet klaar voor een offerte?'
text: 'Bekijk eerst wat we bij anderen plaatsten — echte projecten in uw
       buurt, met de gekozen materialen en afwerking erbij.'
link:
  - type: entry
    entry: [c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03]   # realisaties
    label: 'Naar realisaties'
    new_tab: false
image: dummy-images/test-img-12.jpg
```

Overline, titel en knoplabel komen letterlijk uit Figma. Alleen de body is
herschreven: de tekst in het ontwerp is overgenomen van de CTA op
`/realisaties` en vraagt daar om een offerte aan te vragen — wat vreemd leest
op de pagina waar dat formulier al staat.

Het beeld is een placeholder uit `dummy-images/`, zoals alle andere
CTA-secties in dit project. `test-img-12` is een willekeurige keuze uit de
negentien beschikbare dummy's; wel bewust niet `test-img-14`, want dat is het
beeld van de CTA op `/realisaties` waar deze CTA naartoe linkt.

## Bestaande verwijzingen omzetten

Twee links wezen naar `/contact` omdat `/offerte` nog niet bestond, terwijl
hun label "Vraag offerte aan" is. Beide gaan in deze diff naar de nieuwe
entry:

- `content/collections/quicklinks/vraag-offerte-aan.md`
- de `cta`-set in `content/collections/pages/realisaties.md`

De CTA op `/aanbod` ("Neem contact op") blijft naar contact wijzen; die
bedoelt echt contact.

Geen enkele bestaande test asserteert op het doel van die links.
`QuicklinksContentTest` controleert het label en `type: entry`, en
`ProjectsOverviewPageTest` controleert alleen dát er een `cta`-sectie rendert.
Beide blijven dus groen zonder wijziging.

## Tests

Langs de bestaande scheiding tussen "staat de content er" en "rendert de
markup".

**`tests/Unit/Fieldtypes/RangeCheckboxesTest.php`** — de opties komen uit de
ranges-collectie in `order`-volgorde, met slug als waarde en titel als label;
`rules()` bevat een `in:` met alle negen slugs.

**`tests/Unit/Fieldtypes/LocationSelectTest.php`** — hetzelfde voor de drie
locaties, met `name` als label.

**`tests/Feature/Content/OffertePageTest.php`** — de entry bestaat met de
juiste slug, blueprint en template; `image` wijst naar
`quicklinks/offerte-2.png`; de pagebuilder bevat precies één `cta` met de knop
naar de realisaties-entry.

**`tests/Feature/Content/OfferteFormBlueprintTest.php`** — het formblueprint
heeft de acht velden met de afgesproken types; `products`, `name` en `email`
zijn verplicht en de andere vijf niet; `attachment` staat op de
`private`-container.

**`tests/Feature/Sections/OfferteFormTest.php`** — via
`SectionTestCase::render()`: negen productpillen met de titels uit de
collectie, drie filiaalopties, het honeypot-veld, en het bevestigingsblok in
plaats van de formuliervelden wanneer `success` waar is.

## Open punten

**reCAPTCHA werkt nergens op deze site.** `app/Listeners/VerifyRecaptcha.php`
bestaat, maar wordt nooit geregistreerd — er is geen `Event::listen` voor
`FormSubmitted` in `AppServiceProvider`. Bovendien staat `{{ recaptcha }}` in
`contact.antlers.html` buiten het `<form>`-element, waardoor de
`input.closest('form')` in dat script `null` teruggeeft en het meteen afbreekt.
Repareren raakt `/contact` even hard als `/offerte`, vraagt werkende
enterprise-credentials om te testen, en hoort in een eigen diff met eigen
tests. `/offerte` krijgt voorlopig hetzelfde honeypot-veld als `/contact`.

**Notificaties routeren per filiaal.** Nu gaat elke aanvraag naar één vast
adres. Per filiaal routeren vraagt een e-mailveld in de locations-blueprint,
drie echte adressen, en een fallback voor aanvragen zonder gekozen filiaal.

**Een product voorinvullen via de URL.** De quicklink-tekst zegt "Met Pergola
SO! voorinvuld", en in Figma staat één pil actief. Bewust niet gebouwd: de
pagina start altijd met alle pillen leeg. Een latere `?product=`-parameter kan
dit toevoegen zonder de rest van het formulier te raken.

**De echte ontvanger van de notificaties.** `hello@stuw.agency` is de
placeholder die `contact.yaml` ook gebruikt; het echte adres moet nog van de
klant komen. Hetzelfde geldt voor de globals onder `content/globals/`, die nu
allemaal leeg zijn.
