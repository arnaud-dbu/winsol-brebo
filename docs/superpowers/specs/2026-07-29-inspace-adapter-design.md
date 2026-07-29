# Inspace/Nova adapter — ontwerp

Datum: 2026-07-29
Status: goedgekeurd ontwerp, nog niet geïmplementeerd

## Aanleiding

Inspace verkoopt Nova, een dienst die SEO-content schrijft en publiceert op de
site van een klant. Ze hebben één integratie per CMS; voor WordPress bestaat die
al. Voor Statamic niet, en die bestaat ook niet uit de doos: Statamic's eigen
REST- en GraphQL-API's zijn read-only.

Inspace wacht sinds 15 april op API-documentatie van onze kant. Onze vraag naar
hún WordPress-datamodel is nooit beantwoord. Dirk Versteeg (Product Director) gaf
op 29 juni wél de requirements: als de adapter daaraan voldoet bouwen zij de
koppeling in ±16 uur, waarna een nieuwe klant ±30 minuten configuratie kost.

Wij schrijven dus het contract en leveren het als OpenAPI-spec aan. Zij bouwen
ertegen.

## Requirements van Inspace

Letterlijk uit de mail van 29 juni:

- alle pagina's ophalen
- details van een specifieke pagina ophalen
- een nieuwe pagina aanmaken
- een pagina aanpassen
- media uploaden zodat die aan een pagina toegevoegd kan worden
- authenticatie via bearer token
- gesplitste productie- en testomgeving
- CMS-toegang per klant

Bij de eerste vier staat telkens "belangrijk dat hier wordt meegenomen dat we
voor subscription 2/3 klanten ook service pages toegang moeten hebben".

## Scope

**In scope:** blogartikels, dat wil zeggen de `articles`-collectie. Die heeft een
vaste vorm — page header plus één groot Bard-veld — en is daarmee het enige
contenttype dat zich zonder verlies op een plat artikelmodel laat afbeelden.
Nova mag artikels aanmaken en volledig aanpassen.

**Lezen is breder dan schrijven.** `GET` geeft elke gepubliceerde entry terug,
over alle collecties heen, met titel, URL en type. Alleen artikels zijn
schrijfbaar. Dat dekt Inspace' requirement, kost weinig, en geeft Nova de
sitestructuur die het nodig heeft om interne links te leggen naar service- en
productpagina's — net waar de SEO-waarde zit.

**Buiten scope:** de page builder. Of Nova daar überhaupt bij moet kunnen is
onduidelijk en onbeantwoord. Het contract wordt gebouwd rond één `content`-veld,
waardoor page-builder-ondersteuning later additief is: een `type`-discriminator
op de pagina en per-settype veldbeschrijvingen erbij. Er hoeft vandaag niets
voor gereserveerd te worden.

**Bewust weggelaten:** een `/schema`-endpoint dat per site beschrijft wat
schrijfbaar is. Overwogen en verworpen om het oppervlak klein te houden. Gevolg
dat we accepteren: bij elke nieuwe klant moeten we zelf doorgeven welke velden
schrijfbaar zijn, in plaats van dat Nova dat uitleest. Als de koppeling over
meerdere sites uitrolt is dit de eerste kandidaat om alsnog toe te voegen.

## Plaats in de codebase

App-code in `winsol-brebo` onder `app/Inspace/`, met de grenzen getrokken alsof
het al een package is: geen enkele verwijzing naar winsol-specifieke blueprints,
collecties of veldnamen in de code. Alles wat sitespecifiek is staat in
`config/inspace.php`.

Zodra de koppeling met Inspace live en stabiel is, verhuist `app/Inspace/` naar
een eigen Composer-package voor `statamic-base`. Door bovenstaande discipline is
dat een verhuizing van een map plus een service provider, geen herschrijving.

Routes onder `/api/inspace/v1/`, bewust naast Statamic's eigen `/api/`. Die
laatste is read-only en moet apart aangezet worden; een eigen namespace voorkomt
collisie en verwarring.

## Endpoints

| Methode | Pad | Doel |
|---|---|---|
| `GET` | `/api/inspace/v1/pages` | alle gepubliceerde entries, gepagineerd |
| `GET` | `/api/inspace/v1/pages/{id}` | detail van één entry |
| `POST` | `/api/inspace/v1/pages` | nieuw artikel |
| `PATCH` | `/api/inspace/v1/pages/{id}` | artikel bijwerken, partieel |
| `POST` | `/api/inspace/v1/media` | afbeelding uploaden |

`{id}` is de Statamic entry-UUID, niet de slug. Die overleeft een hernoeming.

### GET /pages

Query-parameters: `collection`, `editable` (bool), `status`, `page`, `per_page`
(standaard 50, max 200).

Per entry: `id`, `collection`, `title`, `url`, `status`, `updated_at`,
`editable`.

Voor schrijfbare collecties komen ook drafts in de lijst: Nova moet een artikel
dat het zelf als draft aanmaakte kunnen terugvinden en bijwerken. Van
niet-schrijfbare collecties worden alleen gepubliceerde entries getoond — drafts
daarvan zijn intern werk van de klant en gaan Nova niet aan.

### GET /pages/{id}

Voor artikels: alle velden uit de mapping hieronder, met `content` als HTML.
Voor niet-schrijfbare entries: `id`, `collection`, `title`, `url`, `editable:
false`, de SEO-velden, en `content: null`. De page builder laat zich niet
betrouwbaar als platte HTML weergeven en we doen ook niet alsof.

### POST /pages

Verplicht: `title`, `content`. Optioneel: `intro`, `slug`, `date`, `status`,
`image`, `meta_title`, `meta_description`, `meta_image`, `seo_noindex`.

Ontbreekt `slug`, dan wordt die van de titel afgeleid. Ontbreekt `date`, dan
vandaag. Antwoordt synchroon met `201` en het volledige object inclusief `id` en
`url`.

### PATCH /pages/{id}

Partieel: alleen meegestuurde velden worden aangepast. Op een niet-schrijfbare
entry: `403` met uitleg welke collecties wel schrijfbaar zijn.

### POST /media

`multipart/form-data`, veld `file`. Toegestaan: jpg, png, webp, gif. Maximum uit
config. Landt in de geconfigureerde container en map. Antwoordt met `id`, `url`,
`width`, `height`, `filename`.

De `id` uit deze response is wat Nova in `image` of `meta_image` zet. De `url` is
wat Nova in de HTML-body mag gebruiken.

## Veldmapping

Config-gedreven, in `config/inspace.php`. Voor winsol-brebo:

| API-veld | `article` blueprint | Type |
|---|---|---|
| `title` | `title` | text |
| `intro` | `intro` | textarea |
| `content` | `redactor` | bard |
| `image` | `image` | assets, max 1 |
| `slug` | `slug` | slug |
| `date` | `date` | date |
| `status` | published-vlag van de entry | bool |
| `meta_title` | `meta_title` | text |
| `meta_description` | `meta_description` | textarea |
| `meta_image` | `meta_image` | assets, max 1 |
| `seo_noindex` | `seo_noindex` | toggle |

De SEO-velden zijn over projecten heen stabiel: `AddDefaultBlueprintTabs` injecteert
de `seo`-fieldset in elke collectie-blueprint van de base install. Alleen de
contentvelden verschillen per site en die staan daarom in config.

`status` accepteert `draft` en `published`, zoals WordPress. Nova bepaalt zelf
of iets live gaat; er is geen goedkeuringsstap aan onze kant.

## Contentpijplijn

Het hart van de adapter en de enige plek waar echt iets kan misgaan. Statamic's
`Statamic\Fieldtypes\Bard\Augmentor` doet het zware werk en levert een
symmetrisch paar:

- `renderHtmlToProsemirror(string $html): array`
- `renderProsemirrorToHtml(array $doc): string`

Beide configureren de tiptap-editor met Statamic's eigen extensies voor
asset-nodes en interne links. We bouwen die conversie dus niet zelf. De Augmentor
wordt geïnstantieerd met het Bard-fieldtype uit de blueprint, zodat de toegelaten
nodes uit de echte veldconfiguratie komen.

**Binnenkomend:**

1. HTML sanitizen tegen een whitelist die wordt afgeleid uit de Bard-buttonconfig
   van het veld, niet uit een hardgecodeerde lijst. De `redactor`-fieldset staat
   vandaag h2, h3, bold, italic, lijsten, anchor en table toe.
2. `<img src>` resolven naar een asset-ID. Verwijst de URL niet naar een asset in
   onze container, dan `422` met de boodschap dat `/media` eerst aangesproken
   moet worden. Geen externe hotlinks in de body.
3. Interne links omzetten naar `statamic://entry::uuid`, zodat ze een
   slug-wijziging overleven.
4. `renderHtmlToProsemirror()` en opslaan.

Wat bij stap 1 gestript wordt komt terug in een `warnings`-array op de response.
Nova stuurt gegarandeerd ooit `<h1>`, `<blockquote>`, `<figure>` of inline
styles; dan moet zichtbaar zijn dat die niet bestaan in plaats van dat ze stil
verdwijnen.

**Uitgaand:** `renderProsemirrorToHtml()`, met `withStatamicImageUrls()` zodat
afbeeldingen een bruikbare `src` krijgen.

**Verliesvrijheid.** `GET` → `PATCH` met ongewijzigde `content` moet exact
dezelfde opgeslagen ProseMirror opleveren. Nova haalt op, past aan, stuurt terug;
wat de whitelist bij stap 1 wegstript is daarna definitief weg. Dit is een
testcase, geen aanname.

## Authenticatie

`Authorization: Bearer <token>`. Tokens gehasht in config, gevoed uit `.env`,
vergeleken met `hash_equals`. Middleware op de hele routegroep, met rate limit.

Geen Sanctum: dat geeft per-token scopes en intrekking via de database, maar
vraagt migrations en een DB-afhankelijkheid die we op elke base install zouden
meeslepen. Voor één koppelende partij per site is een geroteerde env-variabele
genoeg. Als er ooit meerdere partijen tegelijk koppelen is dit het punt om te
herzien.

Elke schrijfactie wordt gelogd: token-label, IP, methode, entry-ID.

## Foutafhandeling

JSON, consistent van vorm:

- `401` ontbrekend of ongeldig token
- `403` schrijven op een niet-schrijfbare entry
- `404` onbekende entry
- `422` validatiefout, met `errors` per veld
- `429` rate limit

Naast `errors` kan elke succesvolle schrijfrespons een `warnings`-array dragen
voor niet-blokkerende zaken zoals gestripte HTML-nodes.

## Configuratie

`config/inspace.php`:

- `readable`: welke collecties `GET` teruggeeft, standaard alle
- `writable`: per collectie het blueprint en de veldmapping
- `assets`: container en map voor uploads, plus maximale bestandsgrootte
- `tokens`: gehashte tokens met een label per partij
- `rate_limit`

## Testen

PHPUnit met 1G geheugen, nooit `php artisan test` (zie CLAUDE.md).

- round-trip: HTML → Bard → HTML is verliesvrij voor de toegelaten subset
- sanitizing: niet-toegelaten nodes worden gestript én gemeld in `warnings`
- afbeeldingen: bekende asset-URL wordt een asset-node, externe URL geeft `422`
- interne links worden `statamic://entry::uuid`
- auth: ontbrekend, fout en geldig token
- `403` bij schrijven op een niet-schrijfbare collectie
- validatie op `POST` en `PATCH`
- media-upload, inclusief afgewezen bestandstypes
- paginering op `GET /pages`

## Aanlevering aan Inspace

- OpenAPI 3.1-spec plus een korte begeleidende markdown
- een staging-URL en een token
- CMS-toegang voor de klant

## Openstaande punten

- **Testomgeving.** Dirk vroeg expliciet om gesplitste productie en test. Of
  winsol-brebo staging heeft is nog niet nagegaan. Infravraag, geen blocker voor
  de bouw, wel voor de oplevering.
- **Page builder.** Onduidelijk of Nova daar toegang toe moet hebben. Pas
  beantwoorden als Inspace er zelf om vraagt.
- **WordPress-datamodel.** Nooit ontvangen. Het risico dat we accepteren: hun
  veldnamen kunnen afwijken van de onze, wat aan hún kant een mappinglaag vraagt.
  Zij bouwen die koppeling toch al, dus dat is een aanvaardbare kost — maar het
  is de reden om de OpenAPI-spec vroeg te sturen in plaats van na oplevering.
