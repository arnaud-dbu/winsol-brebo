# Locations en quicklinks — open punten

Wat na het bouwen van `{{ partial:locations }}` en `{{ partial:quicklinks }}`
open bleef. Niets hiervan blokkeert de componenten zelf; het eerste blok wél
het plaatsen ervan op een pagina.

Bron: `2026-07-26-locations-quicklinks-design.md` en het plan
`docs/superpowers/plans/2026-07-26-locations-quicklinks.md`.

## Vóór de componenten op een template komen

### 1. Handmatige browsercheck van de kaart

De Leaflet-code heeft geen automatische tests — dit project heeft geen
JS-testopstelling, en het plan koos er bewust voor er geen op te tuigen voor
twee event listeners. Deze checklist is dus de énige verificatie die de
JavaScript krijgt, en is nog niet uitgevoerd:

- de kaart laadt pas bij het scrollen ernaartoe (zichtbaar in het netwerktabblad)
- de drie gele pins staan op Dilbeek, Sint-Pieters-Leeuw en Aartselaar, zonder
  witte vierkante achtergrond
- hoveren over een kaartje zoomt naar die locatie; de muis weghalen zoomt terug
  naar alle drie
- tabben naar een kaartje doet hetzelfde als hoveren
- scrollen met de muis boven de kaart scrollt de pagina, niet de kaart
- met "beweging beperken" aan springt de kaart in plaats van te animeren
- op een smal venster staat de kaart onder de kaartjes en gebeurt er niets bij
  aanraken
- de pijl in de locatiekaartjes (`arrow.svg` met `-rotate-45`) ziet eruit als de
  diagonale pijl uit Figma. Let op: het element is 14×19 omdat alleen de breedte
  gezet is; als de pijl scheef oogt, is `size-3.5` de fix (zoals `projectCard`)

### 2. Cookie-consent voor de CARTO-tegels

Elke bezoeker die naar de sectie scrollt, stuurt zijn IP en viewport naar
`basemaps.cartocdn.com`. Dat gebeurt nu onvoorwaardelijk, buiten de
consent-infrastructuur die dit project wél heeft
(`resources/js/components/cookie-consent.js`, met Consent Mode v2 en het
`<script type="text/plain" data-cookie-category="…">`-contract).

Voor een Belgische commerciële site is ongated laden van third-party assets
precies het patroon waar de EU-handhaving op de Google-Fonts-zaken over ging.
Daarnaast heeft CARTO's gratis basemap-tier een usage policy en rate limits, en
vangt de code geen `tileerror` of quota-antwoord af.

Twee routes, allebei verdedigbaar — maar het is een beslissing die genomen moet
worden, geen bug die opgelost moet worden:

- de `createMap()`-aanroep achter een consent-categorie hangen, of
- vastleggen dat decoratieve basemap-tegels onder gerechtvaardigd belang vallen

## Data die nog ingevuld moet worden

### 3. Echte huisnummers én coördinaten

De drie locatie-entries dragen `number: '000'` — letterlijk de placeholder uit
het design. De coördinaten zijn gemeentecentra, geen pandlocaties. **Als de
echte adressen ingevuld worden, moeten de coördinaten mee verhuizen**, anders
staan de pins straks een straat verderop met een adres dat wél klopt.

### 4. Twee quicklinks delen dezelfde tekst

`vraag-offerte-aan` en `bezoek-een-showroom` hebben allebei "Met Pergola SO!
voorinvuld. Vrijblijvend en op maat." Dat staat zo in het design-screenshot en
oogt als placeholder-copy die de ontwerper herhaald heeft. Voor wie de teksten
bezit.

## Losse observaties uit de eindreview

Geen van deze is opgepakt; ze staan hier zodat ze niet opnieuw ontdekt hoeven te
worden.

- **`resources/blueprints/collections/pages/contact.yaml` wordt door geen enkele
  entry gebruikt.** `content/collections/pages/contact.md` draait op
  `blueprint: page` met `template: contact`. Óf het bestand is dood en kan weg,
  óf de contactpagina hoort die blueprint te gebruiken en mist nu velden. Dit
  heeft al één keer schade aangericht: het plan ging ervan uit dat de blueprint
  bereikbaar was en schreef daarop een test die niets verifieerde.
- **`/contact` staat hardcoded in `locationCard.antlers.html`**, drie keer per
  render, en geen test bewijst dat daar iets bestaat. De quicklinks lossen
  hetzelfde probleem netjes op met een entry-link.
- **`canHover()` wordt één keer geëvalueerd** bij het aanmaken van de kaart,
  terwijl `prefersReducedMotion()` per aanroep opnieuw gelezen wordt. Een
  hybride apparaat dat na de eerste paint een muis krijgt, krijgt het
  hovergedrag niet. `sliders.js` lost het analoge geval op met een
  `matchMedia(...).addEventListener('change')`.
- **`LocationsContentTest` haalt zijn blueprint via een entry op**, dezelfde
  indirectie die `QuicklinksContentTest` juist gecorrigeerd kreeg. Hier is het
  toevallig correct (locatie-entries dragen geen `blueprint:`-sleutel), maar
  `Blueprint::find('collections.locations.locations')` zou consistent en
  toekomstvast zijn.
- **Twee sorteermechanismen naast elkaar.** `ranges` sorteert op een expliciet
  `order`-veld; `locations` en `quicklinks` gebruiken nu `structure` +
  boombestanden. Structure is het betere mechanisme — de vraag is of `ranges`
  meemigreert of dat de divergentie blijft.
- **Het plan is op één punt bewust achterhaald.** Task 3 stap 4 schrijft twee
  CSS-selectors voor die nooit kunnen matchen; de code gebruikt de gecorrigeerde
  versies. Het plan is daar het verouderde artefact, niet de code.
