# Bronblad — Terrasoverkapping

**Datum:** 2026-08-01
**Batch:** 1 van 9 (project 2)
**Vlaggenschipproduct:** Pergola SO!

Dit blad verzamelt de bronnen vóór er één regel content geschreven wordt, zodat
bij elke review na te gaan is waar een zin vandaan komt. Bronhiërarchie volgens
`docs/superpowers/specs/2026-07-31-winsol-brebo-content-design.md` §4.1:
winsoldilbeek is leidend voor wélke producten bestaan en voor de zoekwoorden,
de pdf's zijn de enige toegestane bron voor feiten, en winsol.eu is leidend voor
uitleg en sectieopbouw — maar wordt **nooit gekopieerd**.

---

## 1. Het gamma is veranderd sinds winsoldilbeek

De oude site en het huidige Winsol-gamma lopen uiteen. Geverifieerd door de
links van `winsol.eu/nl-be/terrasoverkapping` uit te lezen, niet door URL's te
raden.

| Product | winsoldilbeek | winsol.eu vandaag |
|---|---|---|
| Pergola SO! | ✓ (als "V2020") | ✓ `/lamellen/pergola-so` |
| Pergola Z!P | ✓ | ✓ `/doek/pergola-zip` |
| Pergola Z!P CUBE | ✓ | ✓ `/doek/pergola-zip-cube` |
| Win Cube | ✓ | ✓ `/doek/wincube` |
| Pergola ORIG!N | — | ✓ `/lamellen/pergola-origin` |
| Patiola | ✓ | **niet gevonden** |

**Beslissing van de eigenaar:** het huidige gamma van winsol.eu volgen. Dat
geeft vijf producten; alleen Patiola vervalt, met een 301 naar de rangepagina.
ORIG!N komt erbij en heeft dus geen oude copy — die pagina wordt volledig
nieuw geschreven uit de brochure en winsol.eu.

**De SO!-familie heeft dakvarianten.** De ORIG!N-pagina noemt Pergola SO!
Classic, Climate, Cocoon en Crystal als verwante producten, en daar horen vier
van je brochures bij. Die varianten worden secties bínnen de Pergola SO!-pagina,
geen aparte producten — winsoldilbeek kent ze niet en het SEO-werk stuurt op
"Pergola SO!".

---

## 2. Twee open punten voor de rangepagina

**De slug wijkt af van het zoekwoord.** De range heet in de repo
`Terrasoverkappingen & pergola's` met slug `pergolas`. De oude site gebruikt
`Terrasoverkapping` als H1 én in de URL. §4.1 zegt dat H1 en primair zoekwoord
blijven staan. Voorstel: titel en H1 worden `Terrasoverkapping`, slug wordt
`terrasoverkapping`. Dat maakt de 301 van `/nl/Ons-aanbod/Terrasoverkapping/`
een rechtstreekse mapping in plaats van een omleiding naar een ander woord.

**Somfy Smart Home stond op de oude rangepagina** als zesde blok, maar is in het
nieuwe model een eigen range. Op de terrasoverkappingpagina wordt dat dus een
verwijzing, geen productkaart.

---

## 3. Beeld — wat er is en wat ontbreekt

Gemeten over de 396 foto's in `afbeeldingen/terrasoverkappingen/`, met de
watermerkdetector uit `App\Services\WatermarkDetector`.

| Product | Totaal | Watermerk | Schoon | Hero-waardig (≥2000px, liggend) |
|---|---|---|---|---|
| Pergola SO! | 273 | 218 | 55 | **42** |
| Z!P CUBE | 49 | 48 | 1 | 0 |
| Pergola Z!P | 38 | 33 | 5 | 0 |
| Win Cube | 26 | 24 | 2 | 0 |
| Pergola ORIG!N | 5 | 5 | 0 | 0 |
| niet toegewezen | 4 | 2 | 2 | 1 |

**Elk product heeft beeld** — geen enkele pagina hoeft op een placeholder terug
te vallen. Maar de verdeling is scheef op twee manieren.

Ten eerste is alleen Pergola SO! hero-waardig gedekt. De andere vier hebben
geen enkele schone liggende foto van ≥2000px, dus die krijgen een watermerkfoto
in de kop. `winsol:clean-watermarks` snijdt die later weg.

Ten tweede, en dat is de echte beperking: **alle 42 schone liggende SO!-foto's
komen uit één realisatie in Drongen.** De schone set biedt dus geen variatie.
Voor de kop en de tekstblokken is dat prima — één huis van meerdere kanten leest
als één project — maar een galerij die uitsluitend Drongen toont wordt eentonig.
Daarom halen we de variatie uit watermerkfoto's van andere realisaties
(Oostkamp, Melle) en uit de ORIG!N-renders.

De beste map is `terrasoverkappingen/web/`: schoon, 2250×1502, liggend.

*Correctie 2026-08-01: een eerdere versie van dit blad meldde dat Pergola Z!P,
Win Cube en ORIG!N géén beeld hadden. Dat kwam door een zoekregel die `Z!P` niet
matchte — er werd op `z!ip` gezocht in plaats van op `z!p`. De tabel hierboven is
de gecorrigeerde telling.*

---

## 4. Brochures

Vijf van de dertien pdf's horen bij deze range:

| Bestand | Hoort bij |
|---|---|
| `Winsol-Brochure_SO-Classic-Climate_2025_NL.pdf` | Pergola SO! — varianten Classic en Climate |
| `WINSOL_SO-Crystal_Brochure_NL_Single.pdf` | Pergola SO! — variant Crystal (glazen dak) |
| `winsol_brochure_so-cocoon_nl.pdf` | Pergola SO! — variant Cocoon |
| `Winsol-Brochure-Pergola-ZIP-NL.pdf` | Pergola Z!P én Z!P CUBE |
| `Pergola-ORIGIN_2025_NL.pdf` | Pergola ORIG!N |

Win Cube heeft geen eigen brochure; de oude site verwees naar de algemene
zonwerings- en outdoor-livingbrochure.

---

## 5. Sectieopbouw volgens winsol.eu

Elke productpagina daar volgt hetzelfde stramien. Overnemen als *structuur*,
niet als tekst:

1. H1 met een belofte, niet met de productnaam alleen
2. `Kenmerken van <product>` — korte opsomming
3. één uitgelicht technisch kenmerk (verlichting, bediening, regensensor)
4. `<product> op maat` / personalisatie
5. `Technische eigenschappen`
6. verwante producten
7. brochure-CTA

---

## 6. Oude copy, verbatim

Letterlijk overgenomen van winsoldilbeek.be op 2026-08-01, met alleen de
navigatie- en footerruis eruit. Dit is de bron voor zoekwoorden en voor wat
Winsol Brebo zelf belooft — niet om te herpubliceren.


### Terrasoverkapping (range)

> Terrasoverkapping Wil je wegdromen in een heerlijk stukje schaduw tijdens een brandende zon? Vermijd je liever een stevige wind? Of hou je het hoofd liever droog wanneer je op jouw terras geniet van een drankje? Dan is een terrasoverkapping de ideale oplossing om het hele jaar door te genieten van jouw terras en tuin.e e Met de juiste terrasoverkapping of pergola kan je je buitenruimte omtoveren tot een gezellige plek waar je beschut zit tegen zon, wind en regen. Winsol biedt een ruim aanbod aan terrasoverkappingen, waaronder de populaire Pergola SO! (V2020), de moderne Pergola Z!P (Cube) en de praktische Win Cube. Daarnaast kan je ook kiezen voor de Patiola, een overkapping met een elegante uitstraling. Om het comfort te verhogen kiezen wij ten slotte voor een Somfy Smart Home oplossing die je terrasoverkapping op afstand bedienbaar maakt. In deze onderstaande pagina’s ontdek je de verschillende mogelijkheden zodat je een weloverwogen keuze kan maken voor jouw ideale terrasoverkapping. Pergola SO! Knus cocoonen in je eigen tuin of terras? Met de Pergola SO! creeer je een woonkamer buiten. Op elk moment van de dag vertoef je er in alle rust en comfort. Lees meer... Pergola Z!P CUBE Deze terrasoverkapping met doek als zonwering is zoals de kers op de taart voor jouw terras. De strakke, rechte lijnen zorgen voor een modern en minimalistisch geheel, waardoor de overkapping ook uitstekend zal passen bij jouw woning met crepi-afwerking of moderne baksteen. Lees meer... Pergola Z!P Snak je naar een aangename plek om te vertoeven tijdens mooie lente-, zomer- en herfstdagen? De Pergola Z!P zorgt voor dat streepje schaduw en beschermt jou en je gezin tegen de zon. Lees meer... Win Cube De Win Cube is een architecturaal hoogstandje die je tuin tot een geheel maakt. Hij kan zowel vrijstaand als tegen de gevel worden gemonteerd. De Win Cube straalt door zijn prachtig design pure klasse uit. De terrasoverkapping is gemaakt van hoogwaardige aluminium onderdelen en is uitgerust met een horizontaal en verticaal doek. Lees meer... Patiola Wil je het hele jaar door genieten van jouw terras? Dan vind je bij Patiola zeker jouw ding. In de zomer kan je je terras bedekken waarbij je de brandende zon omruilt voor een stukje gezellige schaduw. Door het plaatsen van extra zijwanden kan je jouw terras verwarmen in de winter zodat je in een aangename temperatuur op je terras kan vertoeven. Lees meer... Somfy Smart Home Wil je wegdromen in een heerlijk stuk schaduw tijdens een brandende zon? Vermijd je liever een stevige wind? Of hou je het hoofd liever droog wanneer je op jouw terras geniet van een drankje? Dan is een terrasoverkapping de ideale oplossing. Lees meer... Vraag een offerte aan

### Pergola SO!

> Pergola SO! div" Leef buiten met het comfort van binnen De nieuwe terrasoverkapping van Winsol combineert een strak design met revolutionaire snufjes, zodat je ten volle kan genieten van het buitenleven. Personaliseer je Pergola SO! via de vele zijdelingse afsluitingen zoals outdoor gordijnen, houten schuifwanden en screens. Download brochure Pergola SO! Opties SO! Cosy Houd zelf de touwtjes in handen Je bepaalt helemaal zelf de openingsgraad van de lamellen. Via een schuiver bepaal je zelf de gewenste positie van de lamellen (tussen 0e en 145e) Standaard motorisatie Bediening met app Dimbare LED-strip met direct wit licht div" div" SO! Chic Geniet met volle teugen Wil je graag met volle teugen genieten van het buitenleven? Dan hebben we voor jou de geschikte oplossing! zet de motorsturing op automatisch en kies de gewenste verhouding schaduw/zon op je terras! In functie van je geolocatie passen de lamellen zich spontaan aan de stand van de zon aan. Automatische motorisatie Regensensor Aansturing via de app Dimbare LED-strip met direct wit licht - regelbaar warm/koud Bluetooth geluidssysteem met 2 ingebouwde luidsprekers SO! Star Intelligente Solar Tracking Wil je het hele jaar door genieten van je terras? Dankzij de gepatenteerde motorsturing gebaseerd op intelligente solar tracking bepaal je zelf het klimaat op je terras. Met de hulp van de temperatuur- en de zonnesensor wordt de gewenste hoeveelheid zonnestralen op je terras doorgelaten en tover je de Pergola SO! om tot de ultieme plaats om te genieten.e Gepatenteerde intelligente motorsturing Windsensor Regensensor Bediening via de app Dimbare ledstrip met direct wit licht, regelbaar koud/warm Dimbare LED-sterrenhemel Bluetooth geluidssysteem met 4 ingebouwde luidsprekers div" Sfeerwanden Maak het gezellig en knus Onze zijwanden en buitengordijnen maken jouw knusse plekje in de tuin helemaal af. Kies zelf welke afsluiting je wil toevoegen of combineer ze voor een neg chiquer resultaat. div" div" Nieuw - Buitengordijnen Gezellig wapperende gordijnen in de wind… Waan je op Ibiza op het strand met gordijnen als afsluiting voor je SO! Gebruik ze als decoratief element of voor de nodige privacy en bescherming tegen de zon. Verkrijgbaar in meer dan 58 kleuren en 4 stoffen met verschillende patronen Speciaal voor buiten; laat ze het ganse jaar door aan de SO! hangen Stof uit de zeilsport: water- en vuilafstotend, UV- en rotbestendig Combineerbaar met glazen schuifwanden en screens Nieuw - Steellook glazen schuifwand Panoramische vergezichten en een cottage-look voor je Pergola SO!? Dat creeer je zelf via onze glazen schuifwanden met steellook. De glaswand wordt afgewerkt met superslanke zwarte aluminium profielen Enkel beschikbaar in RAL 9005 ST (zwart) Veiligheidsglas (10 mm – ESG/H) div" div" Nieuw – Ambiente lamellenwand Een afsluiting die toch nog een aangenaam briesje wind doorlaat? Deze verticale lamellen zorgen voor extra privacy en verluchting. Personaliseer ze volgens jouw smaak! Verkrijgbaar met of zonder houtlook Manuele bediening of gemotoriseerd via de app Optionele sfeervolle led verlichting in de elementen Houten schuifwanden Ben jij fan van een warme en landelijke stijl? Dan zul je zeker gecharmeerd zijn door deze houten schuifwanden. Gebruik je je SO! ook als poolhouse bijvoorbeeld? Dan is de extra privacy zeker welkom … Onderhoudsvrij Thermowood: afschuren en vernissen is niet nodig! PVC handgrepen + gelakte afwerking in dezelfde kleur als de buitenstructuure Stijlvol Serge-doek langs de buitenzijde voor meer bescherming div" div" Glazen schuifwanden Houd je het graag strak en sober, maar zoek je toch beschutting? Glazen schuifwanden zorgen voor een optimale lichtinval, terwijl je toch beschermd zit tegen de weersomstandigheden.e Veiligheidsglas (10 mm – ESG/H) Geentegreerde meenemers voor extra gebruiksgemak Discrete cylindrische RVS handgreep als extra designelement Onderkant in dezelfde RAL-structuurlak Windvaste screens Dankzij de SolFix screens met ristsysteem is het aangenaam vertoeven onder je Pergola SO!. De screens houden de zonnestralen en frisse briezen tegen. Serge screendoeken met openingsfactor van 1%, 5% en 10% beschikbaar in meer dan 50 kleuren. Voldoen aan de hoogste Europese CE-norm in windresistentie: windklasse 3 (6 Beaufort) De screens kunnen ook achteraf worden gemonteerd div" div" Nieuw – Perspective glaswand Liever een vaste afsluiting, maar toch met de nodige flexibiliteit en doorkijk? Deze glaswand uit 3 delen heeft 3 handige posities: helemaal gesloten voor een optimaal verandaeffect, halfopen voor beschutting tegen de wind, maar wel met voldoende verluchting, en open als een handige afbakening van je terras. Ideaal als afsluiting voor horeca: vergezichten, maar toch beschut Veiligheidsglas van 8 mm dik Max. breedte incl. 2 palen: 4,5 m, max. hoogte: 3 m Gemotoriseerde bediening via de app of druktoetsen Stijlen Een Pergola SO! voor elke stijl We zijn allemaal verschillend. Onze aluminium terrasoverkappingeziet er dan ook bij iedereen anders uit en is helemaal afgestemd op onze eigen smaak en noden. Personaliseer jouw Pergola SO! dankzij de verschillende kleuropties en sfeerwanden en creeer je eigen knusse cocon in de tuin. We maken het graag wat makkelijker voor je met onze pergolaestijlen: cottage, romantisch of modern. Voor welke stijl ga jij? Landelijk & cottage Voel je je op je best te midden van de natuur? Je komt helemaal tot rust met groen om je heen en je houdt van de warme uitstraling van hout? Maar ook de combinatie met zwart staal of aluminium valt bij jou in de smaak? Mix en match houtaccenten met een zwarte of antracietgrijze Pergola SO! en creeer zo je eigen persoonlijke plekje in de tuin. Onze steellook glazen schuifwanden zorgen voor nog meer cottagegevoel. Ook een onderhoudsvriendelijke aluminium pergola met houten accenten en/of het industriele karakter van steellook ramen? Ontdek de ingredienten voor een landelijke SO! hieronder. div" div" Romantisch & huiselijk Beeld het je in... gezellig loungen onder je Pergola SO! na een plons in het zwembad. Of genieten van een sprookjesachtige tete e tete met de belangrijkste persoon in je leven... Sfeervolle led verlichting en muziek zorgen voor de juiste mood. De wapperende gordijnen in de wind zorgen op hun beurt voor privacy en een touch huiselijke gezelligheid. En wanneer de zon 's avonds stilletjes ondergaat, schijnt het laatste geeloranje licht subtiel door de stof heen. Ook romantische momenten beleven in je tuin? Onze outdoor gordijnen en led sterrenhemel transformeren je pergola in de meest idyllische plek op aarde. Wedden dat je niet meer naar binnen wil? Modern & minimalistisch 'Less is more' is jouw credo? Dan is deze stijl iets voor jou! Strak en minimalistisch, rechte lijnen en moderne, onderhoudsvriendelijke materialen. Deze aluminium pergola is jou op het lijf geschreven. Strak gespannen en discreet ingebouwde screens als zonwering en een zijwand van verticale lamellen met sfeervolle led verlichting als beschutting.... Meer hoeft dat voor jou niet te zijn! Jouw kleurenpalet? Zwart en wit. Maar ook antracietgrijs past hier uitstekend bij. Stel jouw eigen moderne en minimalistische SO! samen. Onderstaande elementen helpen je op weg! div" Mag het iets anders zijn? Voel je je niet helemaal thuis bij een van bovenstaande stijlen? Ze zijn jouw ding niet? Geen nood, het is jouw Pergola SO!, jij kiest dus helemaal zelf hoe je die wil afwerken en hoe je overkapping er moet uitzien. Maar we helpen je daar wel graag bij. Kom even langs in de showroom in je buurt en we tonen je alle beschikbare opties, afwerkingen, stalen, doeken enz. Jij kijkt, voelt en kiest wat het beste bij jou past. Inspiratie Laat je inspireren door deze Pergola SO!'s Wil je meer buiten leven en genieten in je tuin? Dan is een Pergola SO! geknipt voor jou!e Maar soms wil je eerst wat voorbeelden eneinspiratieezien omeeen beeld te vormen van deeterrasoverkapping van je dromen. Dat kan op deze inspiratie-pagina met de SO!'s van Belgen die je al voorgingen. Wit, grijs of zwart? Glazen of houten schuifwanden? Met of zonder screens voor de nodige privacy? Sfeervolle verlichting en muziek? Een grote of kleine oppervlakte? Alles kan!e

### Pergola Z!P

> Terrasoverkapping met doek en geentegreerde regenafvoer div" Snak je naar een aangename plek om te vertoeven tijdens mooie lente-, zomer- en herfstdagen? De Pergola Z!P zorgt voor dat streepje schaduw en beschermt jou en je gezin tegen de zon. e Is er geen zon? Dan kan je het oproldoek gewoon laten zitten in de kast en valt het daglicht binnen in huis. Handig voor tijdens de donkere dagen wanneer daglicht heel welkom is. e En wat tijdens het typische Belgische miezerweer? Geen nood, de Pergola Z!P is dankzij de geentegreerde regenafvoer in de palen wel bestand tegen wat regen. Ook de helling van het dak helpt hier mee. Je kan dus gerust blijven zitten tijdens een zomerse regenbui. e Wil je eerder een echte zonneklopper met een strakke, rechte look? Dan is de moderne Pergola Z!P CUBE met plat dak iets voor jou! Download brochure: Pergola Z!P en Z!P CUBE (NL) Genieten in alle comfort De Pergola Z!P met waterdicht doek is uitgerust met heel wat handige snufjes die jouw comfort buiten op het terras verhogen. Wat dacht je van sfeervolle ledverlichting door middel van led-strips rondom de pergola en/of spots? e Maar er is natuurlijk veel meer: e Geentegreerd Bluetooth muzieksysteem 230 V stopcontacten Usb oplaadpunten Volant-plus op zonne-energie (volgens het Black Belt-principe van de SolarFix screens) Infrarood verwarming Regen-, wind- en zonnesensoren Slimme bediening En de bediening van dit alles? Dat doe je gewoon via de handige app op je smartphone of tablet! Wil je je Pergola Z!P liever niet via smartphone bedienen? Vraag dan naar de optionele design drukknoppen. Deze knoppen zijn verlicht en waterdicht en integreren we netjes in een van de palen van je Pergola Z!P terrasoverkapping. Je bedient er niet alleen het uitschuifbaar doek mee, maar ook de verlichting en de screens bijvoorbeeld. Een Pergola Z!P voor elke stijl Winsol pergola's zoals de Pergola Z!P en de Pergola SO! zijn volledig personaliseerbaar. Kies jouw terrasoverkapping volgens de packs (Cosy, Chic, Star) of gewoon e-la-carte samen met onze adviseurs. Jij kiest de kleur: standaard, een andere RAL-kleur of zelfs een houtlook-afwerking op de palen! En de sfeerwanden maken het plaatje helemaal af. Deze afsluitingen zorgen niet alleen voor beschutting, maar zijn tegelijkertijd ook decoratief. Dankzij de verschillende opties is er altijd een wand die past bij de bouwstijl van je huis. Klassiek, landelijk, modern of minimalistisch. Er is een Z!P-oplossing voor iedereen.

### Pergola Z!P CUBE

> Moderne pergola met uitschuifbaar doek div" Deze terrasoverkapping met doek als zonwering is zoals de kers op de taart voor jouw terras. De strakke, rechte lijnen zorgen voor een modern en minimalistisch geheel, waardoor de overkapping ook uitstekend zal passen bij jouw woning met crepi-afwerking of moderne baksteen. Liever een pergola die past bij een landelijke of klassieke stijl? Dan is de Pergola Z!P met schuin dak misschien wat je zoekt. Jij beslist zelf hoeveel schaduw je op je terras wil. Laat daarvoor het doek van de overkapping deels of helemaal uitrollen. En als er geen zon is, dan zit het doek netjes opgerold in de strakke, gesloten kast aan de gevel, net zoals bij een zonneluifel! Download brochure: Pergola Z!P en Z!P CUBE (NL) Gezelligheid troef: ledverlichting Geniet van 's morgens vroeg tot 's avonds laat onder je pergola. Dankzij de verschillende ledverlichtingsopties creeer je zelf de juiste sfeer. Wil je vooral functioneel wit licht om ook wanneer het schemert zonder problemen verder te lezen in je boek? Of zoek je eerder extra sfeermakers zoals gekleurd RGB licht of een diffuus licht dat schijnt op het doek? Ontdek alle mogelijkheden in een van onze showrooms, de mogelijkheden zijn eindeloos! Zo kunnen we zelfs spots integreren in de versterkende dwarsbalk! Wil je een terrasoverkapping met een hoog comfortgehalte? Ook Bluetooth luidsprekers, een volant-plus op zonne-energie, infrarood verwarming, regen-, wind- en zonnesensoren, stopcontacten en USB oplaadpunten zijn mogelijk! Handige bediening, altijd bij de hand! Alle functies van je Pergola Z!P CUBE, zoals het in- en uitschuiven van het doek, de ledverlichting, de Bluetooth speakers, het op- en neerlaten van de ingebouwde screens (optioneel),... bedien je allemaal via de handige smartphone en tablet app! Zo hoef je je nooit af te vragen waar je die afstandsbediening ook alweer hebt achter gelaten! Wil je graag nog een extra bedieningsmogelijkheid voor die momenten wanneer je jouw smartphone niet bij je hebt? Of je net na je duik in het zwembad de screens even wil sluiten voor de nodige privacy? Dan zijn de design drukknoppen in de paal ook een optie! Deze zijn bovendien waterdicht en verlicht! Sfeerwanden: functioneel en decoratief Glazen schuifwanden met of zonder steellook, houten schuifwanden, buiten gordijnen, Ambiente lamellenwand of screens. We hebben een wand voor je pergola in elke stijl! Deze sfeerwanden zijn de perfecte manier om je Pergola Z!P of Z!P CUBE af te werken en ze zorgen voor de nodige beschutting en privacy. En wanneer je kiest voor onze glazen wanden boet je ook niet in op je zicht naar buiten en de daglichtinval. En ook voor horecazaken met een groot terras hebben we de gepaste oplossingen. Zo kunnen we verschillende Pergola Z!P of Z!P CUBE elementen aan elkaar koppelen om een grote oppervlakte te overkappen. Jouw klanten genieten vervolgens beschut en met het nodige comfort van jouw creaties! Onze gemotoriseerde Perspective glaswand die volledig geopend tot op balustradehoogte komt, is de perfecte afsluiting voor jouw terras.

### Win Cube

> Win Cube div" De Win Cube is een architecturaal hoogstandje die je tuin tot een geheel maakt. Hij kan zowel vrijstaand als tegen de gevel worden gemonteerd. De Win Cube straalt door zijn prachtig design pure klasse uit. De terrasoverkapping is gemaakt van hoogwaardige aluminium onderdelen en is uitgerust met een horizontaal en verticaal doek. Download brochure: Zonwering Download brochure: Genieten van outdoor living 100% Winsol Winsol zorgt dat de WinCube uniek is door zijn optionele verlichting via ledstrips. Deze is ingewerkt in de regengoten, die in combinatie met een van de waterdichte doek voor een uitstekende bescherming tegen de regen zorgen. Laagstaande zon Door zijn uniek design beschermt de WinCube je ook tegen een laagstaande zon en garandeert het waterafvoer dankzij een speciaal ontwikkeld systeem. Als je een groot terras hebt kan je er ook voor kiezen om meerde overkappingen aan elkaar te koppelen.

### Patiola

Vervalt. Niet overgenomen; de oude pagina krijgt een 301 naar de rangepagina.

### Pergola ORIG!N

Bestaat niet op winsoldilbeek. Bron is uitsluitend
`Pergola-ORIGIN_2025_NL.pdf` en `winsol.eu/nl-be/terrasoverkapping/lamellen/pergola-origin`.
