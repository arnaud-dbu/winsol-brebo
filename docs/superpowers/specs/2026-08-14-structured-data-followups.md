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

## Openstaande minors uit de reviews

Alle vijf gemeld tijdens de taakreviews, geen ervan blokkerend:

- `SchemaGraph`: nodes zonder `@id` krijgen een int-key naast string-`@id`-keys; theoretische botsing.
- `LocationsSchema`: geen test die borgt dat een vestiging **geen** `telephone` draagt, terwijl dat een
  expliciet besluit is.
- `OrganizationSchema`: `sameAs` wordt niet end-to-end op `node()` getoetst, alleen via de unittests.
- `ServiceSchema`: `areaServed()` kan `[]` teruggeven bij een lege locations-collectie.
- `SchemaMarkupTest`: `test_the_placeholder_socials_never_reach_the_output` slaagt ook zonder de tag,
  want `test.be` stond sowieso nergens in de uitvoer. Hij bewaakt de host-regel, niet de tag.
