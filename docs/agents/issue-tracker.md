# Issue tracker: lokale markdown

Issues en specs voor deze repo staan als markdownbestanden in `.scratch/`, niet
op GitHub. De GitHub-issuelijst van deze repo wordt niet gebruikt.

## Conventies

- Eén feature per map: `.scratch/<feature-slug>/`
- De spec is `.scratch/<feature-slug>/spec.md`
- Implementatie-issues staan als één bestand per ticket op
  `.scratch/<feature-slug>/issues/<NN>-<slug>.md`, genummerd vanaf `01`. Nooit
  één gecombineerd ticketbestand.
- De triagestand staat als `Status:`-regel bovenin elk issuebestand. De
  rolstrings staan in `triage-labels.md`.
- Commentaar en gespreksgeschiedenis komen onderaan het bestand onder een kop
  `## Comments`.

## Als een skill zegt "publiceer naar de issue tracker"

Maak een nieuw bestand aan onder `.scratch/<feature-slug>/`, en de map erbij als
die nog niet bestaat.

## Als een skill zegt "haal het bijbehorende ticket op"

Lees het bestand op het genoemde pad. Normaal geeft de gebruiker het pad of het
issuenummer rechtstreeks mee.

## Wayfinding

Gebruikt door `/wayfinder`. De **map** is één bestand met per ticket een
**kindbestand**.

- **Map**: `.scratch/<effort>/map.md`, met de Notes, de Decisions-so-far en de
  Fog.
- **Kindticket**: `.scratch/<effort>/issues/NN-<slug>.md`, genummerd vanaf `01`,
  met de vraag in de body. Een `Type:`-regel legt het tickettype vast
  (`research`, `prototype`, `grilling` of `task`), een `Status:`-regel legt
  `claimed` of `resolved` vast.
- **Blokkade**: een regel `Blocked by: NN, NN` bovenin. Een ticket is vrij zodra
  elk bestand dat het noemt op `resolved` staat.
- **Frontier**: zoek in `.scratch/<effort>/issues/` naar bestanden die open,
  onblokkeerd en niet geclaimd zijn. Het laagste nummer wint.
- **Claimen**: zet `Status: claimed` en sla op vóór je begint.
- **Oplossen**: zet het antwoord onder een kop `## Answer`, zet
  `Status: resolved`, en voeg daarna een contextverwijzing (kern plus link) toe
  aan de Decisions-so-far in `map.md`.
