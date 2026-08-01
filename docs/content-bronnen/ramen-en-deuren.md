# Bronblad — Ramen en deuren

**Datum:** 2026-08-01
**Batch:** 2 van 9 (project 2)
**Vlaggenschipproduct:** Aluminium ramen & deuren

Zelfde werkwijze als batch 1: bronnen eerst, content daarna. Bronhiërarchie
volgens `docs/superpowers/specs/2026-07-31-winsol-brebo-content-design.md` §4.1.

---

## 1. Het gamma

Geverifieerd door de links van `winsol.eu/nl-be/ramen` en `/deuren` uit te
lezen, niet door URL's te raden. Winsol hanteert daar twee URL-families naast
elkaar: `/ramen` en `/deuren` als aparte ingangen, én `/ramen-en-deuren/...`
voor de producten zelf.

| Oude site | winsol.eu vandaag |
|---|---|
| Aluminium ramen & deuren | `/ramen-en-deuren/aluminium` |
| Aluminium ramen | `/ramen/aluminium` |
| PVC ramen | `/ramen-en-deuren/pvc` |
| PVC deuren | `/deuren/pvc` |
| Vliegenramen | `/ramen-en-deuren/vliegenramen` |
| Sierluiken | `/ramen-en-deuren/sierluiken` |
| Steellook | `/ramen-en-deuren/aluminium/steellook` |
| Veiligheidsdeuren (merk Nidium) | **niet gevonden** |
| — | nieuw: `/ramen-en-deuren/aluminium-schuiframen` |
| — | nieuw: `/ramen-en-deuren/pvc-schuiframen` |

**Beslissingen van de eigenaar.** Eén range blijven, niet splitsen zoals
winsol.eu: dat volgt winsoldilbeek, waar het SEO-werk op gestuurd heeft, en
houdt de 301 een rechtstreekse mapping. En dezelfde lijn als batch 1 voor het
gamma: veiligheidsdeuren vervalt met een 301 naar de rangepagina, de twee
schuiframen komen erbij.

Dat geeft negen producten:

1. Aluminium ramen & deuren (vlaggenschip)
2. Aluminium ramen
3. PVC ramen
4. PVC deuren
5. Aluminium schuiframen
6. PVC schuiframen
7. Vliegenramen
8. Sierluiken
9. Steellook

**Stalen binnendeuren stond op de oude rangepagina** als eerste blok, maar is in
het nieuwe model een eigen range. Wordt hier dus een verwijzing, geen
productkaart. Zelfde situatie als Somfy bij terrasoverkapping.

---

## 2. Schrijfregels

- **Geen gedachtestreepjes.** Splits de zin of gebruik een komma. Vaste
  voorkeur van de eigenaar.
- H1 en primair zoekwoord van de oude pagina blijven staan. Het title-patroon
  draait om naar `<Zoekwoord> op maat | Winsol Dilbeek, Sint-Pieters-Leeuw &
  Aartselaar`.

---

## 3. Beeld

Gemeten met `App\Services\WatermarkDetector` over de 309 foto's in
`afbeeldingen/ramen-en-deuren/`. Dit is veruit de best gedekte range: 302 van de
309 zijn schoon.

| Product | Totaal | Watermerk | Schoon | Hero (≥2000px) | Sectie (≥1400px) |
|---|---|---|---|---|---|
| Aluminium, reeks **Allura** | 108 | 1 | 107 | 28 | 59 |
| PVC, reeks **C+70** | 167 | 6 | 161 | 25 | 102 |
| Steellook (Hi) | 11 | 0 | 11 | 1 | 1 |
| Schuiframen | 10 | 0 | 10 | 0 | 0 |
| C+70 Retro | 13 | 0 | 13 | 0 | 3 |

**Geen beeld voor vliegenramen en sierluiken.** Die twee krijgen een placeholder
uit `assets/placeholder/`, gemeld door `php please winsol:image-gaps`.

De reeksnamen zijn bruikbaar als filter: `Allura` voor aluminium, `C+70` voor
pvc, `Steellook` en `schuif` voor de rest. Realisaties zijn gespreid over
Ardooie, Oekene, Gent, Aalbeke, Beauraing, Zottegem en Boutersem, dus hier is
wél variatie beschikbaar, anders dan bij terrasoverkapping.

---

## 4. Brochures

| Bestand | Hoort bij |
|---|---|
| `Winsol_Brochure_Ramen-en-deuren-in-ALU_NL.pdf` | Aluminium ramen & deuren, aluminium ramen, steellook, aluminium schuiframen |
| `Winsol_Brochure_Ramen-en-deuren-in-PVC_NL.pdf` | PVC ramen, PVC deuren, PVC schuiframen |

Vliegenramen en sierluiken hebben geen eigen brochure.

---

## 5. Oude copy, verbatim

Overgenomen van winsoldilbeek.be op 2026-08-01, navigatie- en footerruis
verwijderd. Bron voor zoekwoorden en voor wat Winsol Brebo zelf belooft, niet om
te herpubliceren.


### Ramen en deuren (range)

> Ramen en deuren Ramen en deuren bepalen de look van je woning en geven je huis een volledig nieuw gezicht. Winsol maakt absolute topkwaliteit op maat. Dankzij het isolerende effect van onze ramen en deuren bespaar je ook nog eens op je energiefactuur. Stalen binnendeuren Stalen deuren zijn een grote trend en worden tegenwoordig steeds vaker geentegreerd in interieurconcepten. Dit gaat van modern tot retro, enkel tot dubbel, pivoterend tot standaard deuren. Lees meer... Aluminium ramen & deuren Ontdek het uitgebreid assortiment aluminium ramen en deuren bij Winsol. Geniet van thermische en akoestische isolatie, een esthetische look en verhoogde veiligheid. Bezoek onze showroom en laat je adviseren door onze experts. Lees meer... Aluminium ramen Winsol biedt aluminium ramen met ultradunne profielen en hoogwaardige afwerkingen. Ontdek waarom je voor Winsol aluminium ramen moet kiezen. Lees meer... PVC Ramen Winsol biedt kwaliteitsvolle PVC ramen op maat, met professionele plaatsing, tegen competitieve prijzen. Ontdek ons uitgebreide gamma. Lees meer... PVC Deuren Onze PVC deuren combineren functionaliteit, stijl en veiligheid, waardoor ze de ideale keuze zijn voor jouw huis. Ontdek ons diverse aanbod, vraag nu een vrijblijvende offerte aan voor betaalbare en op maat gemaakte PVC deuren. Lees meer... Vliegenramen Tijdens de warme zomermaanden wil iedereen genieten van het prachtige weer en de buitenlucht. Vliegenramen houden muggen en andere insecten buiten en zo blijf je een comfortabel verblijf hebben binnenshuis. Geniet dus van de zomer zonder kleine indringers. Lees meer... Veiligheidsdeuren Geniet van een warm en veilig nest met Winsol veiligheidsdeuren. Inspireer je met onze verschillende types Nidium Doors. Lees meer... Sierluiken Smaakvolle PVC sierluiken geven een extra touch aan je thuis. Voeg dat extra vleugje decoratie en romantiek toe aan je huis. Extra uitstraling bekom je met de op maat gemaakte sierluiken van Winsol. Lees meer... Steellook De Steellook is een reeks aluminium profielen, maar zo afgewerkt worden dat ze de aanblik krijgen van staal. Het is een reeks die vooral populair is als designelement in landelijke bijgebouwen en aanbouwen, zoals poolhouses of oranjerieen. Lees meer... Vraag een offerte aan

### Aluminium ramen & deuren

> Aluminium ramen & deuren: een veilig en goed geesoleerd huis voor elke woonstijl div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-45" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false > Ramen en deuren leggen het gezicht van je eigen huis vast. Als je op zoek bent naar een veilig en goed geesoleerd huis dat past bij jouw unieke stijl, dan hoef je niet verder te zoeken dan onze collectie aluminium ramen en deuren. Dankzij hun akoestischeeen inbraakwerende kenmerken, kun je elke dag genieten van gemoedsrust. En het feit dat je energierekening lager zal uitvallenedankzij hun isolerende factoreis mooi meegenomen! e Download brochure: Ramen en deuren STEELLOOK: De uitstraling van staal met de voordelen van aluminium Onze aluminium ramen en deuren zijn voorzien van ultradunne profielen en hebben de authentieke uitstraling van vroeger. Het is een esthetische keuze die past bij alle bouwstijlen en bovendien zorgt voor een opvallend element in je woning. e NEEM CONTACT MET ONS OPe⮕ Superieure isolatieprestaties met aluminium schrijnwerk div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-47" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false data-cycle-prev="#slideshow-previous-paragraph-47" data-cycle-next="#slideshow-next-paragraph-47" > Als je bouwt of verbouwt met het oog op de toekomst, is er geen ontkomen aan: isolatie is de boodschap. Dat geldt voor alle delen van je woning, van het dak tot de muren en het aluminium buitenschrijnwerk. De aluminium ramen en deuren van Winsol beschikken stuk voor stuk over zeer goede isolerende eigenschappen, zodat je de warmte binnen en de kou buiten houdt. En tijdens warme dagen zorgen onze aluminium ramen dan weer voor wat verkoeling in huis, al dan niet in combinatie met moderne screens of stijlvolle rolluiken. Aluminium ramen en deuren voor elke bouwstijl Of je nu op zoek bent naar aluminium ramen voor een landelijke of moderne stijl?eWinsol aluminium ramen passen bij elke woning, of die nu nieuw is of al een aantal jaren op de teller heeft. Je kunt kiezen uit zwarte aluminium ramen, witte ramen of ramen in een andere kleur of afwerking. De keuze is aan jou! Hulp nodig? Onze adviseurs in de showroom helpen je graag bij het maken van de juiste keuze. ✓ 4 kleurafwerkingen - Geef je ramen net dat beetje extra met een matte, glanzende, gestructureerde of metallic afwerking. ✓ Exclusieve kleuren - Voeg extra stijl toe met anodisatiekleuren zoals natuur, goud, champagne, koper en zwart. ✓ Standaard voorbehandeling - Alle Winsol aluminium ramen zijn hierdoor beter bestand tegen corrosie. e NEEM CONTACT MET ONS OP e⮕ e

### Aluminium ramen

> Aluminium ramen div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-594" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false > Aluminium ramen bestellen, kopen, en laten installeren voor je huis of veranda Aluminium ramen zijn de perfecte combinatie van ultradunne profielen en een authentieke uitstraling. Deze ramen vormen een opvallend element in je woning, waardoor je huis zowel van binnen als van buiten een eigentijdse uitstraling krijgt. Aluminium ramen en zijn voordelen Aluminium staat bekend om zijn isolerende eigenschappen. Onze Winsol aluminium ramen bieden een ideale balans tussen warmte en koude, waardoor je huis comfortabel blijft, ongeacht het seizoen. In de zomer zorgen ze voor verkoeling, terwijl ze in de winter voor extra isolatie zorgen. Maar dat is niet alles; onze aluminium ramen zijn niet alleen functioneel maar ook esthetisch aantrekkelijk. Ze zijn van hoogstaande kwaliteit en tot in het kleinste detail prachtig afgewerkt. Wil je je huis nog verder verfraaien? Overweeg dan de combinatie van aluminium ramen met moderne screens , stijlvolle rolluiken of aluminium deuren. e Passend bij elke stijl Op zoek naar aluminium ramen voor een landelijke of moderne stijl? Winsol aluminium ramen passen bij elke woning. Ben je aan het verbouwen of hebben jouw ramen zijn beste tijd gehad? Wij hebben de perfecte oplossing voor jou. Bij Winsol heb je keuze uit verschillende kleuropties, waaronder zwarte aluminium ramen, witte ramen, en andere kleuren en afwerkingen. De keuze is volledig aan jou, en als je hulp nodig hebt bij het maken van de juiste keuze, staan onze adviseurs in de showroom klaar om je te begeleiden. 4 afwerkingsmogelijkheden Geef je ramen dat extra vleugje stijl met een matte, glanzende, gestructureerde of metallic afwerking. Exclusieve kleuren Voeg nog meer stijl toe met anodisatie kleuren zoals natuur, goud, champagne, koper en zwart. Standaard voorbehandeling Alle Winsol aluminium ramen genieten van een lange levensduur dankzij hun voorbehandeling. Op maat gemaakt Elk huis is uniek, en daarom bieden wij de mogelijkheid om je aluminium deur volledig op maat te laten maken, zodat deze perfect bij jouw woning past. Professionele installatie Ons ervaren team van vakmensen installeren en monteren zorgvuldig jouw aluminium deur en/of raam. Prijs en installatie van aluminium ramen Winsol biedt een eerlijke prijs die gepaard gaat met een professionele, vlotte plaatsing op maat. Vraag jouw vrijblijvende prijsofferte op. e Contacteer ons

### PVC ramen

> PVC ramen div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-552" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false data-cycle-prev="#slideshow-previous-paragraph-552" data-cycle-next="#slideshow-next-paragraph-552" > PVC ramen kopen? Jouw keuze, jouw stijl! Elke woning is uniek, daarom bieden wij bij Winson PVC ramen aan in verschillende stijlen. Of je nu op zoek bent naar moderne PVC ramen of een meer klassieke uitstraling wilt behouden, Winsol heeft de oplossing voor jou. e Wat is de prijs van PVC ramen? Wij bieden competitieve prijzen zonder in te leveren op kwaliteit. Onze PVC ramen zijn duurzaam en energiezuinig, wat betekent dat je niet alleen bespaart op de aanschafkosten, maar ook op lange termijn op jouw energiefactuur. Dit is dus een win-win situatie. Contacteer ons PVC ramen op maat inclusief plaatsing/ installatie Wij bieden PVC ramen op maat aan zodat je gekozen raam perfect past in je woning. We passen onze ramen aan op basis van jouw specifieke behoeften en afmetingen, zodat je zeker weet dat ze perfect in jouw woning passen. Bij Winsol geloven we in een probleemloze ervaring voor onze klanten. Ons team van ervaren vakmensen staat klaar om je PVC ramen professioneel te plaatsen, zodat je er geen omkijken naar hebt. Kies voor Winsol PVC ramen en investeer in stijl, comfort en kwaliteit voor jouw woning. Of je nu op zoek bent naar moderne PVC ramen, een op maat gemaakte oplossing wilt of gewoon nieuwsgierig bent naar de prijzen, Winsol heeft de perfecte PVC ramen voor jou. Ontdek ons uitgebreide aanbod en kom langs in onze showroom voor advies. Bij Winsol staan jouw wensen centraal!

### PVC deuren

> PVC Deuren div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-555" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false data-cycle-prev="#slideshow-previous-paragraph-555" data-cycle-next="#slideshow-next-paragraph-555" > Jouw PVC deur, jouw stijl Wij bieden PVC deuren aan in verschillende stijlen, van klassiek tot modern. Bij Winsol begrijpen wij dat je een PVC deur niet zomaar koopt, de deur van jouw huis is niet alleen een functionele toegang, maar ook een belangrijk onderdeel van de uitstraling van jouw huis. Jouw stijl, jouw keuze.e Hoogwaardige PVC deuren hoeven niet duur te zijn. Winsol biedt prijzen voor PVC deuren zonder in te boeten op kwaliteit. Onze PVC deuren zijn duurzaam en energiezuinig, wat betekent dat je niet alleen bespaart op de aanschafkosten, maar ook op lange termijn op energiekosten. Contacteer ons PVC deuren op maat gemaakt We passen onze deuren aan op basis van jouw specifieke behoeften en afmetingen, zodat je zeker weet dat ze perfect bij jouw huis passen. e PVC deuren laten installeren / professionele installatie op maat Bij Winsol streven we naar een zorgeloze ervaring voor onze klanten. Ons team van ervaren vakmensen staat klaar om jouw PVC deur professioneel te installeren, zodat je er geen omkijken naar hebt. Kies voor Winsol PVC deuren en investeer in stijl, veiligheid en comfort voor jouw woning. Of je nu op zoek bent naar een moderne PVC voordeur, een op maat gemaakte oplossing zoekt, of gewoon nieuwsgierig bent naar de prijzen, Winsol heeft de perfecte PVC deuren voor jou. Ontdek ons uitgebreide aanbod en neem contact met ons op voor meer informatie. Bij Winsol staan jouw behoeften centraal!

### Vliegenramen

> Vliegenramen div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-39" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false > Tijdens de warme zomermaanden wil iedereen genieten van het prachtige weer en de buitenlucht. Vliegenramen houden muggen en andere insecten buiten en zo blijf je een comfortabel verblijf hebben binnenshuis. Geniet dus van de zomer zonder kleine indringers. Download brochure: Ramen en deuren Op maat van je raam of deur Onze vliegenramen zijn verkrijgbaar in verschillende soorten modellen. Ze worden altijd op maat gemaakt, zodat ze voor het beste comfort zorgen. Winsol vliegenramen kunnen enkel toegepast worden op Winsol raamprofielen. Leve de zomer Genieten op je terras en de buitensfeer beleven is puur genot. Met een vliegenraam hou je muggen en andere insecten buiten en blijf je een comfortabel verblijf hebben binnenshuis. Geniet dus van de zomerbriesjes ook in huis.

### Sierluiken

> Sierluiken div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-32" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false > Een extra snuifje buitendecoratie toevoegen aan je huis kan een wereld van verschil betekenen.eSierluiken zorgen voor een warme en eigentijdse look. Download brochure: Ramen en deuren Sierluiken op maat van je woning Wij ontwerpen versterkte vaste of bedienbare sierluiken in verschillende uitvoeringen. Wij zijn al 100 jaar vakman in de regio. Decoratieve sierluiken Wij ontwerpen versterkte vaste of bedienbare sierluiken in verschillende uitvoeringen. Wij zijn al 100 jaar vakman in de regio. Decoratieve sierluiken Smaakvolle PVC sierluiken geven een extra touch aan je thuis. Voeg dat extra vleugje decoratie en romantiek toe aan je huis. Extra uitstraling bekom je met de op maat gemaakte sierluiken van Winsol.

### Steellook

> STEELLOOK HI: De cottage design reeks div" data-cycle-fx="fade" data-cycle-pager="#slideshow-pager-pager-paragraph-17" data-cycle-pager-template=" " data-cycle-pause-on-hover="true" data-cycle-speed="1000" data-cycle-swipe=true data-cycle-log=false > DeeSTEELLOOKeis een reeks aluminium profielen, maar zo afgewerkt worden dat ze de aanblik krijgen van staal. Het is een reeks die vooral populair is als designelement in landelijke bijgebouwen en aanbouwen, zoals poolhouses of oranjerieen. Download brochure: Ramen en deuren Cottage design Kenmerkend zijn de zeer slanke profielen en de opgekleefde kleinhoutverdelingen. Zo wordt het typische ‘cottage design’ effect gerealiseerd: landelijk maar toch behoorlijk strak. Deze stijl komt het best tot zijn recht in rustieke woningen die tijdens een renovatie toch een modern tintje moeten krijgen. Energiezuining DeeSTEELLOOKevoldoet aan alle eisen op vlak van isolatie, dankzij de ‘spider-technologie’. De profielen zijn aan de binnenkant opgedeeld in verschillende isolerende luchtkamers, zodat de warmte binnen blijft en de koude buiten wordt gehouden. Daar voegen we ook nog ‘warm-edge spacers’, zodat er geen thermische overdracht is tussen het profiel en het glas. Zo vermijden we condensatie aan de binnenkant van de ramen. Ramen op jouw maat gemaakt De ene landelijke woning is natuurlijk de andere niet. En bij de renovatie ervan wil je natuurlijk graag je eigen accenten leggen. Met Winsol als partner kun je echt elk raam personaliseren: we werken immers altijd op maat. Of het nu gaat over speciale afmetingen en kleurencombinaties of specifieke afwerkingen: we doen steeds ons best om aan jouw eisen tegemoet te komen. Daarbij vervullen we vooral onze rol als adviseur en leggen steeds uit wat de mogelijkheden zijn en waarom. Kleuren De kleur van je aluminium profielen kan je zelf kiezen. Door ons uitgebreid kleurenaanbod hoef je je geen zorgen te maken dat je aluminium schrijnwerk uit de toon valt. Je gevel en interieur zullen perfect in balans zijn met elkaar. Heb je toch moeite met het uitkiezen van een kleur, dan kunnen wij je zeker helpen om de juiste beslissing te nemen voor de kleur die het beste past bij je woning.

### Veiligheidsdeuren

Vervalt. Niet overgenomen; 301 naar de rangepagina.

### Aluminium en PVC schuiframen

Bestaan niet op winsoldilbeek. Bron is uitsluitend de ALU- en PVC-brochure plus
`winsol.eu/nl-be/ramen-en-deuren/aluminium-schuiframen` en `/pvc-schuiframen`.
