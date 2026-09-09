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
