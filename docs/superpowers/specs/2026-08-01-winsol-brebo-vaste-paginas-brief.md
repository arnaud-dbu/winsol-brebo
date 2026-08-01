# Brief — project 3: de vaste pagina's

**Datum:** 2026-08-01
**Status:** klaar om uit te voeren
**Voorganger:** project 1 (modelwerk) en project 2 (ranges en producten), beide af

Dit bestand vervangt het gesprek. Alles wat een verse sessie nodig heeft om
project 3 te draaien staat hier; het gesprek waarin dit is afgesproken niet.

---

## 1. Waar we staan

| Project | Inhoud | Status |
|---|---|---|
| 1 | Modelwerk: multisite, geneste productroutes, brochureveld, beeldcommando's | af, gemerged |
| 2 | Bronbladen en content per range, negen batches | af |
| **3** | **De vaste pagina's** | **dit document** |
| 4 | Go-live: redirectmap, sitemap, GSC | nog niet gestart |

Na project 3 volgt een aparte ronde: **ranges en producten herwerken, pagina per
pagina.** Dat is bewust naar achteren geschoven. De eigenaar wil daar chirurgisch
door, en verwacht daarbij componenten die vandaag nog niet bestaan. Project 3
bouwt die componenten, dus die herwerking wordt makkelijker als ze erna komt.

---

## 2. De volgorde

Afgesproken en goedgekeurd:

1. **service**
2. **offerte**
3. **contact**
4. **aanbod**
5. **home**
6. **over-ons**

Daarna pas ranges en producten.

### Realisaties staat on hold

Bewuste beslissing van de eigenaar. De realisaties komen het best tot hun recht
op de productdetailpagina's; een apart overzicht plus detailpagina is daarnaast
grotendeels overbodig.

Er komt wel een blog aan waarvoor nog geen ontwerp bestaat. Mogelijk wordt de
realisatiesectie daar één op één door vervangen. **Dat wordt een apart project.**
Tot dan: niets aan realisaties doen, ook niet opruimen.

---

## 3. De feedbackpunten van de eigenaar

Woordelijk vastgelegd, want ze sturen het werk.

### 3.1 Servicebeelden

Map: `/Users/arnaud/Documents/winsol/service-images` (4 jpg's).

Weergeven **zoals in Figma**. De vier foto's mappen één op één op de vier
tekstblokken, in bestandsvolgorde:

| Bestand | Blok | Wat erop staat |
|---|---|---|
| `winsol 2.jpg` (1536×1024) | Advies | Adviseur wijst naar een garagepoort in de toonzaal, met een koppel |
| `winsol 2_1.jpg` (1000×667) | Installatie | Plaatser monteert een kader aan de gevel |
| `winsol 2_2.jpg` (1920×1143) | Onderhoud | Man onderhoudt een raam |
| `winsol 2_3.jpg` (1448×1086) | Garantie | Winsol-medewerker met garantiemap bij een klant binnen |

Importeren met `php artisan winsol:import-images <map> service`. Let op de
regel uit batch 4 tot 9: **nooit twee imports tegelijk op dezelfde container**,
die zien elkaars werk niet.

### 3.2 Offerte en contact

Vrij letterlijk overnemen zoals ze in het ontwerp staan. **Met één uitzondering:
de contactgegevens en het adres.** Die uit het ontwerp zijn niet betrouwbaar;
haal de juiste op van winsoldilbeek.be.

### 3.3 De CTA onderaan elke pagina

Elke pagina heeft onderaan een CTA. Die moet **per pagina uniek** zijn, niet
overal dezelfde tekst.

### 3.4 Over ons

Het Figma-ontwerp volgen, maar **voorstellen doen waar de formulering beter
kan.** De eigenaar staat daar expliciet voor open.

---

## 4. Figma

Bestand: `dgMxUtoYzYrR5FRuwPzQBn`, pagina **Design v2** (`293:2110`).

| Pagina | Node | Ook als |
|---|---|---|
| `/home` | `293:2695` | |
| `/service` | `318:2955` | |
| `/offerte` | `318:3956` | |
| `/contact` | `318:3481` | |
| `/over-ons` | `318:3268` | |
| `/aanbod` | `293:3002` | desktop `454:5356`, mobile `454:5541` |
| `/aanbod/categorie` | `293:3516` | desktop `457:6569`, mobile `457:6754` |
| `/aanbod/categorie/product` | `301:3494` | |
| `/realisaties` | `297:2847` | on hold |
| `/realisaties/realisatie` | `301:3280` | on hold |
| `/page-builder` | `449:1399` | demopagina |

Losse componentframes: `cards` (449:1850), `projects` (449:1660),
`grid_features` (449:2040), `cta` (449:1698), `products` (449:1799).

**Praktisch:** `get_screenshot` schaalt de langste zijde naar `maxDimension`. Een
paginaframe van 1744×5356 op de standaard 1024 levert 456 px breed op en is dan
onleesbaar. Vraag hem op ware grootte op en snijd hem daarna in stroken met PIL
(`sips --cropOffset` gedraagt zich anders dan verwacht en levert overlappende
stroken op).

---

## 5. De staat van de pagina's bij aanvang

```
home                 4 blokken     1 dummyfoto
contact              4 blokken     1 dummyfoto
service             12 blokken     4 dummyfoto's
offerte              2 blokken     1 dummyfoto
aanbod               2 blokken
realisaties          2 blokken     1 dummyfoto   (on hold)
simuleer-je-lening   1 blok
over-ons             0 blokken     titel "About", lorem ipsum
cases                0 blokken     tekst van STUW, resten van de starterkit
page-builder        56 blokken    13 dummyfoto's, demopagina
```

### Open beslissingen voor de eigenaar

Geen van deze mag zonder toestemming uitgevoerd worden; verwijderen is
onomkeerbaar.

- **`cases`** draagt nog de boilerplate van de starterkit: "Bij STUW zetten we
  ons in voor uitmuntende resultaten... digitale producten ontwerpen". Hoort
  vermoedelijk weg.
- **`page-builder`** is een demopagina van 56 blokken die nu gewoon 200 geeft.
  Hoort niet publiek te staan.
- **De zes projecten** in de projects-collectie zijn verzonnen klantcases op
  dummyfoto's, terwijl de eigenaar expliciet heeft gezegd: geen verzonnen cases.
  Valt samen met de on-holdbeslissing over realisaties, dus voorlopig laten staan.
- **`over-ons`** heeft echte bedrijfsinfo nodig van Jimmy: drie filialen die één
  zaak zijn, sinds wanneer, hoeveel mensen, wat de eigen plaatsingsploeg doet.
  De vorm kan zonder, de inhoud niet.

---

## 6. Wat al is uitgezocht voor /service

De opbouw in Figma:

```
hero            "Service" + ondertitel
ankerrij        Advies ↓  Installatie ↓  Onderhoud ↓  Garantie ↓   [Herstelling melden]
text_image      ADVIES        Advies op maat            beeld links
text_image      INSTALLATIE   Vakkundige installatie    beeld rechts
text_image      ONDERHOUD     Onderhoud en nazicht      beeld links
text_image      GARANTIE      Garantie en nazorg        beeld rechts
herstelling     "Iets stuk of werkt iets niet meer?" + formulier
```

Het herstellingsformulier bestaat al (zie `ReparationSectionTest`). De **ankerrij
met vier knoppen bestaat nog niet** en moet gebouwd worden.

### Drie afwijkingen van het ontwerp, voorgesteld

1. **U-vorm naar je-vorm.** Het ontwerp schrijft "U heeft één vast
   aanspreekpunt" en "Zo staat u nooit alleen"; de rest van de site tutoyeert.
2. **Gedachtestreepjes eruit.** Staat er twee keer in
   (`ons eigen team — geen onderaannemers`,
   `wat er aan de hand is — we nemen contact op`) en dat is tegen de vaste regel.
3. De ankerrij bouwen als nieuwe component.

---

## 7. Regels die blijven gelden

- **Geen gedachtestreepjes** in sitecontent. Splits de zin of zet een komma.
- **Tutoyeren.** De site spreekt met "je", niet met "u".
- **Aartselaar**, niet Aarschot. De footer in Figma en de locations-collectie
  zijn het daarover eens; de oorspronkelijke briefing zei Aarschot en dat was
  een vergissing.
- De codeerregels uit `CLAUDE.md`: ternaries in plaats van `{{ if }}`-blokken
  voor één waarde, Tailwind-utilities in plaats van herhaalde klassenreeksen,
  tokens in `@theme` in plaats van arbitraire waarden, `{{ icon }}` in plaats
  van `{{ svg }}` voor Phosphor-iconen.
- **Tests:** `vendor/bin/phpunit -d memory_limit=1G`, nooit `php artisan test`.

### Drie tests staan bewust rood

`CardLayoutCascadeTest`, `ReparationSectionTest` en `LocationsTest`. Elk met een
uitspraak in
`docs/superpowers/specs/2026-07-31-winsol-brebo-modelwerk-followups.md`. Een
suite met precies deze drie failures is groen; meer is regressie.

Let op: **`ReparationSectionTest` raakt het herstellingsformulier**, dat op de
servicepagina staat. Bij het bouwen van die pagina hoort die uitspraak opnieuw
tegen het licht gehouden te worden.

---

## 8. Bekende drift

Twee tests driften mee zodra het aanbod verandert. Ze horen niet bij project 3,
maar verklaren rode runs die niet van dit werk komen:

- `CatalogContentTest` pint het aantal producten vast (nu 32).
- `MegaMenuTest` pint drie `short_description`-teksten letterlijk vast.

---

## 9. Openstaande punten uit eerdere projecten

- De CTA "Vraag brochure aan" is nergens meer zichtbaar, gevolg van
  verbergen-zonder-pdf plus geen quicklinks op rangepagina's.
- 43 beeldplekken staan op `placeholder/beeld-ontbreekt.jpg`, te vinden met
  `php artisan winsol:image-gaps`. Zwaarst wegend: Fusion-lamellen en VELUX.
- 34 foto's in gebruik dragen nog een watermerk. `winsol:clean-watermarks`
  draait pas op het startsein van de eigenaar.
- Garagerolluiken staat op de oude site onder twee ranges. De URL onder
  Rolluiken heeft een 301 naar Garagepoorten nodig, voor project 4.
- Vier zinnen liggen dicht tegen winsol.eu aan. Voor de SEO-samenwerking is dat
  de enige bron waar bijna-identieke tekst een probleem is. Staat op
  `uitvalschermen-en-markiezen` (2), `garagepoorten` (1) en `solarfix` (1).
