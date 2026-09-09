# 04: De omleidingen zijn geverifieerd in productie

**What to build:** Vaststellen dat het in het echt werkt, niet alleen in de
testsuite. De suite uit ticket 01 draait tegen de applicatie en bewijst dat de
tabel klopt. Hij zegt niets over DNS, over het certificaat, of over wat er
gebeurt als een echte browser het oude domein opvraagt.

Neem per laag één adres en stel vast dat het in **één** sprong op een 200 landt,
niet in twee en niet op een foutpagina:

- een echte pagina van de oude site, bijvoorbeeld de contactpagina
- een gemeentepagina
- een adres dat niet in de tabel staat en dus moet klimmen
- een brochure-downloadadres
- een Frans adres
- de apex zonder `www`
- een adres met een querystring, waarbij de parameters intact blijven

Let bij elk op de statuscode van de eerste respons, op het aantal sprongen, en op
of de keten ergens van `https` naar `http` zakt.

**Dit ticket kan niet door de ontwikkelmachine uitgevoerd worden.** De oude site
bant een IP 24 uur na 200 verzoeken, en dat is op 2026-09-09 rond 16:30 gebeurd.
Tot ongeveer 2026-09-10 18:30 geeft die machine op elk verzoek aan het oude
domein een 503. Voer de steekproef uit vanaf een ander IP, of vanaf een telefoon
met wifi uit.

**Blocked by:** 03

**Status:** ready-for-human

- [ ] Per laag hierboven is één adres gecontroleerd
- [ ] Elk gecontroleerd adres komt in één sprong aan
- [ ] Geen enkele keten zakt onderweg naar `http`
- [ ] Querystrings blijven intact
- [ ] Een adres dat nergens op uitkomt geeft de 404-pagina van de nieuwe site,
      niet de homepage
- [ ] De uitkomst staat onder `## Comments` in dit bestand, met de gebruikte
      adressen erbij
- [ ] De omleidingen staan op de nieuwe server voordat het DNS omgaat
      (nieuw vinkje, zie punt 3 hieronder)

## Comments

**De steekproef is nog niet gedraaid, en kon vandaag ook niet.** Er zitten drie
dingen tussen, en het derde stond nog nergens opgeschreven.

1. **Het DNS staat nog op de oude server.** Gemeten op 2026-09-09:

```
dig +short winsoldilbeek.be           185.162.30.82
dig +short www.winsoldilbeek.be       185.162.30.82
dig +short winsol-brebo.be            129.212.214.7
```

   Ticket 03 is dus niet uitgevoerd, en zolang dat zo is ziet de nieuwe site
   geen enkel verzoek voor het oude domein. Er valt niets te bemonsteren.

2. **Het domein staat ook nog niet in Forge.** Met `curl --resolve` het oude
   domein hard naar `129.212.214.7` wijzen geeft op poort 443 een
   `tlsv1 unrecognized name` en op poort 80 een lege respons. Nginx kent de
   naam niet en er is geen catch-all. Dat is geen probleem, het is stap 2 uit
   de lijst van ticket 03 die nog moet gebeuren, maar het is wel het antwoord
   op de vraag of de tabel alvast buiten het DNS om te toetsen was.

3. **De omleidingen staan niet in productie.** Dit is de nieuwe bevinding.
   `redirects-oude-site` is niet samengevoegd en niet uitgerold, dus op de
   nieuwe server bestaat `RedirectLegacyUrls` niet:

```
curl -sSI https://winsol-brebo.be/nl/Contact/     301 naar /nl/Contact, daarna 404
curl -sSI https://winsol-brebo.be/fr/Contact/     301 naar /fr/Contact, daarna 404
```

   Dat is de trailing-slash-omleiding en daarna niets. Gaat het DNS om vóór
   deze tak is uitgerold, dan landt élk oud adres op een 404 van de nieuwe
   site. Dat is slechter dan de situatie van nu, want vandaag serveert de oude
   site zijn eigen pagina's nog. De volgorde is dus: uitrollen, dan Forge, dan
   de registrar.

**De acht adressen van de steekproef.** Eén per laag uit de opdracht, plus het
adres dat nergens op uitkomt, want dat is een eigen vinkje. Ze staan in
`.scratch/redirects/steekproef.txt`. De toets
`test_the_production_sample_matches_the_table` in `LegacyRedirectTest` leest
diezelfde lijst en zet hem door de kernel tegen de tabel. Zonder die toets veroudert de lijst zodra
iemand de tabel wijzigt, en toetst de mens in productie iets wat de applicatie
allang anders doet.

| laag            | oud adres                                                                | verwacht                                              |
| --------------- | ------------------------------------------------------------------------ | ----------------------------------------------------- |
| echte pagina    | `/nl/Contact/`                                                           | 301 naar `/contact`, 200                              |
| gemeentepagina  | `/Winsol-rolluiken/Aartselaar/`                                          | 301 naar `/aanbod/rolluiken`, 200                     |
| klimregel       | `/nl/Ons-aanbod/Rolluiken/Inbouwrolluiken/Zonwerende-lamellen/Kleuren/`  | 301 naar `/aanbod/rolluiken/inbouwrolluiken`, 200     |
| brochure        | `/nl/Ons-aanbod/Rolluiken/Inbouwrolluiken/Download-brochure-Rolluiken-NL/` | 301 naar `/brochures`, 200                          |
| frans adres     | `/fr/Contact/`                                                           | 301 naar `/fr/contact`, 200                           |
| apex zonder www | `/`                                                                      | 301 naar `https://winsol-brebo.be/`, 200              |
| querystring     | `/nl/Contact/?utm_source=…&utm_medium=…&utm_campaign=…`                  | 301 met de parameters onveranderd, 200                |
| nergens op uit  | `/nl/Offerte-aanvragen/`                                                 | 301 naar `/nl/offerte-aanvragen`, dáár 404            |

De gemeentepagina en de klimregel staan allebei niet als eigen regel in de
tabel. Ticket 01 heeft de patroonlaag namelijk niet gebouwd: er is één tabel en
één algoritme, en een gemeentepagina klimt gewoon naar de wortel van zijn
productgroep. De twee rijen verschillen dus in de diepte en niet in het
mechanisme, en daarom klimt de gemeentepagina één niveau en het klimadres twee.
Het laatste adres staat niet in de sitemap van de oude site en is dus verzonnen;
het toetst dat een onbekend adres op de 404 van de nieuwe site komt en niet op
de homepage.

Elk adres gaat over apex én `www`, in de steekproef en in de toets. De
geïndexeerde links staan grotendeels op `www`, en de spec wil ze allebei
rechtstreeks op de nieuwe site hebben.

**De bestemmingskant is wél al geverifieerd, in productie.** Alle acht
bestemmingen zijn vandaag op de live nieuwe site opgevraagd. Zeven geven een
200 zonder tussensprong, en `/nl/offerte-aanvragen` geeft de 404 die het hoort
te geven. De helft van dit ticket die niets met DNS te maken heeft is daarmee
rond: wat er straks doorgestuurd wordt, bestaat.

```
$ .scratch/redirects/steekproef.sh --bestemmingen
...
Alles goed.
```

**Het gereedschap.** `.scratch/redirects/steekproef.sh` draait de steekproef en
telt per adres de statuscode, het aantal sprongen, het eindadres en of de keten
onderweg naar `http` zakt. Drie standen:

| stand            | wat het doet                                                  | wanneer                         |
| ---------------- | ------------------------------------------------------------- | ------------------------------- |
| (geen)           | de volle steekproef over het echte DNS                        | ná de omzetting, ander IP       |
| `--preflight`    | het oude domein hard naar de nieuwe server, buiten het DNS om | zodra het domein in Forge staat |
| `--bestemmingen` | alleen de bestemmingen op de nieuwe site                      | altijd, ook vanaf deze machine  |

Op de oude host eist het script precies één sprong, op de nieuwe site nul. Zo
valt een bestemming die zélf nog omleidt door de mand, en dat is juist de keten
die dit ticket moet uitsluiten.

`--preflight` is de interessante: die toetst de volle tabel over het echte
nginx en de echte PHP, vóór er bij de registrar iets omgaat. Hij zegt niets
over het certificaat, want dat kan pas ná de omzetting aangevraagd worden, en
gebruikt daarom `--insecure`.

De hele steekproef kost een stuk of veertig verzoeken: twee hosts maal acht
adressen maal de sprong erachteraan. Dat is ruim onder de grens, maar draai hem
niet vanaf de ontwikkelmachine. Die is op 2026-09-09 rond 16:30 geband en
krijgt tot ongeveer 2026-09-10 18:30 een 503 op elk verzoek aan het oude
domein.

**En let op de mail.** Uit ticket 03, maar het hoort ook hier: op
`winsoldilbeek.be` loopt mail. De `MX`-regels wijzen naar Google Workspace en
het `SPF`-record noemt de oude server (`ip4:185.162.30.0/24`). De opdracht aan
de registrar is "wijzig deze twee `A`-regels", niet "verhuis het domein". Wie
de zone in zijn geheel meeneemt neemt `MX` en `SPF` mee, en dan valt de mail
stil terwijl de site er prima uitziet. Toets dat na de omzetting mee:

```
dig +short winsoldilbeek.be MX     de vijf Google-regels staan er nog
```

**Wat de mens doet.**

1. Ticket 02 afmaken, `SITE_INDEXABLE=true` op productie. Zolang dat niet
   gebeurd is serveert de nieuwe site `Disallow: /` en `X-Robots-Tag: noindex,
   nofollow`, en verdampt precies de waarde die deze operatie moet overdragen.
2. Deze tak samenvoegen en uitrollen. Zonder dat geeft de nieuwe server een 404
   op elk oud adres.
3. Ticket 03 afwerken: domein in Forge, dan de twee `A`-regels bij de
   registrar, dan het certificaat.
4. Tussen stap 3a en 3b: `.scratch/redirects/steekproef.sh --preflight`. Alles
   behalve DNS en certificaat is dan al getoetst, en een fout is op dat moment
   nog gratis terug te draaien.
5. Na de omzetting, vanaf een ander netwerk dan de ontwikkelmachine:

```
.scratch/redirects/steekproef.sh
dig +short winsoldilbeek.be MX
```

6. De uitkomst hierboven bijschrijven en de vinkjes zetten.
