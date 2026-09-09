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

**Status:** ready-for-human

- [ ] `winsoldilbeek.be` en `www.winsoldilbeek.be` staan als extra domein op de
      site in Forge
- [ ] Beide wijzen in DNS naar de server van de nieuwe site
- [ ] Er is een geldig certificaat dat beide namen dekt
- [ ] `https://winsoldilbeek.be` en `https://www.winsoldilbeek.be` geven geen
      certificaatwaarschuwing
- [ ] Het domein blijft geregistreerd, en er is afgesproken wie dat bewaakt
- [x] Er is een weg terug: bekend is welke DNS-waarden er stonden, zodat de
      omzetting binnen het uur teruggedraaid kan worden
- [ ] De mailstroom van het oude domein staat er na de omzetting nog
      (nieuw vinkje, zie de zone hieronder)

## Comments

**Let op bij het certificaat: de omleiding uit ticket 01 pakt ook
`/.well-known/acme-challenge/…`.** Op de oude host geeft dat pad een 301 naar
`winsol-brebo.be`, waar het bestand niet bestaat. In de normale opstelling van
Forge serveert nginx de challenge via `try_files` voordat PHP aan bod komt, dus
meestal merk je hier niets van. Valt dat om welke reden dan ook door naar de
applicatie, dan volgt Let's Encrypt de omleiding, vindt niets, en faalt de
controle op een manier die op een DNS-probleem lijkt terwijl het de omleiding is.

Loopt de aanvraag vast: zet `RedirectLegacyUrls` even uit, of geef de middleware
een uitzondering voor `.well-known`, en probeer opnieuw. Gevonden bij de review
tijdens ticket 02.

**De omleiding laat `/.well-known/` nu met rust.** De bevinding hierboven is
opgelost in plaats van omzeild: `RedirectLegacyUrls` laat alles onder
`/.well-known` door naar de applicatie, op beide hosts. Dat is een guard in de
middleware, vóór de klim en vóór de doorval, want juist die doorval maakte er
een omleiding naar zichzelf van. `LegacyRedirectTest` toetst het door de kernel:
`/.well-known/acme-challenge/tok` gaf 301 en geeft nu 404.

De guard matcht zonder afsluitende slash, want `normalise()` haalt die er al af;
met de slash erin ontsnapte de kale namespace er nog aan. Wie `/.well-known/`
opvraagt krijgt daarna alsnog een 301 van `RedirectTrailingSlash`, maar dat is
dezelfde host en dus niet de fout waar het hier om gaat.

De namespace gaat in zijn geheel mee en niet alleen `acme-challenge`. Bij het
schrijven van de toets bleek `/.well-known/security.txt` van deze applicatie te
zijn (RFC 9116, `routes/web.php`), en op de oude host werd die mee omgeleid.
Nu wordt hij daar gewoon geserveerd.

Voor de certificaataanvraag betekent dit dat de ontsnappingsroute uit de vorige
notitie niet meer nodig is. Loopt de aanvraag alsnog vast, dan ligt het niet
hier.

**De zone zoals hij nu staat.** Gemeten op 2026-09-09 met `dig`. Dit is de weg
terug: alleen de twee `A`-regels gaan om, van `185.162.30.82` naar
`129.212.214.7`. Terugdraaien is dezelfde handeling andersom, en met een TTL van
3600 is dat binnen het uur doorgewerkt.

```
winsoldilbeek.be.       3600  IN  A    185.162.30.82        <- wordt 129.212.214.7
www.winsoldilbeek.be.   3600  IN  A    185.162.30.82        <- wordt 129.212.214.7
winsoldilbeek.be.       3600  IN  NS   ns1.european-server.eu.
winsoldilbeek.be.       3600  IN  NS   ns3.european-server.com.
winsoldilbeek.be.       3600  IN  NS   ns4.european-server.com.
```

Er staat geen `AAAA` en geen `CAA`, op geen van beide domeinen. Het nieuwe
domein draait op `129.212.214.7` met alleen een `A`-regel voor apex en `www`,
dus de oude zone hoeft niets nieuws te leren: dezelfde vorm, een ander adres.
Zonder `CAA` staat er ook niets in de weg van Let's Encrypt.

**Op dit domein loopt mail, en dat is het echte risico van deze omzetting.** De
`MX`-regels wijzen naar Google Workspace en het `SPF`-record noemt de oude
server met naam:

```
winsoldilbeek.be.  3600  IN  MX   1 aspmx.l.google.com.  (plus alt1, alt2, aspmx2, aspmx3)
winsoldilbeek.be.  3600  IN  TXT  "v=spf1 include:_spf.google.com include:_spf.relay.mailprotect.be a:mailing.mailflow.be ip4:185.162.30.0/24 ~all"
```

De opdracht aan de registrar is dus niet "zet het domein op de nieuwe server"
maar "wijzig deze twee `A`-regels". Wie het domein in één beweging naar een
nieuwe zone verhuist neemt `MX` en `SPF` mee, en dan valt de mail stil terwijl
de site het prima doet. Die `ip4:185.162.30.0/24` in het `SPF`-record is
overigens de oude server: die blijft nodig zolang daar iets verstuurt, en is een
punt om op te ruimen bij het uitfaseren van de oude hosting.

Er staan ook vier `google-site-verification`-regels in de zone. Die horen te
blijven staan: ze zijn het bewijs van eigendom in Search Console, en zonder
werkende verificatie kan de verhuizing daar niet met de adreswijziging gemeld
worden. De spec noteerde dat Search Console voor deze oplevering niet
beschikbaar was; de verificatie zelf staat er dus wel, het is een kwestie van
bij het juiste Google-account komen.

**Wat de mens doet.**

1. **Eerst ticket 02 afmaken.** De spec zet dat hard vast: "De nieuwe site moet
   indexeerbaar zijn vóór het DNS van het oude domein verhuist." Ticket 02 staat
   nog met alle vinkjes open, en wie deze lijst leest zonder die te doen stuurt
   Google naar een site die op `noindex` staat. Dan verdampt precies de waarde
   die deze hele operatie moet overdragen.
2. In Forge `winsoldilbeek.be` en `www.winsoldilbeek.be` als extra domein op de
   site van de nieuwe pagina zetten. Nog niets bij de registrar.
3. Bij de registrar de twee `A`-regels omzetten naar `129.212.214.7`, en niets
   anders aanraken.
4. Wachten tot het doorgewerkt is, en dat toetsen met `dig` en niet in de
   browser: `dig +short winsoldilbeek.be` hoort `129.212.214.7` te geven.
5. Meteen daarna het certificaat aanvragen in Forge, voor beide namen tegelijk.
   Tussen stap 3 en dit moment faalt `https` op het oude domein en krijgt de
   bezoeker een schermvullende waarschuwing van zijn browser, waarna hij de
   omleiding nooit ziet. Dat gat hoort minuten te duren, geen uren.
6. De vinkjes toetsen:

```
dig +short winsoldilbeek.be www.winsoldilbeek.be     # tweemaal 129.212.214.7
dig +short winsoldilbeek.be MX                       # de vijf Google-regels staan er nog
curl -sSI https://winsoldilbeek.be/nl/Contact/       # 301 naar https://winsol-brebo.be/contact, geen certificaatfout
curl -sSI https://www.winsoldilbeek.be/              # 301 naar https://winsol-brebo.be/
```

De steekproef over de volle tabel is ticket 04, en die moet vanaf een ander IP
dan de ontwikkelmachine: de oude site bant 24 uur na 200 verzoeken.

**Nog steeds extern geblokkeerd.** De DNS-toegang moet van Quinten komen en de
nameservers staan bij `european-server.eu`. Vraag daarbij of het domein na de
verhuizing geregistreerd blijft en wie dat bewaakt: vervalt het, dan vallen alle
omleidingen stil en is er geen code die dat opvangt.
