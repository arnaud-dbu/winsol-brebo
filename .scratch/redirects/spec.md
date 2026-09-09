# Spec: redirects van de oude site naar de nieuwe site

Status: ready-for-agent

## Problem Statement

Winsol Brebo verhuist van de oude site (`winsoldilbeek.be`) naar de nieuwe site
(`winsol-brebo.be`). De oude site telt minstens 1674 adressen die jarenlang
geïndexeerd, gedeeld en in e-mails geplakt zijn. Gaat het oude domein zonder
doorverwijzing uit de lucht, dan landt elke bezoeker die via Google, een oude
mail of een externe link binnenkomt op niets, en verdampt alles wat de oude site
aan zoekwaarde had opgebouwd.

De adressen van beide sites staan bovendien los van elkaar. De oude site zet
Nederlands onder `/nl/`, de nieuwe zet het op de root. De oude gebruikt
hoofdletters en afsluitende slashes (`/nl/Ons-aanbod/Rolluiken/`), de nieuwe
kleine letters zonder slash (`/aanbod/rolluiken`). Er is dus geen mechanische
vertaling: er moet een tabel komen.

## Solution

Het oude domein gaat naar dezelfde Forge-server wijzen als de nieuwe site. Deze
codebase krijgt een redirecttabel en een middleware die elk oud adres in **één
hop** met een 301 omzet naar zijn tegenhanger op de nieuwe site.

De tabel kent drie lagen, in deze volgorde:

1. **Exacte regels** voor de ongeveer 161 echte pagina's van de oude site (83
   Nederlands, 78 Frans).
2. **Patroonregels** voor de 1512 gemeentepagina's: twaalf regels, één per
   productgroep per taal, die elk naar de bijbehorende range op de nieuwe site
   wijzen.
3. **De klimregel** als vangnet. Staat een adres in geen van beide lagen, dan
   klimt de middleware segment voor segment omhoog in het pad tot hij een adres
   vindt dat wél in de tabel staat, en gebruikt die bestemming. Lukt dat nergens,
   dan volgt een 404.

Die derde laag is er omdat de sitemap van de oude site aantoonbaar onvolledig is.
`/nl/Home/`, `/Winsol-ramen/` en verschillende Franse productpagina's bestaan wel
maar staan er niet in. Alle gevonden gaten worden door de klimregel of door een
patroonregel opgevangen.

## User Stories

1. Als bezoeker die via Google op een oud adres klikt, wil ik op de
   overeenkomstige pagina van de nieuwe site landen, zodat ik vind wat ik zocht.
2. Als bezoeker die een oude link uit een e-mail van twee jaar geleden opent, wil
   ik niet op een foutpagina komen, zodat ik het bedrijf niet als verdwenen
   ervaar.
3. Als bezoeker die een oud adres met een afsluitende slash opent, wil ik in één
   sprong op de nieuwe pagina komen, zodat ik geen keten van omleidingen doorloop.
4. Als bezoeker die `winsoldilbeek.be` zonder `www` intikt, wil ik rechtstreeks op
   de nieuwe site komen, zodat ik niet eerst langs twee tussenstappen ga.
5. Als bezoeker met een campagnelink met `utm`-parameters, wil ik dat die
   parameters de omleiding overleven, zodat de meting van die campagne blijft
   kloppen.
6. Als bezoeker die een oud adres over `https` opent, wil ik nooit stilzwijgend
   naar `http` gedegradeerd worden, zodat mijn verbinding beveiligd blijft.
7. Als Franstalige bezoeker die op een oud `/fr/`-adres klikt, wil ik op de Franse
   versie van de nieuwe site landen, zodat ik niet plots Nederlands lees.
8. Als bezoeker die op een oude gemeentepagina klikt, wil ik op de productgroep
   uitkomen waar die pagina over ging, zodat het onderwerp klopt ook al is de
   gemeente weg.
9. Als bezoeker die een oud adres opent dat niemand in de tabel heeft gezet, wil
   ik op het dichtstbijzijnde bovenliggende onderwerp landen, zodat ik nog steeds
   in de buurt van mijn vraag uitkom.
10. Als bezoeker die een oud adres opent waar zelfs geen bovenliggend onderwerp
    voor bestaat, wil ik een nette foutpagina van de nieuwe site zien, zodat ik
    weet waar ik ben.
11. Als Google, wil ik een 301 en geen 302, zodat ik de zoekwaarde van het oude
    adres naar het nieuwe overdraag.
12. Als Google, wil ik dat een omleiding op een pagina uitkomt die zelf een 200
    geeft, zodat ik geen zoekwaarde naar een foutpagina verplaats.
13. Als Google, wil ik geen ketens van omleidingen doorlopen, zodat ik het
    crawlbudget niet verspil.
14. Als Google, wil ik dat de nieuwe site indexeerbaar is op het moment dat de
    omleidingen aangaan, zodat de overgedragen waarde ergens kan landen.
15. Als ontwikkelaar, wil ik de tabel in git hebben staan, zodat een collega hem
    kan nalezen en een wijziging door review gaat.
16. Als ontwikkelaar, wil ik dat een test aantoont dat geen enkele regel op een
    foutpagina uitkomt, zodat de gevaarlijkste fout van een migratie niet
    ongemerkt live gaat.
17. Als ontwikkelaar, wil ik dat die test offline draait, zodat hij in CI werkt
    zonder de oude site te bevragen.
18. Als ontwikkelaar, wil ik dat de test ook echt rood wordt als ik een regel
    breek, zodat ik weet dat hij iets bewijst.
19. Als beheerder, wil ik dat het oude domein geregistreerd blijft, zodat de
    omleidingen niet stilvallen bij het verlopen van het domein.
20. Als beheerder, wil ik dat de omleidingen ook werken voor een adres dat iemand
    op de nieuwe site plakt, zodat een gekopieerde oude link overal opgaat.

## Implementation Decisions

**Waar de omleiding draait.** In deze Laravel-applicatie, niet in een addon en
niet in de nginx-config. De regels staan daarmee in git, gaan door review en zijn
met phpunit te toetsen. Dat sluit aan op `RedirectTrailingSlash`, dat al zo
gebouwd is. De Statamic-addon Simple Redirects is overwogen en afgevallen: die is
bedoeld voor redacteuren die doorlopend losse regels beheren, niet voor één
grote batch die in git hoort.

**Waar de tabel staat.** Een apart bestand in de repo, gescheiden van de
middleware die hem uitvoert. De middleware bevat geen adressen.

**De middleware in de keten.** Na `SecurityHeaders`, vóór `RedirectTrailingSlash`.
Die volgorde is nodig om twee redenen. Ten eerste geeft `RedirectTrailingSlash`
een 301 zonder `$next` aan te roepen, dus alles wat erna staat draait niet meer
voor een adres met een afsluitende slash, en alle oude adressen hebben er een.
Ten tweede wikkelt alleen middleware die ervóór staat zich nog om die respons
heen, wat voor de beveiligingsheaders geldt.

**Slash-ongevoelig matchen.** De tabel matcht een pad met en zonder afsluitende
slash op dezelfde regel. Dat houdt het op één hop; zou `RedirectTrailingSlash`
eerst aan de beurt zijn, dan werd het er twee.

**Beide hosts.** Op het oude domein wijst de omleiding altijd naar
`winsol-brebo.be`. Op de nieuwe host wordt alleen het pad omgezet. Dat kost niets
extra en vangt oude links op die iemand op het nieuwe domein plakt.

**`www` en apex.** Allebei rechtstreeks naar `https://winsol-brebo.be`. De oude
site doet nu apex naar `www` naar `http`, drie sprongen met een degradatie erin.
Dat wordt er één.

**Querystrings.** Blijven ongewijzigd staan, inclusief volgorde en vorm, net
zoals `RedirectTrailingSlash` dat al doet. `getQueryString()` sorteert en
normaliseert, en verandert daarmee een gedeelde campagnelink.

**Alleen GET en HEAD.** Een 301 op een POST laat de browser opnieuw versturen
zonder body, waarmee een formulier stil leegloopt. Dezelfde afweging als in
`RedirectTrailingSlash`.

**De gemeentepagina's.** Twaalf patroonregels, elk van een productgroep naar zijn
range op de nieuwe site. De keuze tussen doorsturen, verdwenen melden en opnieuw
bouwen is gemaakt: doorsturen. Er is geen verwachting dat de posities op
zoekopdrachten als "ramen Aartselaar" behouden blijven; 126 adressen naar
dezelfde bestemming leest Google grotendeels als soft 404. De pagina's opnieuw
bouwen op de nieuwe site is een eigen project.

**De klimregel.** Klimt segment voor segment omhoog en gebruikt de eerste
bestemming die hij tegenkomt. Een adres dat nergens op uitkomt geeft een 404 van
de nieuwe site, nooit de homepage: massaal doorsturen naar de root leest Google
als soft 404 en zet de bezoeker op een pagina die zijn vraag niet raakt.

**De bron van de adreslijst.** De sitemap van de oude site, opgehaald op
2026-09-09, met 1674 adressen. Die is aantoonbaar onvolledig; de klimregel is het
antwoord daarop. Google Search Console zou de prioriteiten kunnen scherpstellen
maar is voor deze oplevering niet beschikbaar.

**Volgorde bij de lancering.** De nieuwe site moet indexeerbaar zijn vóór het DNS
van het oude domein verhuist. Andersom stuur je bezoekers en Google naar een site
die op noindex staat, en verdwijnt de overgedragen waarde. Het certificaat voor
het oude domein kan pas aangevraagd worden nadat het DNS wijst, dus het domein
hoort klaar te staan in Forge voordat er bij de registrar iets verandert.

## Testing Decisions

**Wat een goede test hier is.** Extern gedrag: welke statuscode komt eruit en
waar wijst de `Location` heen. Niet hoe de tabel intern is opgeslagen of hoe de
klimregel zijn segmenten aftelt.

**De seam.** De HTTP-kernel, precies zoals `tests/Feature/TrailingSlashTest.php`
het al doet. Dat is de hoogste seam die er is en hij bestaat al. `$this->get()`
kan niet: `prepareUrlForRequest()` trimt de afsluitende slash eraf, waardoor de
test nooit verstuurt wat hij wil toetsen. Via `Request::create()` en de kernel
loopt het verzoek door de echte middleware-stack, en kan er ook een eigen host
meegegeven worden. Er komt geen tweede seam bij.

**De vier toetsen.**

1. *Elke bestemming bestaat.* Verzamel de unieke bestemmingen uit de tabel, dat
   zijn er ongeveer zestig, en toets per stuk dat die door de kernel een 200
   geeft. Dit vangt de gevaarlijkste fout van een migratie: een omleiding die
   werkt en op een foutpagina uitkomt.
2. *Elke regel wijst naar een geverifieerde bestemming.* Een zuivere
   gegevenscontrole over alle 1673 regels, zonder de pagina's te renderen.
   Daarmee blijft de suite snel.
3. *Representatieve adressen leiden echt om.* Per laag een handvol gevallen door
   de kernel: een exacte regel, elk van de twaalf patronen, een klim van één
   niveau, een klim van twee, een adres dat nergens op uitkomt, een adres met en
   zonder afsluitende slash, een met querystring, een op de apex van het oude
   domein, en een POST die niet mag omleiden.
4. *Geen ketens en geen lussen.* Geen bestemming komt zelf als bronregel in de
   tabel voor.

**Prior art.** `tests/Feature/TrailingSlashTest.php` voor de kernel-seam en de
querystring-gevallen, `tests/Feature/SecurityHeadersTest.php` en
`tests/Feature/NoIndexHeaderTest.php` voor het toetsen van middleware die vóór de
omleiding hoort te draaien.

**Bewijs dat de test iets bewijst.** Breek bewust één regel in de tabel en stel
vast dat toets 1 of 2 rood wordt. Een test die groen blijft bij een kapotte bron
bewijst niets.

**Draaien.** `phpunit` met 1G geheugen, nooit `php artisan test`.

## Out of Scope

- **De gemeentepagina's opnieuw bouwen** op de nieuwe site. Dat is optie C uit de
  afweging en een eigen project met eigen planning en budget.
- **De DNS-omzetting en het certificaat.** Dat gebeurt in Forge en bij de
  registrar, buiten deze codebase. De spec legt wel de volgorde vast.
- **`SITE_INDEXABLE` omzetten.** Een omgevingsvariabele op productie, geen code.
- **Het uitfaseren van de oude hosting.** Pas aan de orde als de omleidingen
  aantoonbaar werken.
- **Prioriteren op basis van Search Console.** De toegang is er niet en de
  oplevering wacht er niet op.
- **Een beheerscherm om zelf redirects toe te voegen.** Niet gevraagd. Komt die
  wens later, dan is dat het moment om de addon opnieuw te wegen.

## Further Notes

Het IP van de ontwikkelmachine staat tot ongeveer 2026-09-10 18:30 geblokkeerd
door de oude site: die bant 24 uur na 200 verzoeken. Adressen op de oude site
zijn tot dat moment niet te verifiëren. De tabel wordt daarom gebouwd op de
sitemap van 2026-09-09, met de klimregel als vangnet. De steekproef na de
DNS-omzetting moet vanaf een ander IP gebeuren.

De oude site heeft een tweede adres voor dezelfde pagina gevonden,
`/fr/Notre-gamme/Couvertures-de-terrasse/Pergola-SO-/` met een streepje op het
eind naast de variant zonder. Er kunnen meer van die dubbelvormen zijn; de
klimregel vangt ze op.
