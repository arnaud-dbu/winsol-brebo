# Service Page — Follow-ups

**Date:** 2026-07-27
**Status:** Open
**Context:** Surfaced while wiring `/service` together in `2026-07-27-service-page` (taak 5). Niets hiervan blokkeerde die taak; alles staat hier zodat het niet vergeten wordt.

## Follow-ups

- **Het formulier mag niet live voordat de verwerking geregeld is.** `resources/forms/herstelling.yaml` heeft geen `email:`-blok, dus er wordt niets verstuurd — maar Statamic schrijft een POST wél als inzending weg. Dat is een formulier dat in stilte inzendingen opslokt.
- **Geen recaptcha.** `partials/recaptcha.antlers.html` bestaat en wordt op `/contact` gebruikt; dit formulier heeft hem niet.
- **Geen drag-and-drop-feedback.** Slepen werkt, maar er is geen highlight tijdens het slepen en de gekozen bestandsnaam wordt niet getoond. Dat is JavaScript-werk.
- **Vier dummy-servicefoto's.** Te vervangen zodra de echte beelden er zijn.
- **`/service` staat niet in de navigatie.** `content/trees/navigation/main.yaml` bevat alleen *Over ons*, *Projecten* en *Contact*; Aanbod en Realisaties ontbreken er ook. De nav is als geheel achterstallig.
- **Vijfde pill-vormige knop.** `.section-nav__link` herhaalt de vormdeclaraties van `.btn--accent`, `.btn--outline`, `.btn--dark` en `.btn--cta`. De `.btn--pill`-extractie staat open in `2026-07-26-pagebuilder-sections-followups.md`.
- **De checklists uit het design zijn niet gebouwd.** Figma-nodes `318:3008`, `318:3029`, `318:3052` en `318:3073` staan op `hidden`. `textImage` heeft er een `features`-argument voor als ze terugkomen.
- **`#f5f5f5` en `#bfbfbf` zijn arbitraire waarden in `form.css`.** Geen `@theme`-token, omdat ze alleen in dit component voorkomen. Komt er een tweede formulier met dezelfde vulling, dan horen ze in `site.css`.
- **`Field::get('fields')` lost geen `import:`-verwijzingen op** (bv. `reparation.image` in `services_overview.yaml`, die naar de gedeelde `image`-fieldset wijst). Die expansie gebeurt pas wanneer de Group-fieldtype zijn eigen `Fields`-collectie bouwt via `fieldtype()->fields()` — precies hoe de CP en augmentatie het veld consumeren. Blueprinttests die een geneste group inspecteren moeten dus via die laatste gaan, niet via de ruwe `get('fields')`-accessor.
