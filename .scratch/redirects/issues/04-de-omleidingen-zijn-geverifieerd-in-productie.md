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
