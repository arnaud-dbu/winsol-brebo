# 05: robots.txt antwoordt met een 404

**What to build:** `https://winsol-brebo.be/robots.txt` geeft de juiste tekst
terug met statuscode 404. De inhoud klopt, de code niet, en een zoekmachine kijkt
naar de code.

De oorzaak zit in nginx, niet in de applicatie. Het standaardrecept voor Laravel
zet er een apart blok voor:

```
location = /robots.txt  { access_log off; log_not_found off; }
error_page 404 /index.php;
```

Dat blok heeft geen `try_files`, dus nginx zoekt een bestand `public/robots.txt`
dat er niet is en komt op zijn eigen 404 uit. Via `error_page` gaat het verzoek
alsnog naar `index.php`, de route in `routes/web.php` rendert de juiste tekst, en
de 404 blijft eroverheen staan. Door de kernel gedraaid geeft dezelfde route wel
een 200, dus de applicatie is niet de plek om dit te repareren.

Google leest een 404 als "er is geen robots.txt" en negeert de inhoud. Dat werkt
vandaag toevallig de goede kant op, maar het betekent ook dat de `Sitemap:`-regel
nooit gelezen wordt, en dat een toekomstige `Disallow` geruisloos niets doet.

De ingreep is één regel in de nginx-config van de site in Forge: `try_files $uri
/index.php?$query_string;` in dat blok, of het blok helemaal weg zodat de
algemene `location /` het afhandelt. Daarna nginx herladen.

**Blocked by:** None (can start immediately)

**Status:** ready-for-human

- [ ] `curl -sSI https://winsol-brebo.be/robots.txt` geeft `200`
- [ ] De inhoud is ongewijzigd, met de `Sitemap:`-regel erin
- [ ] De sitemap is daarnaast in Search Console ingediend, zodat de vindbaarheid
      niet van dit ene bestand afhangt

## Comments

**Gevonden bij ticket 02**, bij het nakijken van het tweede vinkje. Apart gezet
omdat het de lancering niet blokkeert en in de serverconfiguratie zit, terwijl
ticket 02 over een omgevingsvariabele gaat. Lokaal in Herd hetzelfde beeld, met
diezelfde twee regels in `herd.conf`.

**De 404 is nagemeten, en de tekst eromheen is intussen veranderd.** Gemeten op
2026-09-09 met `.scratch/redirects/robots.sh`:

```
$ ./.scratch/redirects/robots.sh
robots.txt op https://winsol-brebo.be

  FOUT  statuscode 404 in plaats van 200 (nginx, niet de applicatie: zie de kop van dit script)
  ok    text/plain; charset=utf-8
  ok    User-agent-regel aanwezig
  ok    Sitemap: https://winsol-brebo.be/sitemap.xml
  ok    die sitemap antwoordt met 200

1 keer mis.
```

Lokaal in Herd hetzelfde beeld, met `http://winsol-brebo.test` als basis: dezelfde
404, dezelfde vier regels eronder.

**Ticket 02 is op productie afgemaakt.** `robots.txt` geeft `Disallow:` zonder
`/`, en de homepage geeft geen `X-Robots-Tag` meer. De inhoud staat dus open. Dat
verandert de weging van dit ticket: de zin "dat werkt vandaag toevallig de goede
kant op" gold toen de site zichzelf nog afsloot. Nu wil de site geïndexeerd
worden, en is de statuscode het enige dat nog tussen Google en de
`Sitemap:`-regel staat. De stand van ticket 02 zelf is daar bijgeschreven.

**De inhoud van vandaag, om na de ingreep tegen te vergelijken.** Het tweede
vinkje vraagt of de tekst ongewijzigd blijft, en dat is pas te zeggen met een
opname van ervóór. Dit is `https://winsol-brebo.be/robots.txt` op 2026-09-09, met
de lege eerste regel die de `{{ if }}` in de view achterlaat en de lege regel aan
het eind:

```
$ curl -sS https://winsol-brebo.be/robots.txt | wc -c
72
$ curl -sS https://winsol-brebo.be/robots.txt | shasum -a 256
b9b5aacce264518c8f4ee20ce25646f42c3e8cd981fa61380c28084ba33bdb85
```

Die twee regels maken het tweede vinkje exact toetsbaar: dezelfde 72 bytes en
dezelfde som ná de ingreep, en de inhoud is aantoonbaar ongewijzigd. De ingreep
zit in nginx en raakt de route niet, dus daar hoort niets aan te veranderen. Het
vinkje blijft open tot dat ná de ingreep is nagekeken, want dat is het moment
waarop het moet gelden. `robots.sh` zelf toetst de regels die iets doen en niet de
tekst byte voor byte: een `Sitemap:`-regel die naar een bestaande sitemap wijst,
een `User-agent`-regel, en geen `Disallow: /`.

**Waarom de applicatie het niet kan repareren.** Dit stond als conclusie in de
opdracht, maar niet met de reden erbij. Zonder `=` houdt nginx bij `error_page
404 /index.php` de oorspronkelijke status vast; alleen `error_page 404 =
/index.php` neemt de status van het doorverwezen verzoek over. De route levert
netjes een 200, nginx plakt zijn eigen 404 eroverheen, en er is geen regel PHP
die daar tussen komt. Vandaar dat `RobotsTest` groen staat terwijl het adres in
het echt een 404 geeft.

**Eén blok, in productie één slachtoffer.** Diezelfde regels raken ook
`favicon.ico`, en lokaal in Herd geeft die daarom óók een 404. Op Forge staat er
wel een echt bestand `public/favicon.ico`, dus daar valt alleen `robots.txt` door
de mand.

**Het gereedschap.** `.scratch/redirects/robots.sh` toetst de statuscode, het
content-type, de `User-agent`-regel, de afwezigheid van `Disallow: /`, en of het
adres achter de `Sitemap:`-regel zelf een 200 geeft. Die laatste zit erbij omdat
een 200 op `robots.txt` niets waard is als de sitemap waar hij naar wijst niet
bestaat. Zonder argument draait hij tegen productie, met een basis-URL tegen een
andere omgeving:

```
./.scratch/redirects/robots.sh                            productie
./.scratch/redirects/robots.sh http://winsol-brebo.test   lokaal in Herd
```

Twee verzoeken per run, dus vrij te draaien vanaf de ontwikkelmachine. Het oude
domein komt er niet in voor.

**Wat de suite wél vasthoudt.** `test_no_static_file_shadows_the_route` in
`RobotsTest` houdt tegen dat iemand deze 404 met een bestand `public/robots.txt`
oplost. Dat is precies het bestand dat nginx zoekt, dus het werkt, en het
bevriest tegelijk de inhoud en zet `SITE_INDEXABLE` buitenspel. De statuscode
zelf kan geen enkele toets in de suite zien; die komt uit nginx en staat daarom
in het script hierboven.

**Wat de mens doet.**

1. In Forge, bij de site onder Nginx-configuratie, het blok een `try_files`
   geven zodat het verzoek net als elk ander adres bij `index.php` uitkomt:

```
location = /robots.txt  { access_log off; log_not_found off; try_files $uri /index.php?$query_string; }
```

   Het blok helemaal weghalen werkt ook, dan handelt de algemene `location /`
   het af. Dat kost wel het stilhouden van de logregels.

2. Nginx herladen, en daarna de twee vinkjes zetten:

```
./.scratch/redirects/robots.sh                                    hoort "Alles goed" te geven
curl -sS https://winsol-brebo.be/robots.txt | shasum -a 256       hoort b9b5aacc… te geven
```

3. De sitemap in Search Console indienen, zodat de vindbaarheid niet van dit ene
   bestand afhangt.

4. Lokaal hetzelfde beeld wegnemen, want de ontwikkelmachine hoort niet stiller
   te zijn over deze fout dan productie. De regel staat in
   `~/Library/Application Support/Herd/config/nginx/herd.conf`, en die valt
   buiten deze repo:

```
sed -i '' '/location = \/robots.txt/d' ~/Library/Application\ Support/Herd/config/nginx/herd.conf
herd restart nginx
./.scratch/redirects/robots.sh http://winsol-brebo.test
```
