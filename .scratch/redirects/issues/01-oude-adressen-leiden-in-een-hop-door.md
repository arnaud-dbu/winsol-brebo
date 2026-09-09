# 01: Oude adressen leiden in één hop naar de nieuwe site

**What to build:** Iemand die een adres van de oude site opent, komt met één
permanente omleiding uit op de pagina van de nieuwe site die over hetzelfde
onderwerp gaat. Dat geldt voor de echte pagina's van de oude site, voor de 1512
gemeentepagina's, voor de brochure-downloadpagina's, en voor adressen die in geen
enkele lijst staan maar wel bestaan.

De mapping ligt vast in `mapping.md` naast dit ticket: 165 regels, plus de
redenering bij de gevallen die niet vanzelf spreken. Neem die over, verzin er
geen nieuwe bij.

Drie dingen die de vorm bepalen. Er is **één tabel en één algoritme**, geen
aparte laag voor patronen: een gemeentepagina vindt geen eigen regel, klimt naar
de wortel van zijn productgroep, en die staat gewoon als normale regel in de
tabel. De klim **stopt bij het laatste segment** en komt dus nooit op de
homepage uit; alles naar de root sturen leest Google als soft 404. En de klim
geldt **alleen op de oude host**, want anders zou elk onbekend pad op de nieuwe
site naar een bovenliggende pagina omleiden in plaats van een 404 te geven.

De omleiding komt in deze applicatie, niet in een addon en niet in de
nginx-config, zodat de regels door review gaan en met phpunit te toetsen zijn.
Ze hoort vóór de bestaande trailing-slash-omleiding te draaien: elk adres van de
oude site eindigt op een slash, dus anders wordt het twee sprongen in plaats van
één.

Deployen naar productie hoort bij dit ticket. De omleiding is inert zolang het
oude domein er nog niet naartoe wijst, dus dat kan veilig vooruit.

**Blocked by:** None (can start immediately)

**Status:** ready-for-human

- [x] Een adres van de oude site geeft een 301, geen 302, en landt op zijn
      tegenhanger uit `mapping.md`
- [x] Een gemeentepagina landt op de productgroep waar hij over ging
- [x] Een adres dat niet in de tabel staat landt op zijn dichtstbijzijnde
      bovenliggende onderwerp
- [x] Een adres waarvoor zelfs dat niet bestaat geeft een 404, niet de homepage
- [x] Een brochure-downloadadres landt op de brochurepagina, in de juiste taal
- [x] Een adres met afsluitende slash komt in één hop aan, niet twee
- [x] De apex van het oude domein komt rechtstreeks op `https`, zonder tussenstap
      via `www` en zonder degradatie naar `http`
- [x] Een querystring overleeft de omleiding, met dezelfde volgorde en vorm
- [x] Een POST leidt niet om
- [x] Op de nieuwe host leidt niets naar zichzelf om, en klimt er niets
- [x] Elke unieke bestemming uit de tabel geeft een 200; er is er geen die op een
      foutpagina uitkomt
- [x] Alle 1674 adressen uit de sitemap van de oude site lossen op naar een
      bestemming die in die controle een 200 gaf
- [x] Geen bestemming komt zelf als bronregel voor, dus geen ketens en geen lussen
- [x] Eén regel bewust breken maakt de suite rood
- [ ] `phpunit` met 1G geheugen draait groen, en de wijziging staat op productie

## Comments

**Opgeleverd, behalve de deploy.** De code staat er, de suite draait groen
(24 toetsen, 3561 asserties) en de 13 rode toetsen elders in de suite waren al
rood vóór deze wijziging — vergeleken met dezelfde suite met de middleware
uitgezet, en dat geeft exact dezelfde 13. Alleen het laatste vinkje ontbreekt:
deployen naar productie kan niet vanaf hier, en daarom staat het ticket op
`ready-for-human` in plaats van afgerond.

**Waar het staat.** `config/legacy_redirects.php` heeft de tabel,
`App\Services\LegacyRedirect` zoekt de bestemming op, `RedirectLegacyUrls`
maakt er een respons van, en die staat in `bootstrap/app.php` na
`SecurityHeaders` en vóór `RedirectTrailingSlash`. De 1674 adressen uit de
sitemap staan als `tests/fixtures/legacy-sitemap.txt`, zodat de toets offline
draait.

**Twee afwijkingen van de spec, allebei bewust.**

1. *Een adres dat nergens op uitkomt.* De spec zegt "Lukt dat nergens, dan volgt
   een 404" en tegelijk "een 404 van de nieuwe site" (user story 10: "een nette
   foutpagina van de nieuwe site"). Die twee vallen niet samen: `abort(404)` op
   de oude host geeft de kale foutpagina van Laravel, en doorlaten laat het oude
   domein de hele nieuwe site serveren. Zo'n adres houdt daarom zijn eigen pad
   en gaat in één sprong mee naar `winsol-brebo.be`, waar het de nette 404 van
   de nieuwe site krijgt. Eén 301 naar een 404, in plaats van een 404 op een
   domein dat er niet meer hoort te zijn.
2. *De klim mag niet op een homepage uitkomen.* `/nl` en `/fr` staan als gewone
   regel in de tabel — dat waren de homepages van de oude site. Zonder extra
   regel klimt `/nl/onbekend` daar dus gewoon naartoe, precies de soft 404 die
   dit ticket wil vermijden. `config/legacy_redirects.homepages` sluit die
   bestemmingen uit voor de klim; een exacte regel als `/nl/home` mag er wél op
   uitkomen.

**De aantallen in dit ticket en in de spec kloppen niet meer.** Het ticket
spreekt van "165 regels", de spec van "alle 1673 regels"; `mapping.md` heeft er
127 en de sitemap 1674 adressen. De tabel volgt `mapping.md`, en een toets
vergelijkt die twee regel voor regel — anders valt een regel geruisloos weg en
absorbeert de klim hem zonder dat de suite iets merkt.
