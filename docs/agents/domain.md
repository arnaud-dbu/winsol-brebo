# Domeindocs

Hoe de engineering-skills de domeindocumentatie van deze repo horen te lezen bij
het verkennen van de codebase.

## Lees dit vóór je verkent

- **`CONTEXT.md`** in de root: de woordenlijst van het project.
- **`docs/adr/`**: lees de ADR's die raken aan het gebied waar je gaat werken.

Bestaat een van deze niet, ga dan **stilzwijgend verder**. Meld de afwezigheid
niet en stel niet voor ze alvast aan te maken. De skill `/domain-modeling`,
bereikbaar via `/grill-with-docs` en `/improve-codebase-architecture`, maakt ze
aan op het moment dat er echt een term of een beslissing wordt vastgelegd.

## Bestandsindeling

Deze repo is **single-context**:

```
/
├── CONTEXT.md
├── docs/adr/
│   ├── 0001-....md
│   └── 0002-....md
└── app/
```

Er is geen `CONTEXT-MAP.md` en die is hier ook niet nodig: het is één
Statamic-site, geen monorepo.

## Gebruik de woorden uit de woordenlijst

Noemt je uitvoer een domeinbegrip, in een issuetitel, een refactorvoorstel, een
hypothese of een testnaam, gebruik dan de term zoals `CONTEXT.md` hem definieert.
Wijk niet uit naar de synoniemen die daar onder `_Avoid_` staan.

Staat het begrip dat je nodig hebt nog niet in de woordenlijst, dan is dat een
signaal. Of je verzint taal die het project niet gebruikt, en dan moet je je
bedenken, of er zit een echt gat, en dan noteer je het voor `/domain-modeling`.

## Meld strijd met een ADR

Spreekt je uitvoer een bestaande ADR tegen, zeg dat dan hardop in plaats van hem
stil te overrulen:

> _Dit gaat in tegen ADR-0003 (redirects in eigen middleware), maar het is het
> heroverwegen waard omdat…_
