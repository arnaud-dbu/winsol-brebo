# Bronblad — batches 4 tot 9

**Datum:** 2026-08-01
**Batches:** Garagepoorten, Zonwering, Stalen binnendeuren, Somfy Smart Home,
VELUX en Airco. In één ronde uitgevoerd op vraag van de eigenaar.

Bronhiërarchie volgens
`docs/superpowers/specs/2026-07-31-winsol-brebo-content-design.md` §4.1.

---

## 1. Wat het onderzoek opleverde

De oude site heeft maar drie ranges met échte subpagina's: Terrasoverkapping,
Ramen en deuren, en Rolluiken. Dat waren batches 1 tot 3. De vijf overige
ranges bestaan daar uit één enkele pagina, waarop de producten alleen als
tekstblok met een "Lees meer" staan.

`Ons-aanbod` linkt naar precies acht ranges:

Airco, Garagepoorten, Ramen en deuren, Rolluiken, Somfy Smart Home,
Terrasoverkapping, VELUX en Zonwering.

**Stalen binnendeuren staat daar niet bij.** Zie §6.

---

## 2. Het gamma per range

### Garagepoorten (3 producten)

| Oude site | winsol.eu vandaag |
|---|---|
| Sectionale poorten | `/garagepoorten/sectionale-garagepoorten` |
| Schuifpoorten | niet apart |
| Garagerolluiken | niet apart |

winsol.eu is hier smaller dan winsoldilbeek. De oude site is gevolgd, want dat
is de SEO-afspraak. Garagerolluiken komt hier terecht en niet bij Rolluiken,
zoals afgesproken in batch 3: op de oude site staat dat product onder twee
ranges, en ons model laat er maar één toe.

De drie reeksen binnen sectionale poorten (Isol-Comfort, Comfort, Comfort-Go)
komen van winsol.eu en staan als blok op de productpagina, niet als aparte
producten. Ze verschillen in toepassing, niet in soort.

### Zonwering (6 producten)

| Oude site | winsol.eu vandaag |
|---|---|
| Zonneschermen | `/zonwering/zonneschermen` |
| Screens | `/zonwering/screens` |
| SolarFix | `/zonwering/screens/solarfix` |
| Verandazonwering | `/zonwering/veranda` |
| Terrasoverkapping (verwijzing) | eigen range bij ons |
| Configurator (geen product) | — |
| — | nieuw: `/zonwering/zonneschermen-op-zonne-energie` |
| — | nieuw: `/zonwering/uitvalscherm-markiezen` |

Zelfde lijn als batch 2 en 3: het oude gamma blijft, de nieuwe winsol.eu-
producten komen erbij.

### VELUX (3 producten)

De oude pagina behandelt vier zaken: dakvensters, buitenzonwering,
verduisterende buitenzonwering en rolluiken. De twee zonweringstypes zijn
samengevoegd tot één product met twee blokken. Reden: er is nul beeldmateriaal
voor VELUX, en vier pagina's zuivere tekst waarvan er twee bijna hetzelfde
zeggen leest slechter dan drie stevige.

### Somfy Smart Home, Airco en Stalen binnendeuren (0 producten)

Deze drie zijn één pagina zonder deelproducten. Bij Somfy gaat het om één
systeem (TaHoma) en niet om een assortiment. Bij Airco gaat het om een dienst
die Brebo levert, geen Winsol-productlijn. Stalen binnendeuren zie §6.

De `products`-sectie ontbreekt in hun page builder. Dat is een geldige
configuratie: het is een optionele set.

---

## 3. Merknamen in deze batches

| Naam | Waar | Wat het is |
|---|---|---|
| **Isol-Comfort** | Garagepoorten | Sectionaalpoort voor inpandige garages |
| **Comfort** | Garagepoorten | Sectionaalpoort, alle maten en afwerkingen |
| **Comfort-Go** | Garagepoorten | Compacte poort, vervangt een kantelpoort |
| **SolFix** | Zonwering | Voorzetscreen met ritssysteem |
| **SolFix IN** | Zonwering | Inbouwscreen, kast in de spouw |
| **SolScreen** | Zonwering | Screen met afgeronde kast, zonder rits |
| **SolarFix** | Zonwering | Voorzetscreen op zonne-energie |
| **Linasol / Lumisol / Squaro / Luno** | Zonwering | Zonneschermen, verschillen in kastvorm |
| **LinaSolar / LumiSolar** | Zonwering | Zonneschermen op zonne-energie |
| **Verandasol** | Zonwering | Zonwering bovenop een glazen dak |
| **AluBox** | Zonwering | Uitvalscherm met semi-cassette |
| **TaHoma** | Somfy | De centrale van het Somfy-systeem |

---

## 4. Waar de feiten vandaan komen

**Garagepoorten.** De onderhoudsfiche `Sectionale_poorten_Belgi_.pdf` levert
maar één bruikbaar productgegeven: de panelen zijn vuurverzinkte staalplaten
met een tweevoudig ingebrande polyestercoating. Al de rest (RC2, de drie
reeksen, de maxima van Comfort-Go) komt van winsol.eu. Voor garagerolluiken is
er wél een echte productfiche, `Garagerolluiken.pdf`, met de lameltypes,
kastmaten, kleuren en de norm EN 13241-1.

**Zonwering.** Twee brochures uit `product-pdfs`:
`Winsol_Brochure_Verticale-zonwering_NL.pdf` (screens) en
`WINSOL_Brochure_Luifels_NL.pdf` (zonneschermen), aangevuld met winsol.eu voor
de reeksbeschrijvingen. De vergelijkingstabel achterin de screensbrochure is de
bron voor de lichtdoorgang (1, 5 of 10 %), de maximale oppervlakte (18 m² voor
screens tegenover 12 m² voor een klassiek rolluik) en de kastafmetingen
(90, 110, 125, 150 mm).

**VELUX en Somfy.** Volledig van winsoldilbeek.be. Beide pagina's daar zijn
uitzonderlijk rijk vergeleken met de rest van die site, waarschijnlijk omdat de
tekst van de fabrikant is overgenomen.

**Airco.** De oude pagina is kort en dat is alles wat er is: geruisloze
toestellen, exclusieve Daikin-toestellen, koelen én verwarmen, koppelbaar aan
een warmtepomp. Er is bewust niets bij verzonnen over vermogens of
multisplitsystemen.

---

## 5. Beeldinventaris

De bronmappen `zonwering/` en `stalen-deuren/` zijn **leeg**. Het beeld voor de
range Zonwering zit in twee andere mappen: `screens/` (357) en `luifels/` (212).
Die laatste is geïmporteerd onder de containermap `zonwering/`.

| Bronmap | Totaal | Watermerk | Schoon |
|---|---|---|---|
| `garagepoorten/` | 42 | 2 | 40 |
| `screens/` | 357 | 352 | 5 |
| `luifels/` → `zonwering/` | 212 | 165 | 47 |
| `zonwering/` | 0 | — | — |
| `stalen-deuren/` | 0 | — | — |

**`screens/` is vrijwel volledig gewatermerkt.** Vijf schone foto's op 357, en
geen daarvan is breed genoeg voor een sectiebeeld. De screens- en
SolarFix-pagina's draaien dus op gewatermerkt materiaal. Dat is de afspraak uit
batch 1: de vlag staat, `winsol:clean-watermarks` snijdt ze later weg.

**`screens/` bevat deels duplicaten.** De submappen `LR`, `LR 2`,
`lage resolutie` en `lage resolutie 2` overlappen: 245 van de 357 bestanden
hebben een unieke naam, de overige 112 staan er dubbel. De import bewaart de
mapnaam, dus die 112 komen onder twee paden in de container terecht. Bij
`luifels/` speelt dat nauwelijks: 208 van de 212 zijn uniek. Niet opgeruimd;
het kost wat opslag maar breekt niets.

Op de SolarFix-pagina was dat wél een inhoudelijk probleem: drie van de vier
beeldplaatsen wezen aanvankelijk naar dezelfde foto uit drie verschillende
mappen. Dat is rechtgezet met vier verschillende opnames uit dezelfde
realisatie.

### Twee imports tegelijk zien elkaars werk niet

Omdat de bulkimport traag ging, zijn de vijftien foto's die de content nodig
had apart vooruit geïmporteerd, met dezelfde mapstructuur zodat de bestaat-al-
toets ze zou herkennen. Dat werkte voor `luifels/`, want die importrun startte
pas daarna en las de container vers in. Voor `screens/` niet: die run liep al
en hield een verouderde assetlijst vast, waardoor hij drie bestanden opnieuw
uploadde en Statamic er een tijdstempel achter plakte.

De drie duplicaten zijn verwijderd; de originelen waren intact, gemarkeerd als
gewatermerkt en in gebruik. **Les:** `winsol:import-images` is idempotent
binnen één run en tussen opeenvolgende runs, maar niet tussen runs die elkaar
overlappen. Laat imports op dezelfde container nooit gelijktijdig lopen.

**Zonder enig beeld:** VELUX, Somfy Smart Home, Airco, Stalen binnendeuren,
verandazonwering, uitvalschermen en garagerolluiken. Alles daar staat op
`placeholder/beeld-ontbreekt.jpg` en wordt gemeld door `winsol:image-gaps`.

---

## 6. Stalen binnendeuren heeft geen enkele bron

Dit is het enige echte open punt van deze ronde.

- Niet in `Ons-aanbod` van winsoldilbeek.be
- 404 op `winsol.eu/nl-be/stalen-binnendeuren`
- Geen brochure in de drive
- Bronmap `stalen-deuren/` bestaat maar is leeg

De range bestond al in het model, vermoedelijk uit het Figma-ontwerp of op
vraag van de eigenaar. De pagina is geschreven op wat over dit producttype in
het algemeen waar is: smalle stalen profielen, veel glas, uitvoerbaar als vaste
wand, schuifdeur of draaideur. Er staan bewust **geen** merknamen, afmetingen,
profielbreedtes of technische waarden in, want die zouden verzonnen zijn.

**Voor te leggen aan de eigenaar:** verkoopt Brebo dit werkelijk, en zo ja van
welke leverancier? Zodra dat bekend is, kan de pagina met echte gegevens en
beeld aangevuld worden. Blijkt het niet te kloppen, dan hoort de range uit het
model te verdwijnen.

---

## 7. Wijzigingen aan bestaande rangebeschrijvingen

Vier `long_description`-teksten stonden in het model met plausibele maar
onjuiste of onbronde inhoud. Ze zijn herschreven:

- **Garagepoorten** noemde "kanteldeuren" als productlijn. Winsol verkoopt die
  niet; Comfort-Go is er juist de vervanger van. Nu: sectionaal, schuif, rolluik.
- **Zonwering** noemde "raamdecoratie" en "zonnetenten". Vervangen door het
  gamma zoals het werkelijk is.
- **VELUX** claimde "officieel erkend installateur". Die claim is nergens
  onderbouwd en is geschrapt.
- **Airco** beschreef multisplitsystemen en bediening via app. Niet in de bron;
  vervangen door wat er wél staat.

---

## 8. Schrijfregels

Zoals batch 2 en 3: geen gedachtestreepjes in sitecontent. Splits de zin of zet
een komma.
