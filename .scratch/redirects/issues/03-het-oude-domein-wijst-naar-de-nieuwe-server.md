# 03: Het oude domein wijst naar de nieuwe server

**What to build:** Wie `winsoldilbeek.be` opent, komt op de server van de nieuwe
site terecht en wordt daar doorgestuurd. Nu staan de twee domeinen op
verschillende machines: het oude op `185.162.30.82`, het nieuwe op
`129.212.214.7`. Zolang dat zo is ziet de nieuwe site geen enkel verzoek voor het
oude domein en doet de tabel uit ticket 01 niets.

Zowel de apex als `www` moeten mee. De oude site stuurt nu apex naar `www` naar
`http`, drie sprongen met een degradatie erin; dat wordt er één.

De volgorde luistert nauw. Het certificaat kan pas aangevraagd worden nadat het
DNS al naar de nieuwe server wijst, want de controle haalt een bestand op via het
domein zelf. Zet het domein dus klaar in Forge vóór je bij de registrar iets
aanraakt, zodat het certificaat daarna één klik is. Tussen die twee momenten zit
een gat waarin `https` faalt en de bezoeker een schermvullende waarschuwing van
zijn browser krijgt, waarna hij de omleiding nooit ziet.

De TTL staat op 1 uur, dus de omzetting is binnen het uur doorgewerkt.

**Externe blokkade:** de DNS-toegang moet van Quinten komen. De nameservers staan
bij `european-server.eu`. Vraag daarbij meteen of het domein na de verhuizing
geregistreerd blijft: vervalt het, dan vallen alle omleidingen stil.

**Blocked by:** 01 (zonder de tabel landt elk oud adres op een 404), 02 (zonder
indexeerbare bestemming verdampt de overgedragen waarde)

**Status:** ready-for-agent

- [ ] `winsoldilbeek.be` en `www.winsoldilbeek.be` staan als extra domein op de
      site in Forge
- [ ] Beide wijzen in DNS naar de server van de nieuwe site
- [ ] Er is een geldig certificaat dat beide namen dekt
- [ ] `https://winsoldilbeek.be` en `https://www.winsoldilbeek.be` geven geen
      certificaatwaarschuwing
- [ ] Het domein blijft geregistreerd, en er is afgesproken wie dat bewaakt
- [ ] Er is een weg terug: bekend is welke DNS-waarden er stonden, zodat de
      omzetting binnen het uur teruggedraaid kan worden
