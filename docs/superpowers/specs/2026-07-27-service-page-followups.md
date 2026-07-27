# Service Page — Follow-ups

**Date:** 2026-07-27
**Status:** Open
**Context:** Surfaced while wiring `/service` together in `2026-07-27-service-page` (taak 5). Niets hiervan blokkeerde die taak; alles staat hier zodat het niet vergeten wordt.

## Follow-ups

- **Het formulier mag niet live voordat de verwerking geregeld is.** `resources/forms/herstelling.yaml` heeft geen `email:`-blok, dus er wordt niets verstuurd — maar Statamic schrijft een POST wél als inzending weg. Dat is een formulier dat in stilte inzendingen opslokt. Daarom staat `seo_noindex: true` op `content/collections/pages/service.md` — dat is de poort die de pagina uit Google houdt zolang de verwerking niet klopt. Wie het `email:`-blok toevoegt, moet in diezelfde wijziging `seo_noindex` terugzetten naar `false`; anders blijft de pagina onvindbaar nadat de verwerking al wél werkt.
- **Het formulier verzamelt persoonsgegevens zonder privacyverklaring of bewaartermijn.** Naam, e-mailadres, telefoonnummer én een foto worden naar schijf geschreven (zie vorig punt) zonder dat de bezoeker iets leest over hoe lang die gegevens bewaard blijven of wie ze inziet.
- **Geen recaptcha.** `partials/recaptcha.antlers.html` bestaat en wordt op `/contact` gebruikt; dit formulier heeft hem niet.
- **Geen drag-and-drop-feedback.** Slepen werkt, maar er is geen highlight tijdens het slepen en de gekozen bestandsnaam wordt niet getoond. Dat is JavaScript-werk.
- **Vier dummy-servicefoto's.** Te vervangen zodra de echte beelden er zijn.
- **`/service` staat niet in de navigatie.** `content/trees/navigation/main.yaml` bevat alleen *Over ons*, *Projecten* en *Contact*; Aanbod en Realisaties ontbreken er ook. De nav is als geheel achterstallig.
- **Vijfde pill-vormige knop.** `.section-nav__link` herhaalt de vormdeclaraties van `.btn--accent`, `.btn--outline`, `.btn--dark` en `.btn--cta`. De `.btn--pill`-extractie staat open in `2026-07-26-pagebuilder-sections-followups.md`.
- **De checklists uit het design zijn niet gebouwd.** Figma-nodes `318:3008`, `318:3029`, `318:3052` en `318:3073` staan op `hidden`. `textImage` heeft er een `features`-argument voor als ze terugkomen.
- **`#f5f5f5` en `#bfbfbf` zijn arbitraire waarden in `form.css`.** Geen `@theme`-token, omdat ze alleen in dit component voorkomen. Komt er een tweede formulier met dezelfde vulling, dan horen ze in `site.css`.
- **`Field::get('fields')` lost geen `import:`-verwijzingen op** (bv. `reparation.image` in `services_overview.yaml`, die naar de gedeelde `image`-fieldset wijst). Die expansie gebeurt pas wanneer de Group-fieldtype zijn eigen `Fields`-collectie bouwt via `fieldtype()->fields()` — precies hoe de CP en augmentatie het veld consumeren. Blueprinttests die een geneste group inspecteren moeten dus via die laatste gaan, niet via de ruwe `get('fields')`-accessor.

## Uitgesteld tijdens de reviews

Punten die de per-taak-reviews en de eindreview als Minor beoordeelden. Geen ervan blokkeerde de oplevering; ze staan hier zodat ze niet opnieuw ontdekt hoeven te worden.

- **`sectionNav` rendert de `#herstelling`-pill onvoorwaardelijk.** Zolang er services zijn, verschijnt die knop — ook op een `services_overview`-entry zónder `reparation`-group, waar hij naar een anker wijst dat niet bestaat. De andere pills hebben wél een `{{ if overline }}`-guard. Een `{{ if reparation }}` eromheen lost het op, maar breekt `ServiceNavTest::test_links_to_the_reparation_section`, die alleen `services` in context zet; die test moet dan mee.
- **`reparation.text` rendert zonder `<p>`.** Dat veld is een `textarea`, geen `bard`, dus de opgeslagen waarde is een kale string die in `<div class="rich-text">` belandt. Zelfde typografieprobleem als de vier servicteksten hadden, maar de oorzaak zit in de vorm van het blueprintveld en niet in de content — het is dus geen kwestie van `<p>` in de entry zetten.
- **`/contact` staat er anders bij dan voorheen.** De oude `form.css` gaf `space-y-4` (16px) tussen velden; `.form` geeft nu `gap-8 lg:gap-12` (32/48px). De pagina rendert prima en is duidelijk nog een placeholder — Engelse labels, "Submit", `{{ recaptcha }}` buiten de `<form>` — maar dit is een ongereviewde wijziging aan een live pagina zonder testdekking.
- **De `.btn--pill`-notitie klopt niet helemaal.** `.section-nav__link` gebruikt `py-4`, de vier `btn--*`-pills gebruiken `py-5`. Ze delen dus níet exact dezelfde vormdeclaraties; de knoppen verschillen 8px in hoogte. Vóór de extractie moet uitgezocht worden of dat verschil uit Figma komt of per ongeluk is.
- **Drie kopieën van dezelfde haarlijn.** `headers/default` (`.page-header__divider`), `sectionNav` en `.form-section + .form-section` gebruiken alle drie `border-black/10`. Nog niet de moeite van abstraheren; bij een vierde wel.
- **`test_is_hidden_below_the_lg_breakpoint` kan alleen falen bij een typefout.** Hij toetst een hardgecodeerde klassenstring zonder vertakking. Bewust behouden als tripwire.
- **Divider `/10` versus pills `/12`.** Twee procent alpha, onzichtbaar. Let op: de designspec beweert in §3 dat de pills de headerlijn volgen, maar die lijn is `/10`. Als er één waarde moet winnen is het waarschijnlijk `/10`, niet andersom.
- **Geen `accept="image/*"` op de file-input.** Die wordt door een vendor-fieldtype-view gerenderd die dat attribuut niet accepteert; forceren betekent de view overriden, en dat is het niet waard.

## Fouten in de brondocumenten

Gevonden tijdens de uitvoering, gecorrigeerd in het plan maar niet in de spec. Alleen verwarrend als de spec later als actueel gelezen wordt.

- `2026-07-27-service-page-design.md` §2 schrijft `index % 2 == 0` voor. `index` is 0-gebaseerd, dus dat draait de afwisseling om. De implementatie gebruikt `count`, dat 1-gebaseerd is.
- Diezelfde spec §3 schrijft de headerlijn `border-black/12` toe; het is `border-black/10`.
