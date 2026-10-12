# Issue tracker: GitHub Issues

Issues en specs voor deze repo staan als GitHub Issues op
`arnaud-dbu/winsol-brebo`. Gebruik de `gh`-CLI voor alles wat ermee te maken
heeft.

De repo is **publiek**: alles in een issue is voor iedereen leesbaar. Zet er
geen klantgegevens, prijzen, mailadressen of letterlijke klantmails in.

## Conventies

- Eén feature is één **spec-issue** met het label `spec`. De titel is de naam
  van de feature.
- Implementatie-issues zijn aparte issues, één per ticket. Nooit één
  gecombineerd issue. Elk ticket verwijst bovenaan naar zijn spec:
  `Spec: #12`.
- Zet de tickets ook als takenlijst in de spec (`- [ ] #13`), zodat de spec de
  voortgang toont.
- De triagestand is een label. De labelstrings staan in `triage-labels.md`.
  Een issue draagt er precies één.
- Commentaar en gespreksgeschiedenis komen als comments onder het issue.
- Een ticket sluit je met `gh issue close <nr>`, of via `Closes #<nr>` in de
  commit.

## Als een skill zegt "publiceer naar de issue tracker"

```bash
gh issue create --title "<titel>" --body-file <pad> --label needs-triage
```

Schrijf de body eerst naar een bestand in de scratchpad; dan blijven
backticks en regeleinden heel.

## Als een skill zegt "haal het bijbehorende ticket op"

```bash
gh issue view <nr> --comments
```

Normaal geeft de gebruiker het issuenummer rechtstreeks mee.

## Wayfinding

Gebruikt door `/wayfinder`. De **map** is één issue met per ticket een
**kindissue**.

- **Map**: een issue met het label `map`, met de Notes, de Decisions-so-far en
  de Fog in de body.
- **Kindticket**: een issue met `Map: #<nr>` bovenaan en de vraag in de body.
  Een `Type:`-regel legt het tickettype vast (`research`, `prototype`,
  `grilling` of `task`).
- **Blokkade**: een regel `Blocked by: #NN, #NN` bovenaan. Een ticket is vrij
  zodra elk genoemd issue gesloten is.
- **Frontier**: open kindissues van de map die onblokkeerd zijn en niemand
  toegewezen hebben. Het laagste nummer wint.
- **Claimen**: wijs het issue aan jezelf toe (`gh issue edit <nr> --add-assignee @me`)
  vóór je begint.
- **Oplossen**: zet het antwoord als comment met de kop `## Answer`, sluit het
  issue, en voeg daarna een contextverwijzing (kern plus link) toe aan de
  Decisions-so-far in de map.
