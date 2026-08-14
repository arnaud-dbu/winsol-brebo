# Structured data — followups

**Datum:** 2026-08-14
**Hoort bij:** `2026-08-14-structured-data-design.md`

## Handmatige validatie van de echte uitvoer

Gecontroleerd op `http://winsol-brebo.test` (Herd serveert deze checkout onbeveiligd over http,
niet https — een `https://`-curl geeft stilzwijgend niets terug).

| Pagina | Nodes | Klopt |
|---|---|---|
| `/` | Organization + 3× LocalBusiness | ja — geen BreadcrumbList op de homepage, zoals ontworpen |
| `/contact` | \+ BreadcrumbList | ja — geen Service, want pages-entry |
| `/aanbod/rolluiken/inbouwrolluiken` | \+ BreadcrumbList + Service | ja |
| `/nieuws/{slug}` | \+ BreadcrumbList + Article | ja, met `datePublished` |

Steeds **precies één** `<script type="application/ld+json">` per pagina. Alle `@id`-verwijzingen
(`provider`, `parentOrganization`) resolveren binnen dezelfde graph; geen dangling refs.

Inhoudelijk bevestigd op de productpagina:

- `Organization.name` is `Winsol Brebo`, niet de opsomming uit `globals.company.name`.
- `sameAs` ontbreekt volledig — de host-regel weert de `https://test.be`-placeholders, zoals bedoeld.
- Telefoon staat op `Organization` en niet op de drie vestigingen.
- Openingstijden: maandag (`Op afspraak`) correct weggelaten, `Di - Vr` uitgeklapt naar vier dagen,
  zondag als `00:00`/`00:00`.
- `Service` draagt geen `offers`, `price`, `sku` of `brand`, en `areaServed` bevat de drie gemeentes.

## Nog te doen

1. **Rich Results Test — niet uitgevoerd.** De testomgeving is lokaal (`winsol-brebo.test`) en dus niet
   bereikbaar voor Google's crawler, en staging staat op `noindex`. De JSON is wel geverifieerd geldig en
   volledig resolvend. Zodra de site publiek staat: URL door
   <https://search.google.com/test/rich-results> halen en controleren dat de drie `LocalBusiness`-items
   **apart** herkend worden en niet samensmelten. `Service` levert bewust geen rich result op maar hoort
   ook geen fouten te geven.
2. **`globals.socials` invullen of leegmaken.** Zolang ze op `https://test.be` staan, blijft `sameAs`
   weg. Dat is het juiste gedrag, maar het betekent ook dat er nu geen enkel socialsignaal meegaat.
3. **Naamconflict opruimen.** `globals.company.name` is `'Winsol Dilbeek, Sint-Pieters-Leeuw &
   Aartselaar'`, wat een opsomming van toonzalen is en geen bedrijfsnaam. De schema-laag gebruikt
   bewust `site:name`. Het veld zelf blijft misleidend voor wie het later gebruikt.
4. **Eén telefoonnummer voor drie vestigingen.** Zodra elke toonzaal een eigen nummer heeft, verhuist
   `telephone` van `Organization` naar de `LocalBusiness`-nodes.
5. **`url` per vestiging.** De `LocalBusiness`-nodes hebben nu alleen een `@id`-fragment. Komen er
   vestigingspagina's (fase 3 van het actieplan), dan krijgt elke node zijn eigen `url`.


## Bevindingen uit de eindreview

De eindreview over de hele branch gaf **ship**. De escaping-garantie is empirisch getoetst, niet
gelezen: een artikeltitel met `</script>`, een `onerror`-payload, een Antlers-expressie en U+2028 komt
er correct geëscaped uit, en Antlers parseert de teruggegeven string niet opnieuw. Alle 57
sitemap-URL's zijn gesweept: overal precies één blok, overal geldige JSON, nergens een dangling
`@id`-verwijzing. Een 404 valt netjes terug op Organization + 3× LocalBusiness zonder exception.

### Opgelost in de fix-golf (commit 7c711ff)

| # | Bevinding | Oplossing |
|---|---|---|
| 1 | Geen exception-boundary: een throw in deze head-laag gaf een 500 op élke pagina | `index()` vangt `\Throwable`, logt een warning en geeft `''` terug |
| 2 | `Article` was half gevuld | `image` en `dateModified` toegevoegd; beide bestonden al, geen blueprint-wijziging |
| 3 | `ServiceSchema` groef de JSON-LD-uitvoervorm van een andere builder af | `LocationsSchema::cities()` leest `city` rechtstreeks; scheelt ook een Stache-query per pagina |
| 4 | `ListItem` kon zonder `name` de uitvoer halen | Laatste segment krijgt dezelfde slug-fallback als de tussenniveaus |
| 5 | Geen borging dat `LocalBusiness` géén `telephone` draagt | Assertie toegevoegd |
| 6 | `sameAs`-bedrading ongetoetst | Test die `socials` tegen `contact` onderscheidt, zodat een verwisseling opvalt |
| 7 | Vacuüme socials-test | Herschreven naar een falsifieerbare assertie op de graph |
| 8 | `SiteUrlTest` toetste tegen een andere bron dan de code leest | Toetst nu tegen `Site::current()->absoluteUrl()` |

### Bewust niet opgelost

- **`SchemaGraph`: int-key naast string-`@id`-keys.** Onbereikbaar: elke `@id` komt uit `SiteUrl` en
  begint met `http`, dus nooit een numerieke string. Alle vijf de nodetypes dragen bovendien een `@id`.
- **`areaServed()` kan `[]` teruggeven.** `SchemaGraph::prune()` verwijdert de lege sleutel vóór de
  uitvoer. De invariant "nooit leeg" is dus een eigenschap van **`SchemaGraph`**, niet van de builders;
  builder-uitvoer mag bewust lege waarden bevatten. Dat onderscheid is de moeite waard om te onthouden.
- **`LocationsSchema::cities()` filtert geen vestigingen met een lege `name`,** waar `nodes()` dat
  impliciet wel doet. Nu zonder gevolg omdat alle drie de entries een naam hebben.
- **Testsuite is gekoppeld aan productie-contentwaarden** (telefoonnummer, `Inbouwrolluiken`,
  `Ons aanbod`, drie adressen, zes coördinaten). Een redacteur die een entry hernoemt maakt de build
  rood zonder dat er code wijzigt. De coördinaten- en adrescontroles moeten juist blijven; de
  titel-assertions zouden vormcontroles kunnen worden, zoals `ArticleSchema`'s test al doet.
- **`SiteUrlTest` en `OrganizationSchemaTest` staan onder `tests/Unit` maar booten de app,** in strijd
  met §5 van de spec die de unit-laag definieert als "snel, zonder Statamic". Verplaatsen is churn
  zonder gedragswinst, maar de indeling klopt niet.
- **Trailing slash verschilt tussen de twee head-lagen:** `Organization.url` en het Home-item van de
  breadcrumb eindigen op een slash, `canonical` en `og:url` niet. Google normaliseert dit, dus geen
  ranking-gevolg, maar het leest als een bug voor wie de twee naast elkaar legt.
- **`LocalBusiness.image` ontbreekt,** net zoals `Article.image` ontbrak. Zelfde argument, lagere inzet.

### Interactie met de parallelle werkstroom

Deze branch draagt ook vier commits van een gelijktijdige sessie (security headers, trailing-slash-301,
`og:type`, favicon en security.txt). Gecontroleerd op botsingen: `RedirectTrailingSlash` zondert `/` uit,
dus de slash in `Organization.url` wijst niet naar een 301. `SecurityHeaders` zet geen CSP, dus niets
beperkt het inline `ld+json`-blok — wel iets om te onthouden als CSP later alsnog landt.
