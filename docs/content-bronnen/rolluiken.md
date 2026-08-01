# Bronblad — Rolluiken

**Datum:** 2026-08-01
**Batch:** 3 van 9 (project 2)
**Vlaggenschipproduct:** Voorzetrolluiken

Bronhiërarchie volgens
`docs/superpowers/specs/2026-07-31-winsol-brebo-content-design.md` §4.1.

---

## 1. Het gamma

Geverifieerd door de links van `winsol.eu/nl-be/rolluiken` uit te lezen, niet
door URL's te raden.

| Oude site | winsol.eu vandaag |
|---|---|
| Voorzetrolluiken | `/rolluiken/voorzetrolluiken` |
| Inbouwrolluiken | `/rolluiken/inbouwrolluiken` |
| Opbouwrolluiken | `/rolluiken/opbouwrolluiken` |
| Rolluiken op zonne-energie | `/rolluiken/rolluiken-op-zonne-energie` |
| Rolluiken met Fusion-lamellen | `/rolluiken/rolluiken-met-fusion-lamellen` |
| Garagerolluiken | **niet onder rolluiken** |
| — | nieuw: `/rolluiken/rolluiken-met-klassieke-lamellen` |

Dat geeft zes producten:

1. Voorzetrolluiken (vlaggenschip)
2. Inbouwrolluiken
3. Opbouwrolluiken
4. Rolluiken op zonne-energie
5. Rolluiken met Fusion-lamellen
6. Rolluiken met klassieke lamellen

### Garagerolluiken staat op de oude site onder twee ranges

`/Ons-aanbod/Rolluiken/Garagerolluiken/` én onder Garagepoorten. Ons model
laat dat niet toe: het `range`-veld op een product is `max_items: 1`, dus een
product hoort bij precies één range. Garagepoorten is de natuurlijke plek,
dus het product komt in batch 4 en de rolluik-URL krijgt een 301 daarheen.
Vastleggen in de redirectmap van project 4.

### Twee assen, geen zes losse producten

De zes pagina's beschrijven niet zes verschillende rolluiken. Er zijn twee
assen die je onafhankelijk combineert:

- **Waar de kast zit:** voorzet (tegen de gevel), inbouw (in de spouwmuur),
  opbouw (op het raamprofiel).
- **Wat er in het pantser zit:** klassieke lamellen of Fusion-lamellen; en
  bediening op netstroom of op zonne-energie.

Dat betekent dat elke productpagina moet zeggen waar hij op die assen staat,
anders leest de bezoeker zes keer hetzelfde. Concreet: SolarBox is een
voorzetrolluik op zonne-energie, SolarFuse is een voorzetrolluik op
zonne-energie met Fusion-lamellen. Beide staan dus ook op de
voorzetrolluiken-pagina, en dat is geen dubbeling maar de kruising van de
twee assen.

---

## 2. Merknamen in dit gamma

| Naam | Wat het is |
|---|---|
| **SolarBox** | Voorzetrolluik op zonne-energie, klassieke lamellen |
| **SolarFuse** | Voorzetrolluik op zonne-energie, Fusion-lamellen |
| **Fusion** | Voorzetrolluik op netstroom, Fusion-lamellen |
| **XSMAX** | Optionele motor en oprolas, 40 cm extra oprolhoogte |

---

## 3. Feiten uit de brochure

Bron: `Winsol-brochure-rolluiken-nl.pdf` (winsol-drive, Former Website
Library Documents). Alles hieronder staat er letterlijk in.

**Voorzetrolluiken**
- Tegen de gevel of in de dagopening gemonteerd, geen kapwerk
- 5 kastmaten: 137, 150, 165, 180 en 205 mm
- 3 kastvormen: 45° afgeschuind, afgerond, rond
  (afgerond niet bij 137 en 165 mm; rond niet bij 150 mm)
- Een raam met voorzetrolluik isoleert **tot 32 % beter** dan een raam zonder

**SolarBox (zonne-energie)**
- 2 ingewerkte zonnepanelen, geen kabels of stopcontact nodig
- Installatie volledig langs de buitenkant
- 4 Ah-batterij
- Rechte kast, **4 kastmaten: 137, 150, 165 en 180 mm**
- Afwerkingen: Standard (symmetrisch of asymmetrisch), Black Belt, Colour

> **Let op — afwijking van de oude site.** winsoldilbeek.be schrijft vijf
> kastmaten voor de solar-variant (137, 150, 165, 180, 205). De brochure
> geeft er vier en noemt het product "NEW". De brochure is leidend; de
> 205 mm hoort bij de gewone voorzetrolluiken.

**Inbouwrolluiken**
- In de spouwmuur boven raam of deur, geen kast zichtbaar op de gevel
- Vraagt voldoende plaats in de spouwmuur, en een omkasting binnen mét isolatie
- Mechanisme onbereikbaar voor indringers dankzij de afgesloten kast
- Dubbelwandige lamellen in pvc of aluminium (gevuld met polyurethaanschuim),
  onderlatten en geleiders in geëxtrudeerd aluminium

**Opbouwrolluiken**
- Kast in de fabriek al op het raamprofiel gemonteerd, kant-en-klaar geleverd
- Voor renovatie of nieuwbouw mét nieuwe ramen, of wanneer er te weinig
  ruimte is boven het raam
- Raam en kast uit één stuk, dus volledig lucht-, water- en winddicht
- Behoren tot de best isolerende opbouwrolluiken op de markt

**Lamellen**
- Pvc: goedkoper, hoge isolatiewaardes, in de massa gekleurd
- Aluminium: sterker, dus beter voor grote rolluiken; bevat polyurethaanschuim
- Beide onderhoudsvriendelijk
- Keuze uit lamellen met daglichtsleuven

**Bediening**
- Elektrisch, via domotica, op zonne-energie of manueel

---

## 4. Feiten over Fusion (niet in de brochure)

De rolluikbrochure bevat Fusion niet. Deze feiten komen van
winsoldilbeek.be, `/Ons-aanbod/Rolluiken/Rolluiken-met-Fusion-lamellen/`:

- Afzonderlijke lamellen met microperforaties over de volledige breedte
- Tot **vier keer meer daglicht** dan een klassiek rolluik
- Lichtinval regelbaar van 100 % tot 3 %
- Zicht naar buiten blijft behouden, zoals bij een screen, maar het rolluik
  kan wél volledig dicht voor totale verduistering
- Verluchten met de insecten buiten: doet ook dienst als muggenhor

---

## 5. Beeldinventaris

Bron: `/Users/arnaud/Documents/winsol/afbeeldingen/rolluiken` (112 bestanden).

> De beeldenbank is tussen batch 2 en 3 herschikt. Wat in de eerste scan nog
> onder `luifels/` stond, staat nu onder `rolluiken/`. De scan is opnieuw
> gedraaid; oudere padverwijzingen naar `luifels/…rolluiken…` kloppen niet meer.

| Groep | Totaal | Watermerk | Schoon | Breed genoeg voor sectie |
|---|---|---|---|---|
| SolarBox / zonne-energie | 71 | 43 | 28 | 11 |
| Voorzet / mini-caisson | 2 | 0 | 2 | 1 |
| Fusion | 0 | 0 | 0 | 0 |
| Overige realisaties (Liedekerke) | 41 | 41 | 0 | 0 |

> **De Liedekerke-set hoort grotendeels niet bij deze batch.** De 41 foto's in
> `rolluiken/LR/` heten `..._Allura_Poort_Rolluiken (n).jpg`, maar bij
> steekproef blijken het vooral detailopnames van een garagepoort en een
> Allura-voordeur. Slechts één opname toont daadwerkelijk een rolluik: `(36)`,
> een interieurbeeld waarop het opgerolde inbouwrolluik boven het raam te zien
> is. De rest is materiaal voor batch 4 (Garagepoorten). Niet blind uit deze
> map plukken op basis van de bestandsnaam.

**Wat dat betekent per product:**

- **Voorzetrolluiken** — `OneDrive_2_31-07-2026/Voorzetrolluiken - Volets
  roulants mini-caisson 2.jpg` (4484×3363, schoon) is een leerboekvoorbeeld:
  zichtbare 45°-kast op een bestaande gevel.
- **Rolluiken op zonne-energie** — het rijkst bedeeld. De map `Renders/`
  bevat renders van álle vier de afwerkingen (Standard symmetric, Standard
  asymmetric, Black Belt, Colour), wat de designoptie letterlijk toont.
  `2019 onbekend, Izegem/…(8).jpg` is een echte foto waarop het zonnepaneel
  in de kast zichtbaar is.
- **Inbouw- en opbouwrolluiken** — geen beeld dat het onderscheid tóónt. Een
  inbouwrolluik is per definitie onzichtbaar op de gevel, dus een foto van
  het resultaat is een foto van een raam. Hier wordt teruggevallen op de
  Liedekerke-realisaties (met watermerk, gemarkeerd voor `clean-watermarks`).
- **Fusion en klassieke lamellen** — geen enkele foto. Dit zijn precies de
  producten waar het verschil in het pantser zit, dus juist daar zou beeld
  het meest opleveren. Placeholder plus melding via `winsol:image-gaps`.

**Gericht bij te zoeken:** een detailfoto van een Fusion-lamel met de
microperforaties, en een binnenopname met het licht dat er doorvalt. Dat is
het enige argument van dat product en het staat nu volledig in tekst.

---

## 6. De watermerkdetector miste er één

`rolluiken/Low Res 3/rolluiken_volets-roulants_solarbox_attenhoven_(0698).jpg`
kwam als schoon uit de detectie, maar draagt wél het winsol-logo rechtsonder.
De oorzaak is de meetmethode: de detector telt de fractie bijna-witte pixels
in de rechteronderhoek, en op deze foto is die hoek donkere baksteen waarop
het logo relatief klein is. De drempel van 0,08 wordt dan niet gehaald.

De vlag is handmatig gezet, met `watermark_box` op `1050,865,450,135`. Zonder
die correctie zou `winsol:clean-watermarks` de foto overslaan en zou het logo
op de site blijven staan.

**Regel voor de volgende batches:** de detectie is een zeef, geen bewijs. Van
elke foto die daadwerkelijk in content terechtkomt, de hoek zelf bekijken en
de vlag corrigeren waar nodig. Alleen wat gemarkeerd is, wordt later gesneden.

---

## 7. Schrijfregels

Zoals batch 2: geen gedachtestreepjes in sitecontent. Splits de zin of zet
een komma.
