# Structured data — JSON-LD graph via een PHP-builder (design)

**Datum:** 2026-08-14
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan
**Aanleiding:** SEO-audit van 2026-08-14 (`winsol-brebo.stuw.agency-audit/`)
**Blok:** A van vier. B (head/meta), C (HTTP-headers) en D (crawl-laag) volgen apart.

## Doel

Eén samenhangende JSON-LD-graph op elke pagina, opgebouwd uit data die al in de
CMS staat, zodat Google en AI-zoeksystemen kunnen vaststellen wát Winsol Brebo
is, wáár het zit en wát het levert.

## Probleemanalyse

1. **Nul structured data.** Alle 57 gecrawlde pagina's bevatten geen enkele
   regel JSON-LD. Een grep door `resources/views/` en `app/` op `ld+json`,
   `schema.org` en `LocalBusiness` geeft geen treffer: het is nergens aangelegd,
   niet half kapot.
2. **De data ligt wél klaar.** `content/collections/locations/` bevat voor alle
   drie de vestigingen `name`, `street`, `number`, `postal_code`, `city`,
   `latitude`, `longitude` en `opening_hours`. `content/globals/nl/globals.yaml`
   bevat `company`, `contact` en `socials`. Er hoeft niets uitgezocht te worden,
   alleen omgezet.
3. **Dezelfde adressen staan al als platte tekst op 38 van de 39 commerciële
   pagina's** (het "Bezoek ons"-blok). Alle informatie is publiek zichtbaar,
   alleen niet machineleesbaar.
4. **Het onderscheid Winsol / Winsol Brebo is nergens vastgelegd.** De
   fabrikant (winsol.eu) en de dealer zijn voor een model niet uit elkaar te
   houden zonder expliciete entiteitsmarkup.
5. **Escaping is een reëel risico.** Titels bevatten dubbele punten,
   uitroeptekens en accenten (`SO! Universe: één systeem voor pergola en
   poolhouse`). JSON met de hand in Antlers plakken breekt stil zodra er een
   aanhalingsteken of `</script>` in een veld belandt.

## Besluiten

| Vraag | Besluit |
|---|---|
| Vorm | PHP-builder + custom tag; `json_encode` in plaats van handmatige JSON |
| Structuur | Eén sitewide `@graph`, nodes gekoppeld via `@id` |
| Productpagina's | `Service`, niet `Product` — geen prijs/SKU, en `areaServed` versterkt het lokale signaal |
| Blueprint-wijzigingen | Geen. Alleen bouwen op velden die vandaag bestaan |
| FAQPage / auteur / reviews | Buiten scope (volgt zodra de content er is) |
| Caching | Geen eigen laag; static caching (blok C) dekt dit |
| `Organization.name` | `Winsol Brebo` (= `site:name`), niet de globals-opsomming |
| Telefoon | Op `Organization`, niet op de drie `LocalBusiness`-nodes |

## 1. Architectuur

Drie lagen, elk met één taak en los te testen.

```
app/Schema/
    SchemaGraph.php          verzamelt nodes, ontdubbelt op @id, encodeert
    OpeningHours.php         'Di - Vr' + '10:30 - 17:30' → specificatie
    OrganizationSchema.php   uit globals.company + contact + socials
    LocationsSchema.php      uit content/collections/locations/ (3 nodes)
    BreadcrumbSchema.php     uit de URL-hiërarchie
    ServiceSchema.php        product- en rangepagina's
    ArticleSchema.php        /nieuws/*

app/Tags/Schema.php          de tag {{ schema }}, leest de cascade

resources/views/layout.antlers.html
    {{ schema }} naast {{ partial:seo }} in de <head>
```

**Verantwoordelijkheden.** `SchemaGraph` kent alleen nodes en `@id`, en weet
niets van Winsol. De `*Schema`-klassen geven PHP-arrays terug en weten niets van
JSON. `Tags/Schema` bepaalt alleen wélke bouwers relevant zijn voor de huidige
pagina en weet niets van schema.org. `OpeningHours` is puur: string in,
array uit, geen Statamic-afhankelijkheid.

**Samenstelling per paginatype:**

| Pagina | Nodes |
|---|---|
| elke pagina | `Organization` + `LocalBusiness` ×3 + `BreadcrumbList` |
| `/aanbod/{range}` | \+ `Service` |
| `/aanbod/{range}/{product}` | \+ `Service` |
| `/nieuws/{slug}` | \+ `Article` |

`Organization` en de drie vestigingen staan bewust op élke pagina: zo pint
Google de entiteit consistent vast, en kunnen `Service.provider` →
`Organization.@id` binnen dezelfde graph resolveren.

## 2. De graph

Routes zijn volledig afleidbaar, dus BreadcrumbList vergt geen extra velden:

```
products  /aanbod/{{ range_slug }}/{{ slug }}   → Home ▸ Aanbod ▸ Range ▸ Product
ranges    /aanbod/{slug}                        → Home ▸ Aanbod ▸ Range
articles  /nieuws/{slug}  (mount op een page)   → Home ▸ Nieuws ▸ Artikel
pages     {parent_uri}/{slug}  (structure)      → uit de boom
```

Vorm van de uitvoer:

```json
{ "@context": "https://schema.org", "@graph": [
  { "@type": "Organization", "@id": "{site}/#organization",
    "name": "Winsol Brebo",
    "url": "{site}",
    "telephone": "+32 2 308 02 26",
    "email": "info@winsoldilbeek.be" },

  { "@type": "LocalBusiness", "@id": "{site}/#winsol-dilbeek",
    "name": "Winsol Dilbeek",
    "parentOrganization": { "@id": "{site}/#organization" },
    "address": { "@type": "PostalAddress",
      "streetAddress": "Ninoofsesteenweg 637",
      "postalCode": "1700",
      "addressLocality": "Dilbeek",
      "addressCountry": "BE" },
    "geo": { "@type": "GeoCoordinates",
      "latitude": 50.842047, "longitude": 4.237594 },
    "openingHoursSpecification": [ ... ] },

  { "@type": "BreadcrumbList", "@id": "{current}#breadcrumb",
    "itemListElement": [ ... ] },

  { "@type": "Service", "@id": "{current}#service",
    "name": "PVC ramen",
    "serviceType": "Plaatsing van PVC ramen",
    "provider": { "@id": "{site}/#organization" },
    "areaServed": ["Dilbeek", "Sint-Pieters-Leeuw", "Aartselaar"] }
]}
```

De drie vestigingen: Dilbeek (50.842047, 4.237594), Sint-Pieters-Leeuw
(50.777979, 4.269867), Aartselaar (51.114612, 4.370697).

De `LocalBusiness`-nodes krijgen `@id`-fragmenten en geen eigen `url`, omdat
vestigingspagina's nog niet bestaan. Komen die er (fase 3 van het actieplan),
dan is dat één regel per node.

## 3. Openingstijden

De opgeslagen vorm is voor mensen geschreven en niet schema-klaar:

```yaml
- { day: Maandag,   time: 'Op afspraak' }
- { day: 'Di - Vr', time: '10:30 - 17:30' }
- { day: Zaterdag,  time: '10:00 - 16:00' }
- { day: Zondag,    time: Gesloten }
```

`OpeningHours` vertaalt dat:

| Invoer | Uitvoer |
|---|---|
| `Maandag` / `Op afspraak` | **weggelaten** — schema.org kent geen "op afspraak", en een specificatie zonder `opens`/`closes` is ongeldig |
| `Di - Vr` / `10:30 - 17:30` | `dayOfWeek: [Tuesday, Wednesday, Thursday, Friday]`, `opens: "10:30"`, `closes: "17:30"` |
| `Zaterdag` / `10:00 - 16:00` | `dayOfWeek: Saturday` |
| `Zondag` / `Gesloten` | `opens: "00:00"`, `closes: "00:00"` — de gedocumenteerde manier om dicht te zijn |

De parser kent Nederlandse dagnamen én de afkortingen die in dagreeksen
voorkomen (`Ma`, `Di`, `Wo`, `Do`, `Vr`, `Za`, `Zo`). Een tijdstring die niet als
`HH:MM - HH:MM` te lezen is en niet `Gesloten` is, levert géén specificatie op
in plaats van een gok.

## 4. Foutafhandeling

**Invariant: een node bevat nooit een lege of `null`-waarde.** Ontbreekt er iets
essentieels, dan valt dat veld of die hele node weg. Half ingevulde markup is
voor Google slechter dan afwezige markup. Geen `geo` is beter dan
`"latitude": null`.

**Socials zonder placeholder-lijst.** `globals.socials` staat nu volledig op
`https://test.be`. Die horen in `sameAs`, en foute URL's zijn daar schadelijker
dan geen URL's. In plaats van `test.be` te hardcoden — waarmee de volgende
placeholder alsnog doorglipt — telt een social-URL alleen mee als de host bij het
platform past:

```
facebook  → facebook.com
instagram → instagram.com
linkedin  → linkedin.com
youtube   → youtube.com / youtu.be
```

`https://test.be` matcht niets en verdwijnt, dus `sameAs` blijft voorlopig weg.
Zodra er een echte URL wordt ingevuld, verschijnt het veld vanzelf. Geen
onderhoud, geen lijst die veroudert.

**Uitbreken uit `<script>` is structureel onmogelijk.** `json_encode` draait met
`JSON_HEX_TAG`, wat `<` en `>` omzet naar `\u003C` / `\u003E`. Een titel die
`</script>` bevat kan het scriptblok daarmee niet sluiten. Daarnaast
`JSON_UNESCAPED_UNICODE`, zodat `Sint-Pieters-Leeuw` en `één` leesbaar blijven.
Dit is de kernreden dat de builder-aanpak wint van string-plakken in Antlers.

**Geen entry in de cascade** (bijvoorbeeld op een foutpagina) levert alleen
`Organization` + `LocalBusiness` op, en geen exception.

## 5. Tests

**Unit — snel, zonder Statamic:**

| Test | Wat het vastlegt |
|---|---|
| `OpeningHoursTest` | `Di - Vr` klapt uit naar vier dagen; `Gesloten` wordt `00:00`/`00:00`; `Op afspraak` verdwijnt; onleesbare tijd levert niets op |
| `OrganizationSchemaTest` | `sameAs` weg bij `test.be`, aanwezig bij een echte facebook.com-URL |
| `SchemaGraphTest` | ontdubbeling op `@id`; lege waarden komen er niet in |

**Feature — de echte pagina:**

| Test | Wat het vastlegt |
|---|---|
| `SchemaMarkupTest` | op élk paginatype parset het `<script type="application/ld+json">`-blok als geldige JSON |
| | homepage bevat `Organization` + 3× `LocalBusiness` |
| | `/aanbod/rolluiken/inbouwrolluiken` bevat `Service` met een `provider` die naar een bestaande `@id` in dezelfde graph wijst |
| | `/nieuws/{slug}` bevat `Article` met `datePublished` |
| | elke `@id`-verwijzing resolveert binnen de graph (geen dangling refs) |

De eerste feature-test is de belangrijkste: die draait over alle paginatypes en
parset de JSON echt. Dat is precies het escaping-risico waarvoor dit ontwerp
gebouwd is, en de enige test die het kan betrappen.

**Verificatie:** elke test wordt één keer bewust aan de bronkant gebroken om te
zien dat hij rood wordt, zoals bij `SitemapTest`. Een test die nooit rood is
geweest, bewijst niets. Draaien met `php -d memory_limit=1G vendor/bin/phpunit`,
nooit `php artisan test`.

## 6. Buiten scope

- `FAQPage`, auteursveld + volledige `Article`, `Review` / `AggregateRating` —
  vergen blueprint-wijzigingen en content die er nog niet is.
- Vestigingspagina's — fase 3 van het actieplan, contentwerk.
- Blok B (`og:type` per contenttype, whitespace uit `<title>`), blok C
  (security headers, trailing-slash-redirect) en blok D (`favicon.ico`,
  `llms.txt`, `security.txt`).

## 7. Open punten

1. **`globals.company.name` klopt niet met `site:name`.** De globals bevatten
   `'Winsol Dilbeek, Sint-Pieters-Leeuw & Aartselaar'`, de titels eindigen op
   `'Winsol Brebo'`. Dit ontwerp gebruikt `site:name` als `Organization.name`,
   omdat dat de naam is die in de SERP staat; de globals-string is een
   opsomming van toonzalen, geen bedrijfsnaam. De globals zelf blijven
   ongewijzigd — opruimen hoort bij het contentwerk.
2. **Eén telefoonnummer voor drie vestigingen.** De `locations`-entries hebben
   geen eigen `phone`. Het nummer staat daarom op `Organization`; drie
   vestigingen met exact hetzelfde nummer is voor Google een zwak signaal.
   Zodra er per toonzaal een nummer is, verhuist het naar de `LocalBusiness`-nodes.
3. **`socials` staan op placeholders.** Zie §4: `sameAs` blijft weg tot ze echt
   zijn. Dit is een contentactie, geen codeactie.
