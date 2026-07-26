# Overzichtspagina's — Follow-ups

**Date:** 2026-07-26
**Status:** Open
**Context:** Naar boven gekomen tijdens de uitvoering en de eindreview van `2026-07-26-overview-pages-design.md`. Niets hiervan blokkeerde die oplevering; alles staat hier zodat het niet verdwijnt.

## Valkuilen die je moet kennen voordat je hieraan werkt

- **`range` botst met Antlers' ingebouwde Range-tag** (`Statamic\Tags\Range`). Staat de variabele niet in context, dan valt een parameterloze `{{ range }}…{{ /range }}` terug op die tag en rendert de body één keer tegen de parent scope. In `partials/projectCard.antlers.html` betekende dat concreet: het categorielabel vulde zich met de *projecttitel*. De `{{ if range }}` eromheen is dus load-bearing, niet overbodig — een reviewer heeft hem al eens als redundant bestempeld. `ProjectCardTest::test_omits_the_category_when_no_range_is_set` dekt het. Zelfde reden voor de guard rond `{{ range:slug }}` in `projects-overview.antlers.html`.
- **`{{ get:… }}` mag nooit in een Alpine-expressie geïnterpoleerd worden.** Statamic escapet die waarden naar HTML-entiteiten, maar de browser decodeert die vóórdat Alpine het attribuut leest, dus dat is geen bescherming. Dit leverde een reflected XSS op die pas bij de eindreview gevonden werd (`x-data="projectFilter('{{ get:range }}')"`). Laat de component de querystring zelf uit `location.search` lezen. In een HTML-attribuut of een `{{ if }}`-vergelijking is dezelfde waarde wél veilig.
- **Een taxonomieveld moet dezelfde handle hebben als de taxonomie.** Heette het veld `range_category` terwijl de collectie `range_categories` declareert, dan injecteerde Statamic er stilzwijgend een tweede veld bij: twee bijna identieke velden in het CMS, en `Term::queryEntries()` gaf nul voor elke term. Inmiddels hernoemd.
- **`{{ if }}`-blokken lekken witruimte.** De indentatie vóór de tag en de newline erna blijven staan wanneer de conditie onwaar is. Voor een gedeeld partial waarvan de output ongewijzigd moet blijven, betekent dat: tag en markup op één regel, zonder tussenliggende spatie.

## Openstaande beslissingen

- **Volgorde van het filter op `/realisaties`.** `app/Tags/ProjectRanges.php` sorteert alfabetisch op titel; de spec zei "de volgorde volgt de `ranges`-collectie". Nu `ranges` een `order`-veld heeft (toegevoegd voor `/aanbod`), kan het filter diezelfde volgorde aanhouden. Eén regel, maar het is een zichtbare keuze.
- **Escaping is inconsistent.** De categorielabels en filterknoppen gaan door `| entities`, terwijl range-kaarttitels op dezelfde pagina een rauwe `&` renderen. Plain `{{ var }}` escapet in dit project niet. Sitewide punt, ouder dan dit werk — één keer doorlopen en een lijn kiezen.
- **`{{ taxonomy: }}` is nu bruikbaar.** Sinds de veldnaam klopt werkt Statamic's associatie-index. `range-overview.antlers.html` gebruikt bewust nog de bewezen `overlaps`-query per categorie; die kan vereenvoudigd worden, maar het gedrag eromheen (volgorde, lege categorieën overslaan) kostte al een reviewronde, dus het is geen gratis wissel.

## Code follow-ups

- **`divider="false"` is truthy.** `headers/default.antlers.html` rendert de lijn bij elke niet-lege waarde. Beide aanroepers geven `"true"`, dus geen probleem vandaag.
- **Het onderbouwingscommentaar in `headers/default.antlers.html` staat op één fysieke regel**, om witruimte-emissie te vermijden. De inhoud is compleet maar leest slecht; Antlers' whitespace control kan de regelafbrekingen misschien terugbrengen.
- **`data-range` heeft geen consument** buiten de tests. Het is een testhaak in productiemarkup — bewust, maar het hoort benoemd te zijn.
- **`PageHeaderTest` hardcodeert `\r\n`** terwijl `.gitattributes` `eol=lf` afdwingt. Het slaagt omdat Antlers CRLF uitstuurt; een parserwijziging maakt van een terechte tripwire een rode test.
- **Twee "geen slider"-tests kunnen niet falen** door hoe de partials zijn opgebouwd (`RangeCardTest` en `ProjectCardTest`). Bewust behouden als regressie-tripwire.
- **Slugs worden in Alpine-expressies geïnterpoleerd** (`select('{{ slug }}')`). Slugs zijn geslugificeerd dus onexploiteerbaar, maar het is dezelfde constructie als de XSS hierboven.
- **`order` is verplicht op `ranges`.** Handgeschreven YAML zonder dat veld sorteert vooraan in plaats van te falen.

## Documentatie

- Oudere specs onder `docs/superpowers/` noemen nog de oude categorie-slugs (`buitenzonwering`, `schrijnwerk`, `comfort-en-techniek`) en de veldnaam `range_category`. Alleen verwarrend als ze als actueel gelezen worden.

## Handmatig nog te controleren

De tests dekken de markup, niet de vormgeving. Nog na te kijken in de browser:

1. `/aanbod` op 402px: drie categorieblokken, kaarten onder elkaar, geen horizontale scroll.
2. `/realisaties` op 402px: het filter scrollt horizontaal door tot de schermrand.
3. Klikken op een filterknop wisselt de grid onmiddellijk, zonder animatie en zonder paginalading, en de URL verspringt mee.
4. `/realisaties?range=zonwering` met JavaScript uitgeschakeld: één kaart zichtbaar, juiste knop actief.
