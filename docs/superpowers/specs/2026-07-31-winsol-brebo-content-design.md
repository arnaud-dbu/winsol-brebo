# Winsol Brebo — Content Design

**Datum:** 2026-07-31
**Status:** Goedgekeurd voor planning
**Scope:** Dit ontwerp dekt **project 1** (modelwerk) en **project 2** (content per range). Project 3 en 4 krijgen een eigen spec.

## Doel

De winsol-brebo-site vullen met echte content, gebaseerd op winsoldilbeek.be, de Winsol-product-pdf's, winsol.eu en de SharePoint-beeldenbank — zonder het SEO-werk van het bureau van Jimmy weg te gooien, en zonder verzonnen feiten of klantverhalen op de site van een echt bedrijf.

---

## 1. Uitgangssituatie

### De oude site is groter dan het nieuwe model

Sitemap van winsoldilbeek.be: **1674 URL's**.

| Blok | Aantal | In het nieuwe model? |
|---|---|---|
| Programmatische gemeentepagina's (6 thema's × 126 gemeenten × 2 talen) | 1512 | nee |
| NL redactionele pagina's | 83 | deels |
| FR redactionele pagina's | 78 | nee (`multisite => false`) |

De gemeentepagina's zijn dun: ~1200 tekens unieke copy, twee alinea's met de gemeentenaam ingevuld, verder boilerplate. Titelpatroon `Wij plaatsen terrasoverkappingen in Aartselaar - Winsol`.

### Het aanbod is drie niveaus diep

`/nl/Ons-aanbod/<Range>/<Product>/`, met onder elk product nog een `Download-brochure-…`-pagina. Negen ranges, ~29 producten.

| Range | Producten op de oude site |
|---|---|
| Ramen en deuren | Aluminium ramen en deuren, Aluminium ramen, PVC ramen, PVC deuren, Vliegenramen, Veiligheidsdeuren, Sierluiken, Steellook |
| Rolluiken | Voorzet-, Inbouw-, Opbouwrolluiken, op zonne-energie, met Fusion-lamellen, Garagerolluiken |
| Garagepoorten | Sectionale poorten, Schuifpoorten, Garagerolluiken |
| Zonwering | Zonneschermen, SolarFix, Verandazonwering, Screens/verticale zonwering |
| Terrasoverkapping | Pergola SO!, Pergola ZIP, Pergola ZIP CUBE, Win-Cube, Patiola |
| Stalen deuren, Somfy Smart Home, VELUX, Airco | geen kinderen |

De huidige `products`-entries in de repo (carport, pergola-co, pergola-lo, pergola-so, veranda, terrasoverkapping-met-glasdak) zijn verzonnen en worden vervangen.

### De documentendrive bevat geen fotografie

3,5 GB: 1831 pdf's, 125 dwg's, 2 foto's. `Marketing & Communication/Photography realisations` is een SharePoint-linkstub, niet mee gesynct. Beeld komt uit de apart opgehaalde beeldenbank (§5).

---

## 2. Genomen beslissingen

| Onderwerp | Beslissing |
|---|---|
| Talen | NL eerst, **FR volgt na project 3**. Multisite gaat nu al aan. |
| Gemeentepagina's | Alle 1512 vervallen, 301 naar de bijbehorende rangepagina |
| Product-URL's | Genest: `/aanbod/{range}/{slug}` |
| Copywriting | Ik schrijf, review per batch |
| Brochures | Assetveld op range en product; quicklink wordt downloadknop |
| Brochurekaart zonder pdf | Kaart verbergen |
| Realisaties | Fotogalerij per producttype, geen verzonnen cases |
| Leningsimulator | Overnemen als losse pagina, link in de **footer** |
| Batchindeling | Per range verticaal, met tussentijdse check na het eerste product |
| Watermerkfoto's | Gebruiken zoals ze zijn; achteraf bijsnijden met één commando (§5.4) |
| Ontbrekend beeld | Blokkeert nooit een batch: sectie afmaken met een placeholder uit `assets/placeholder/`, gat melden via `winsol:image-gaps` (§5.8) |

### Buiten scope

Vacatures. De 756 gemeentepagina's. Verzonnen klantcases. Balustrades (staan niet in de negen ranges — 213 foto's in de beeldenbank zijn daarmee overbodig).

---

## 3. Project 1 — modelwerk

Code, geen content. Voorwaarde voor al het overige.

### 3.1 Multisite aanzetten

`php please multisite` met één site `nl`. Nu doen: er staan enkel dummy-entries. Dezelfde migratie ná project 2 en 3 raakt ~50 entries, alle assets en elke blueprint tegelijk, met de NL-site al live.

Meteen meenemen: `localizable` correct zetten in elke blueprint die we toch openen, en de collectieroutes per site voorbereiden (`/aanbod/…` naast het latere `/notre-offre/…`).

### 3.2 Geneste productroute

- `range`-veld op `resources/blueprints/collections/products/products.yaml`: `type: entries`, `collections: [ranges]`, `max_items: 1`, verplicht.
- Computed value `range_slug` op de `products`-collectie, geregistreerd in `AppServiceProvider`. Een `entries`-veld levert zelf geen slug op in een route; Statamic 6 staat computed values in routes wel toe.
- `content/collections/products.yaml`: `route: '/aanbod/{range_slug}/{slug}'`.

Ranges en products blijven **twee aparte collecties**. Samenvoegen tot één gestructureerde collectie zou `ranges/index.antlers.html` breken — dat groepeert via de `range_categories`-taxonomie, niet via een boom — en daarmee ook `rangeFilter`, `rangeCard`, `productCard` en drie page-builder-sets.

### 3.3 Brochures

- `brochure`-veld op `ranges.yaml` en `products.yaml`: `type: assets`, `container: assets`, `folder: brochures`, `restrict: true`, `max_files: 1`, mime-validatie op pdf.
- **Geen aparte assetcontainer.** `CompressUploadedAsset` filtert al op `image-compression.process_mimes`, dus een pdf gaat er ongemoeid doorheen. De assets-blueprint bevat enkel een `alt`-veld, onschadelijk voor een pdf.

### 3.4 Brochure-quicklink

- `type`-select op `quicklinks.yaml`: `default` / `brochure`, standaard `default`. Matchen op titel is te breekbaar.
- `quicklinkCard.antlers.html`: bij `type == 'brochure'` én een brochure in scope wordt de knop een directe download naar de asset-URL. Zonder brochure rendert de kaart niets.
- De brochure wordt **expliciet doorgegeven**, niet via de cascade: `{{ partial:quicklinks :brochure="brochure" }}`, en in de loop een `scope`. Binnen `{{ collection:quicklinks }}` zou `{{ brochure }}` nu toevallig doorvallen naar de pagina — precies het impliciete gedrag waar de comment bovenaan `quicklinks.antlers.html` voor waarschuwt.
- `quicklink-grid` moet twee én drie kaarten aankunnen; `lg:grid-cols-3` staat nu hardcoded in `quicklinks.antlers.html`.

### 3.5 Mediaschakelaar op `text_image`

Alleen de **optie**, niet de vormgeving van luloop. `sections/textImage.antlers.html` behoudt zijn huidige opmaak, klassen en positielogica ongewijzigd.

- Op de `text_image`-set: `media` (button_group `none` / `image` / `video`, standaard `image`) en het bestaande `video`-veld onder `if: media equals video`.
- In de template komt naast de bestaande `{{ img }}`-tak één tak die `{{ partial:video }}` rendert, in dezelfde container. `resources/views/partials/video.antlers.html` bestaat al en blijft ongewijzigd.
- Uit luloop wordt niets overgenomen behalve het idee van de schakelaar.

### 3.6 Embed-set

Nieuwe page-builder-set `embed`: `title` (text, optioneel), `url` (text, verplicht), `height` (integer, standaard 900). Voor de KBC-leningsimulator:

```
https://www.kbc.be/co2-p-wgt/visual-widget/resources/0001/nl/?creditAmount=10000&intermediaryName=Winsol&intermediaryTypeCode=0287&creditPurposeCode=60293&hideRequestLoanButton=false&isCreditAmountEditable=true&isLogoShown=true#/loa/simulation
```

De widget draagt Winsols eigen tussenpersoonscode (`0287`) en is levend.

### 3.7 Navigatie

Leningsimulator in de **footer**-navigatie, niet in de header. Op de oude site staat hij in de hoofdnavigatie; dat is een bewuste afwijking.

---

## 4. Project 2 — bronnen en content per range

### 4.1 Bronhiërarchie

| Bron | Leidend voor | Regel |
|---|---|---|
| winsoldilbeek.be (NL) | welke producten bestaan, kernboodschap, bestaande zoekwoorden | H1 en primair zoekwoord blijven staan |
| Product-pdf's (13) | specificaties, materialen, afmetingen, opties, kleuren | enige toegestane bron voor **feiten**; staat het niet in de pdf, dan claimen we het niet |
| winsol.eu | uitleg, voordelen, sectieopbouw | **nooit kopiëren, altijd herschrijven** — winsol.eu is de fabrikant met een sterker domein; letterlijk overnemen levert duplicate content op waarin Brebo altijd verliest |
| Figma | welke secties op een pagina staan en in welke volgorde | vormgeving is goedgekeurd en gaat voor op mijn eigen voorkeur |

### 4.2 Werkwijze per range

Eerst een bronblad in `docs/content-bronnen/<range>.md` met de oude copy verbatim, het pdf-extract, een samenvatting van winsol.eu en de beeldkandidaten. Pas daarna de entries. Zo is bij elke review na te gaan waar een zin vandaan komt.

### 4.3 Title-tags

De oude title-tags zijn `Winsol Dilbeek, Sint-Pieters-Leeuw & Aartselaar - Ons aanbod Ramen en deuren`: merk vooraan, zoekwoord achteraan, ruim over de 60 tekens. De H1 (`Ramen en deuren`) is wél goed.

H1 en zoekwoorden blijven ongemoeid; het title-patroon draait om naar `<Zoekwoord> op maat | Winsol Dilbeek, Sint-Pieters-Leeuw & Aartselaar`. Geen inhoudelijke afwijking, wel een verbetering.

### 4.4 Batchindeling

Elke batch: **bronblad → rangepagina + één vlaggenschipproduct → check → resterende producten → korte eindcheck.**

Batch 1 is de dure check: daar valideren we toon, lengte, sectiekeuze en bronnengebruik. Slaagt die, dan zijn de volgende licht.

De volgorde volgt de beeldbeschikbaarheid uit §5.6, aflopend; bij gelijke stand gaat de range met de meeste producten voor.

| # | Range | Vlaggenschip | hero / sectie |
|---|---|---|---|
| 1 | Terrasoverkapping | Pergola SO! | 43 / 49 |
| 2 | Ramen en deuren | Aluminium ramen en deuren | 54 / 165 |
| 3 | Rolluiken | Voorzetrolluiken | 3 / 12 |
| 4 | Garagepoorten | Sectionale poorten | 1 / 25 |
| 5 | Zonwering | Screens / verticale zonwering | 0 / 19 |
| 6–9 | Stalen binnendeuren, Somfy Smart Home, VELUX, Airco | — | 0 / 0 |

**Geen enkele batch wordt geblokkeerd door beeld.** De secties worden altijd volledig opgemaakt; ontbreekt een geschikte foto, dan komt er een placeholder (§5.8) en gaat de batch door. Een kleinere foto dan de laag vraagt is toegestaan — liever een scherpe 1200px-foto op een hero dan een gat. Watermerkfoto's mogen overal; `clean-watermarks` (§5.4) ruimt ze achteraf op.

Batch 3 (Rolluiken) verdient extra aandacht: dat is de eerste range zonder brochure, dus daar blijkt of de verbergende quicklinkkaart klopt.

---

## 5. Beeld

### 5.1 R2 en de bestaande pijplijn

Vergt geen nieuw werk. Container `assets` staat op disk `r2`; een aparte `r2_img`-disk zorgt dat ook Glide-afgeleiden op R2 landen. `components/img.antlers.html` regelt `<picture>`, webp, srcset en focuspunt en blijft ongewijzigd.

### 5.2 Importcommando

`CompressUploadedAsset` hangt aan het `AssetUploaded`-event (`AppServiceProvider.php:39`). Dat vuurt bij een CP-upload, maar **niet** bij een rechtstreekse `Storage::disk('r2')->put()`. Bulk-importeren met een kaal script zet dus ongecomprimeerde originelen op R2.

`php please winsol:import-images` uploadt daarom via de assetcontainer, zodat het event vuurt en de bestaande compressie (max 2500px, JPEG 85) zijn werk doet. Geen tweede compressiepad.

Het commando zet per asset de `watermark`-vlag (boolean) via de detector uit §5.3, en bewaart de bounding box van het watermerkvlak zodat §5.4 later niet opnieuw hoeft te meten.

Het focuspunt blijft op de standaard `50-50`. Een eerder ontwerp forceerde dat laag om het watermerk bij lichte hersnedes te sparen; dat vervalt nu watermerkfoto's overal gebruikt mogen worden — een laag focuspunt zou de compositie van een hero of `text_image` juist bederven. Focuspunten worden per foto bijgesteld waar de uitsnede het vraagt.

Het `alt`-veld blijft handwerk per batch (zie §5.5).

Doelmap: `assets/<range>/`.

### 5.3 Watermerkdetectie

Gemeten over alle 1642 foto's: witfractie (drempel 238 in grijswaarde) in het vlak rechtsonder (x 0,74–1,00; y 0,845–1,00), met dezelfde zone linksonder als controle. Classificatie: watermerk indien `br ≥ 0,08` én `br ≥ 4 × bl`.

Scheiding is scherp: watermerkfoto's meten 0,16–0,22 tegen een controle van 0,001–0,011; schone foto's 0,0001. Visueel geverifieerd op vier gevallen (drie met, één zonder).

### 5.4 Watermerken wegwerken

**Het bijsnijden is niet het dure deel — de toestemming is dat.** Het gaat om het merk van de leverancier op diens eigen foto's. Brebo is officieel Winsol-verkooppunt, dus het gebruik is legitiem; het verwijderen is een vraag van Jimmy aan Winsol.

Daarom: **nu niets bewerken.** Watermerkfoto's worden gewoon gebruikt, ook in headers en `text_image`. De pagina's zijn daarmee af en beoordeelbaar; alleen het logo staat er nog (soms half) op.

Zodra Jimmy akkoord is:

```
php please winsol:clean-watermarks --dry-run   # toont wat er zou gebeuren
php please winsol:clean-watermarks --list      # schrijft de bestandsnamen uit
php please winsol:clean-watermarks             # snijdt bij
```

Het commando:

1. doorloopt alle entries en verzamelt elke asset waar een `image`-, `images`- of galerijveld naar wijst;
2. houdt daarvan de assets met de `watermark`-vlag over — geen 1187, maar de circa honderd die werkelijk gebruikt worden;
3. lokaliseert per foto het exacte watermerkvlak via de bounding box van de detector en snijdt **precies zoveel weg als nodig**, geen vaste strook;
4. zet de `watermark`-vlag om, zodat een tweede run dezelfde foto overslaat;
5. leegt de Glide-cache.

Assetpaden blijven identiek, dus **geen enkele entry hoeft aangepast**.

**Gevolgen om te kennen:**
- De verhouding verandert licht, dus Glide hersnijdt rond het focuspunt. Op een hero of `text_image` is dat een verschuiving van enkele procenten. Bij kritische composities kan het focuspunt daarna bijgesteld worden.
- Terugdraaien kan altijd: de bronmap van 1,6 GB staat lokaal in `/Users/arnaud/Documents/winsol/afbeeldingen`. Opnieuw importeren volstaat; geen extra opslag op R2 voor originelen.
- `--list` levert de aanvraag aan Winsol op: een concrete lijst van ~100 bestandsnamen waarvoor je de versie zonder watermerk vraagt. Komen die binnen, dan vervang je de bestanden op dezelfde paden.

**Blijvende regel:** waar een schone foto bestaat, gebruiken we die. Niet uit principe, maar omdat een bijgesneden foto altijd iets van zijn compositie inlevert.

### 5.5 Alt-teksten

Het `alt`-veld bestaat al op de assets-blueprint. Voor een installatiebedrijf is dat geen bijzaak maar de enige manier waarop die foto's vindbaar worden. Hoort bij de batch, niet als naloopronde.

De bestandsnamen helpen: `Winsol_2019_Mol_Pergola SO! (23).jpg`, `Realisatie-Pergola-SO-terrasoverkapping-Oostkamp (1).jpg` — product en plaats zitten er al in.

### 5.6 Inventaris van de beeldenbank

1642 foto's, 1,6 GB, in `/Users/arnaud/Documents/winsol/afbeeldingen`.

**Patroon in de mapnamen:**
- `LR` / `Low Res` / `lage resolutie` → webderivaten mét watermerk;
- mappen met jaar en plaatsnaam (`2019_Ardooie`, `2019_Oekene`, `2019_Aalbeke`, `2020_Boutersem`) → schone originelen;
- `terrasoverkappingen/web/` → 55 foto's, schoon, hoge resolutie, liggend; de beste map van de set.

**Geschiktheid hangt af van de positie, niet van één drempel.** De beeldposities vragen sterk uiteenlopende breedtes (`headers/hero` gebruikt `max_width="2560"`, `sections/textImage` 1600, kaarten 640–1400), dus wordt er in drie lagen gemeten. Alle drie gaan uit van een foto zonder watermerk.

| Laag | Eis | Voor |
|---|---|---|
| hero | liggend, ≥2000px | `headers/hero`, `headers/product`, `headers/project` |
| sectie | liggend, ≥1400px | `sections/textImage`, `sections/cta` |
| kaart | ≥800px, elke oriëntatie | `rangeCard`, `productCard`, `imageGallery`, `card` |

Meting over 1642 foto's (na het opkuisen van `luifels`):

| Map | Totaal | Watermerk | Schoon | hero | sectie | kaart |
|---|---|---|---|---|---|---|
| ramen-en-deuren | 309 | 7 | 302 | 54 | 165 | 301 |
| terrasoverkappingen | 395 | 330 | 65 | 43 | 49 | 65 |
| luifels | 212 | 165 | 47 | 0 | 19 | 47 |
| garagepoorten | 42 | 2 | 40 | 1 | 25 | 40 |
| rolluiken | 114 | 84 | 30 | 3 | 12 | 30 |
| screens | 357 | 352 | 5 | 0 | 0 | 5 |
| ballustrades | 213 | 204 | 9 | 0 | 2 | 9 |

**De mapnamen dekken de ranges niet.** `ballustrades` valt weg — balustrades staan niet in de negen ranges, dus 213 foto's blijven ongebruikt.

Vertaald naar de negen ranges:

| Range | Bronmappen | hero | sectie | kaart |
|---|---|---|---|---|
| Ramen en deuren | ramen-en-deuren | 54 | 165 | 301 |
| Terrasoverkapping | terrasoverkappingen | 43 | 49 | 65 |
| Garagepoorten | garagepoorten | 1 | 25 | 40 |
| Zonwering (zonneschermen, SolarFix, screens, verandazonwering) | luifels + screens | 0 | 19 | 52 |
| Rolluiken | rolluiken | 3 | 12 | 30 |
| Stalen binnendeuren, Somfy Smart Home, VELUX, Airco | geen | 0 | 0 | 0 |

**Het tekort is smaller dan het lijkt.** Garagepoorten, Zonwering en Rolluiken hebben ruim voldoende sectie- en kaartbeeld; alleen een schone **hero** ontbreekt. Omdat watermerkfoto's overal gebruikt mogen worden (§5.4), blokkeert dat niets: die heroes krijgen voorlopig een watermerkfoto en `clean-watermarks` ruimt ze later op.

Zonder bronmap zijn Stalen binnendeuren, Somfy Smart Home, VELUX en Airco. Die worden niet uitgesteld: hun secties krijgen placeholders volgens §5.8 en worden achteraf gericht aangevuld.

### 5.7 Geen tweede ronde op SharePoint

Er wordt niet opnieuw op SharePoint gezocht. De beeldgaten worden aangevuld **nadat** de secties staan, gericht en door Arnaud zelf. De taak van project 2 is daarom niet "wacht op beeld" maar "maak de sectie af en meld precies wat er ontbreekt".

### 5.8 Placeholders en beeldgaten

**Regels bij het kiezen van beeld, in volgorde:**

1. een schone foto uit de juiste laag (§5.6);
2. anders een schone foto uit een lagere laag — **een kleinere foto is toegestaan**, ook op een hero. Glide schaalt niet op, dus een scherpe 1200px-foto is beter dan een gat;
3. anders een watermerkfoto uit dezelfde range;
4. anders een placeholder.

**Placeholders staan in `assets/placeholder/`**, nooit ergens anders. Dat is geen administratieve voorkeur maar de enige manier om ze terug te vinden: een markdownlijst veroudert zodra iemand een foto vervangt, een mapverwijzing niet.

`php please winsol:image-gaps` doorloopt alle entries en somt elk veld op dat nog naar `assets/placeholder/` wijst, met per regel: collectie, entry, veld, sectie, en wat er nodig is (oriëntatie, minimale breedte, onderwerp). Dat is tegelijk de boodschappenlijst en de livegangcontrole — bij oplevering moet de uitvoer leeg zijn.

**Grens aan wat een placeholder mag zijn.** Sfeerbeeld of een abstracte foto van Unsplash is prima. Een foto van iemand anders' pergola, raam of poort is dat niet: op de site van een installateur leest elke productfoto als hun eigen werk. Placeholders die een realisatie suggereren horen dus niet op een live pagina — vandaar de controle hierboven.

---

## 6. Restpagina's van de oude site

| Pagina | Behandeling |
|---|---|
| 6× `/nl/Winsol-<thema>/` (regio-hubs) | vervallen met de gemeentepagina's, 301 naar de range |
| ~13 `Download-brochure-…` | vervallen, brochure hangt aan de entry; 301 naar range of product |
| `/nl/Ons-aanbod/Zonwering/Configurator/` | lege pagina met enkel een offerteformulier; 301 naar de offertepagina |
| `/nl/screens-verticale-zonwering/` | wordt een product onder Zonwering (er is een pdf voor) |
| `/nl/Vacatures/` | buiten scope, 301 naar contact |
| `/nl/Simuleer-je-lening/` | overnemen via de `embed`-set, link in de footer |
| `/nl/Inspiratie/` | wordt de realisatiegalerij |

---

## 7. Redirects (project 4, hier alvast vastgelegd)

| Groep | Aantal | Bestemming |
|---|---|---|
| Gemeentepagina's NL | 756 | de bijbehorende rangepagina |
| Gemeentepagina's FR | 756 | idem, tot de FR-site er is |
| Regio-hubs `/nl/Winsol-<thema>/` | 6 | de bijbehorende range |
| `Download-brochure-…` | ~13 | de range of het product |
| Overige NL en FR | ~160 | 1-op-1 mapping |

De mapping komt als CSV in de repo, gegenereerd uit de sitemap en met de hand nagelopen. Bij de FR-lancering is diezelfde CSV de bron om de Franse URL's terug te zetten in plaats van ze opnieuw te herleiden.

---

## 8. Aannames

- Routes voor de latere FR-site worden per site gedefinieerd; de exacte Franse padnamen worden bij dat project bepaald.
- De 13 product-pdf's dekken 3 van de 9 ranges. Voor de overige zes verschijnt geen brochurekaart tot er een pdf is.
- `range_categories` blijft de groepering op het aanbodoverzicht; de nesting van producten verandert daar niets aan.
- De watermerkdetector draait bij import één keer; komen er later foto's bij, dan draait hij mee in dezelfde import.
