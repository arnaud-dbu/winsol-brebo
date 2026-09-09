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

**Status:** ready-for-agent

- [ ] `SITE_INDEXABLE=true` staat op productie
- [ ] `winsol-brebo.be/robots.txt` bevat geen `Disallow: /` meer
- [ ] Een willekeurige pagina van de nieuwe site draagt geen `noindex` meer uit,
      niet in de header en niet in de markup
- [ ] Dit is gebeurd vóór ticket 03 begint
