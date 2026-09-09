# 02: De nieuwe site is zichtbaar voor Google

**What to build:** Google mag de nieuwe site crawlen en indexeren. Nu staat
`SITE_INDEXABLE` op productie op `false`, waardoor `robots.txt` met
`Disallow: /` de hele site afsluit. Dat was juist zolang de site niet gelanceerd
was, maar het moet om vóór er verkeer van het oude domein naartoe gestuurd wordt.

Dit is de lancering zelf. Zet de vlag om, deploy, en stel vast dat de site het
ook echt uitdraagt.

Het gaat om een omgevingsvariabele op productie, geen code. Wel in deze volgorde:
een omleiding naar een site die op noindex staat is voor Google een doodlopende
weg. Hij volgt hem, mag de bestemming niet lezen, en de waarde die het oude
domein had opgebouwd verdampt.

**Blocked by:** None (can start immediately)

**Status:** ready-for-human

- [ ] `SITE_INDEXABLE=true` staat op productie
- [ ] `winsol-brebo.be/robots.txt` bevat geen `Disallow: /` meer
- [ ] Een willekeurige pagina van de nieuwe site draagt geen `noindex` meer uit,
      niet in de header en niet in de markup
- [ ] Dit is gebeurd vóór ticket 03 begint

## Comments

**De stand op productie, gemeten op 2026-09-09.** `SITE_INDEXABLE` staat er nog
op `false`. `https://winsol-brebo.be/robots.txt` geeft `Disallow: /` en elke
respons draagt `X-Robots-Tag: noindex, nofollow`. De vlag omzetten kan niet
vanaf de ontwikkelmachine: er is geen toegang tot de server en er staat geen
deploygereedschap in de repo. Daarom `ready-for-human` met de vier vinkjes open.
De code eronder is af en staat onder toets: `RobotsTest` en `NoIndexHeaderTest`
dekken beide takken van de vlag.

**Er zijn twee schakelaars, niet één.** `SITE_INDEXABLE` stuurt `robots.txt` en
de `X-Robots-Tag`-header. De markup heeft een eigen bron: `seo_noindex` in de
globals, per site, en hetzelfde veld per entry. Het derde vinkje noemt allebei,
het ticket noemt alleen de eerste. Nagekeken: `seo_noindex` staat op `false` in
`content/globals/{nl,fr,en}/seo.yaml`, en de homepage op productie bevat het
woord `noindex` nul keer in 190 kB markup. De vlag omzetten volstaat dus, en het
derde vinkje valt na de omzetting in één keer goed.

**`robots.txt` antwoordt met een 404.** De inhoud klopt, de statuscode niet. Het
nginx-recept voor Laravel heeft `location = /robots.txt` zonder `try_files`, dus
nginx zoekt een bestand dat er niet is, komt op zijn eigen 404 uit, en stuurt die
via `error_page 404` alsnog naar `index.php`. De applicatie rendert dan de juiste
tekst, maar de 404 blijft staan. Lokaal in Herd exact hetzelfde beeld, en daar
staan die twee regels letterlijk in `herd.conf`; het standaardsjabloon van Forge
draagt ze ook.

Google leest een 404 op `robots.txt` als "er is geen robots.txt" en mag dan alles
crawlen. Wat de site vandaag uit de index houdt is dus niet de `Disallow: /` maar
de `X-Robots-Tag`, precies waarvoor `NoIndexHeader` geschreven is; dat staat ook
zo in zijn docblock. Na de omzetting blijft er één gevolg over: de
`Sitemap:`-regel wordt niet gelezen. Dat blokkeert de lancering niet en staat als
ticket 05 apart.

**Wat de mens doet.**

1. `SITE_INDEXABLE=true` in de omgeving van de site in Forge.
2. Deployen of de configuratiecache opnieuw opbouwen. Staat `config:cache` aan
   en gebeurt dit niet, dan blijft de oude waarde staan en verandert er niets.
3. De drie vinkjes toetsen:

```
curl -sSI https://winsol-brebo.be/ | grep -i x-robots-tag   # hoort niets te geven
curl -sS  https://winsol-brebo.be/robots.txt                # hoort `Disallow:` te geven, zonder /
curl -sS  https://winsol-brebo.be/ | grep -c noindex        # hoort 0 te geven
```

**De bestemmingen staan er al.** Steekproef op productie: `/`,
`/aanbod/ramen-en-deuren`, `/fr/gamme/chassis-et-portes`, `/contact` en
`/aanbod/terrasoverkapping` geven alle vijf een 200, en `sitemap.xml` geeft een
index met vijf deelsitemaps. Het vierde vinkje gaat dus over de volgorde en niet
over een gat in de inhoud: er is een indexeerbare bestemming zodra de vlag om is.
