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

## Wat taak 5 onderweg tegenkwam

- **`Blueprint::field()->get('fields')` volgt geen `import:`-verwijzingen.** De `reparation`-group in `services_overview.yaml` verwees via `import: image` naar de gedeelde `image`-fieldset (zo gebouwd in taak 4, volgens diens eigen opdracht). Dat werkt prima voor alles wat via `Group::fields()` gaat — CP, augmentatie, validatie — maar code die het blueprint rechtstreeks inspecteert met `field('reparation')->get('fields')` krijgt de ruwe yaml terug, inclusief het `{'import': 'image'}`-item zonder `handle`-sleutel. `ServicePageTest::test_the_reparation_group_carries_an_image_field` (letterlijk uit de opdracht van taak 5) faalde daarop. Opgelost door het image-veld inline te definiëren in plaats van via `import:` — zelfde `container`/`max_files`/`type`/`display` als de fieldset, dus geen gedragsverandering in CP of front-end. Elders in het project staat `import: image` nog wél (bv. op de `service`-set in dezelfde replicator); dat is geen probleem zolang niemand dat sub-blueprint met dezelfde rechtstreekse `get('fields')`-truc inspecteert. Komt zo'n test er ooit voor een ander veld, dan geldt dezelfde beperking.
