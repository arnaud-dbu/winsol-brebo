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

**Fase 1 is blog.** De `articles`-collectie is schrijfbaar: Nova mag artikels
aanmaken en volledig aanpassen.

**Lezen is breder dan schrijven.** `GET` geeft entries terug over alle
collecties heen, met titel, URL en type. Alleen artikels zijn schrijfbaar. Dat
dekt Inspace' leesrequirement en geeft Nova de sitestructuur die het nodig heeft
om interne links te leggen naar service- en productpagina's — net waar de
SEO-waarde zit.

**Service pages zijn fase 2, en dat is een bewust risico.** Dirk herhaalt bij
alle vier de pagina-endpoints — óók bij aanmaken en aanpassen — dat subscription
2/3 klanten toegang tot service pages nodig hebben. Letterlijk gelezen wil Nova
die dus schrijven, niet alleen lezen. Fase 1 bedient daarmee subscription 1.
Inspace moet vóór ze aan hun 16 uur beginnen weten dat fase 1 blog-only is,
anders bouwen ze tegen een contract dat meteen moet uitbreiden.

Het contract is zo vormgegeven dat uitbreiden additief is: `collection` staat al
in elk object en de blokkenstructuur van `content` is niet artikelspecifiek.

**Bewust weggelaten:** een `/schema`-endpoint dat per site beschrijft wat
schrijfbaar is. Overwogen en verworpen om het oppervlak klein te houden. Gevolg
dat we accepteren: bij elke nieuwe klant geven we zelf door welke velden
schrijfbaar zijn. Als de koppeling over meerdere sites uitrolt is dit de eerste
kandidaat om alsnog toe te voegen.

**Geen `DELETE`.** Inspace vroeg er niet om. Terugtrekken kan via `status:
draft`.

## Voorwerk in winsol-brebo

Twee dingen moeten recht vóór de adapter er content in schrijft.

**Het artikeltemplate klopt niet met het veld.**
`resources/views/articles/show.antlers.html:4` loopt over `{{ redactor }}` en
test op `type == "text"`, `"image"` en `"video"` — de vorm die Bard aanneemt
mét sets. De `redactor`-fieldset heeft geen sets, dus die takken worden nooit
waar en de artikelbody rendert leeg.

We lossen dit op aan de kant van het veld: `redactor` krijgt een `image`- en een
`video`-set, zodat het bestaande template klopt. De `video`-set gebruikt een
`video`-fieldtype met handle `video`, want `partials/video.antlers.html` verwacht
`is_embeddable` en `embed_url` op dat veld. De `image`-set krijgt een
assets-veld met `max_files: 1` op container `assets`.

Bestaande artikels blijven werken: hun platte ProseMirror-nodes worden na de
wijziging één `text`-segment.

**Waar `image` en `intro` terechtkomen.** Geen van beide rendert op het
artikeldetail — `headers/default` toont alleen `title`, want die partial gebruikt
`text` en `divider`, velden die het artikelblueprint niet heeft. Beide worden wél
gebruikt op de blogoverzichtskaart in `partials/article.antlers.html`: `image`
als thumbnail, `intro` als excerpt. Daarom is `image` verplicht bij create —
zonder afbeelding staat er een leeg kader op het overzicht.

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
| `GET` | `/api/inspace/v1/pages` | entries over alle leesbare collecties, gepagineerd |
| `GET` | `/api/inspace/v1/pages/{id}` | detail van één entry |
| `POST` | `/api/inspace/v1/pages` | nieuw artikel |
| `PATCH` | `/api/inspace/v1/pages/{id}` | artikel bijwerken, partieel |
| `POST` | `/api/inspace/v1/media` | afbeelding uploaden |

`{id}` is de Statamic entry-UUID, niet de slug. Die overleeft een hernoeming.

Elk endpoint accepteert een optionele `site`-parameter. winsol-brebo heeft geen
`resources/sites.yaml` en draait dus single-site, maar de base install gaat naar
klanten waar NL/FR eerder regel dan uitzondering is. De parameter nu opnemen kost
een default en voorkomt dat we Inspace later een tweede contractversie moeten
sturen. Ontbreekt hij, dan geldt de default site.

### GET /pages

Query-parameters: `collection`, `editable` (bool), `status`, `site`, `page`,
`per_page` (standaard 50, max 200).

Per entry: `id`, `collection`, `title`, `url`, `status`, `updated_at`,
`editable`.

Voor schrijfbare collecties komen ook drafts in de lijst: Nova moet een artikel
dat het zelf als draft aanmaakte kunnen terugvinden en bijwerken. Van
niet-schrijfbare collecties worden alleen gepubliceerde entries getoond — drafts
daarvan zijn intern werk van de klant en gaan Nova niet aan.

Entries zonder route krijgen `url: null`. `quicklinks` heeft geen `route` en is
dus niet bereikbaar; die staan wel in de lijst maar zijn nutteloos als linkdoel.

### GET /pages/{id}

Voor artikels: alle velden uit de mapping hieronder, met `content` als
blokkenarray. Voor niet-schrijfbare entries: `id`, `collection`, `title`, `url`,
`editable: false`, de SEO-velden, en `content: null`. De page builder laat zich
niet betrouwbaar als platte content weergeven en we doen ook niet alsof.

### POST /pages

Verplicht: `title`, `content`, `image`. Optioneel: `intro`, `slug`, `date`,
`status`, `external_id`, `meta_title`, `meta_description`, `meta_image`,
`seo_noindex`.

Ontbreekt `slug`, dan wordt die van de titel afgeleid. Bestaat die al, dan krijgt
hij een numeriek achtervoegsel — Nova hergebruikt titels bij het herschrijven en
mag daar geen bestaand artikel mee overschrijven. Ontbreekt `date`, dan vandaag.

Antwoordt synchroon met `201` en het volledige object inclusief `id` en `url`.

**`external_id`** is Nova's eigen identifier. Wordt die meegestuurd en bestaat er
al een artikel mee, dan antwoordt de adapter met `200` en het bestaande object in
plaats van een duplicaat aan te maken. Zonder dit levert één timeout gevolgd door
een retry twee identieke artikels op.

### PATCH /pages/{id}

Partieel: alleen meegestuurde velden worden aangepast. Op een niet-schrijfbare
entry: `403` met de lijst van wél schrijfbare collecties.

### POST /media

`multipart/form-data`, veld `file`. Toegestaan: jpg, png, webp, gif. Maximum uit
config. Landt in de geconfigureerde container en map. Antwoordt met `id`, `url`,
`width`, `height`, `filename`.

De upload gaat door Statamic's eigen uploadpad, zodat `AssetUploaded` vuurt en de
bestaande `CompressUploadedAsset`-listener zijn werk doet. Een asset die
rechtstreeks naar disk geschreven wordt slaat die listener over, en dan komen
Nova's beelden ongecomprimeerd binnen — precies het tegendeel van wat de dienst
moet bereiken.

De `id` uit deze response is wat Nova in `image`, `meta_image` of een
image-blok zet.

## Veldmapping

Config-gedreven, in `config/inspace.php`. Voor winsol-brebo:

| API-veld | `article` blueprint | Type |
|---|---|---|
| `title` | `title` | text |
| `intro` | `intro` | textarea |
| `content` | `redactor` | bard met sets |
| `image` | `image` | assets, max 1 |
| `slug` | `slug` | slug |
| `date` | `date` | date |
| `status` | published-vlag van de entry | bool |
| `meta_title` | `meta_title` | text |
| `meta_description` | `meta_description` | textarea |
| `meta_image` | `meta_image` | assets, max 1 |
| `seo_noindex` | `seo_noindex` | toggle |

De SEO-velden zijn over projecten heen stabiel: `AddDefaultBlueprintTabs`
injecteert de `seo`-fieldset in elke collectie-blueprint van de base install.
Alleen de contentvelden verschillen per site en die staan daarom in config.

`status` accepteert `draft` en `published`, zoals WordPress. Nova bepaalt zelf of
iets live gaat; er is geen goedkeuringsstap aan onze kant.

**Onbekende velden geven `422`.** `articles` heeft vandaag geen taxonomie, dus
een `categories` uit Nova's WordPress-model wordt geweigerd in plaats van
genegeerd. Stil slikken laat Inspace geloven dat het werkt. Blijkt hun
contentstrategie op categorieën te leunen, dan voegen we een taxonomie toe — dat
is een bewuste uitbreiding, geen stille.

## Contentvorm

`content` is een array van getypeerde blokken, geen HTML-string:

```json
[
  {"type": "text", "html": "<h2>Kop</h2><p>Tekst met <a href=\"/over-ons\">link</a>.</p>"},
  {"type": "image", "id": "rowid", "asset": "asset-uuid", "enabled": true},
  {"type": "video", "id": "rowid", "url": "https://youtu.be/…", "enabled": true}
]
```

**Waarom geen HTML-string.** Statamic serialiseert een Bard-set in HTML als
`<set>index-0</set>` — een positienummer zonder data. De veldwaarden houdt de
Augmentor apart in geheugen (`Augmentor.php:97`) en splitst de HTML daar
achteraf op (`convertToSets`, regel 137). `renderHtmlToProsemirror()` leest zo'n
marker terug als een lege set: afbeelding, video en row-ID weg. Statamic's eigen
conversie is dus alleen symmetrisch voor een Bard zónder sets. Een eigen
HTML-representatie met `data-`attributen zou kunnen, maar staat of valt met wat
Inspace' sanitizer ermee doet, en faalt stil. De blokkenarray spiegelt wat
Statamic opslaat en is verliesvrij per constructie.

De bloktypes komen één-op-één overeen met de sets: `image` heeft één assets-veld
met handle `image`, `video` één video-veld met handle `video`. Het API-veld `url`
op een video-blok schrijft dus naar de set-handle `video`; die vertaling staat in
de veldmapping, niet in de code. `text` is geen set maar een tekstsegment en
draagt daarom geen `id` of `enabled`.

**`id` en `enabled`.** Sets dragen een row-ID in `attrs.id` en kunnen in de CP
uitgeschakeld staan via `attrs.enabled`. Beide gaan mee naar buiten en worden bij
een update gerespecteerd. Zonder `id` wisselt de row-ID bij elke bewerking;
zonder `enabled` zet Nova een bewust uitgeschakeld blok stilzwijgend weer aan.

**Text-blokken.** De `html` binnen een text-blok gaat door dezelfde
sanitize-stap, met een whitelist afgeleid uit de Bard-buttonconfig van het veld
in plaats van een hardgecodeerde lijst. De `redactor`-fieldset staat vandaag h2,
h3, bold, italic, lijsten, anchor en table toe. Wat gestript wordt komt terug in
een `warnings`-array op de response — Nova stuurt gegarandeerd ooit `<h1>`,
`<blockquote>` of inline styles, en dan moet zichtbaar zijn dat die hier niet
bestaan.

**Afbeeldingen in tekst.** Een `<img src>` binnen een text-blok wordt geresolved
naar een asset-ID. Verwijst de URL niet naar een asset in onze container, dan
`422` met de boodschap dat `/media` eerst aangesproken moet worden. Geen externe
hotlinks.

**Interne links.** Uitgaand worden `statamic://entry::uuid`-links naar echte
URL's gerenderd, anders krijgt Nova onbruikbare hrefs terug. Binnenkomend gaat de
omgekeerde weg: een URL die naar een bestaande entry wijst wordt weer
`statamic://entry::uuid`, zodat de link een slug-wijziging overleeft. Dit is een
expliciete testcase, geen aanname.

**Verliesvrijheid.** `GET` → ongewijzigde `PATCH` moet exact dezelfde opgeslagen
ProseMirror opleveren, inclusief row-ID's en uitgeschakelde sets.

## Schrijfacties serialiseren

Statamic is flat-file met een Stache-index. Nova die parallel tientallen artikels
post kan die index in de knoop leggen. Schrijfacties lopen daarom door een lock,
zodat er nooit twee tegelijk zijn.

De adapter controleert bij het opstarten of revisions aan staan op een
schrijfbare collectie. `articles.yaml` heeft `revisions: false`, maar in de base
install kan dat verschillen — en met revisions aan maakt `save()` een working
copy in plaats van te publiceren, waardoor Nova denkt dat het publiceerde terwijl
er niets live staat.

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
- `422` validatiefout of onbekend veld, met `errors` per veld
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

- round-trip: `GET` → ongewijzigde `PATCH` levert identieke opgeslagen ProseMirror
- row-ID's en `enabled: false` overleven een update
- sanitizing: niet-toegelaten nodes worden gestript én gemeld in `warnings`
- afbeeldingen: bekende asset-URL wordt een asset-node, externe URL geeft `422`
- interne links: uitgaand echte URL, binnenkomend weer `statamic://entry::uuid`
- `external_id`: tweede POST met dezelfde waarde geeft `200`, geen duplicaat
- slug-collisie krijgt een achtervoegsel en overschrijft niets
- auth: ontbrekend, fout en geldig token
- `403` bij schrijven op een niet-schrijfbare collectie
- `422` bij een onbekend veld
- media-upload, inclusief afgewezen bestandstypes en compressie die effectief loopt
- paginering op `GET /pages`

## Aanlevering aan Inspace

- OpenAPI 3.1-spec plus een korte begeleidende markdown
- expliciete vermelding dat fase 1 blog is en service pages later komen
- een staging-URL en een token
- CMS-toegang voor de klant

## Openstaande punten

- **Testomgeving.** Dirk vroeg expliciet om gesplitste productie en test. Of
  winsol-brebo staging heeft is nog niet nagegaan. Infravraag, geen blocker voor
  de bouw, wel voor de oplevering.
- **Welke klant eerst.** De mails spreken van "volgende maand opstarten", gezegd
  op 25 juni. Of winsol-brebo de eerste gekoppelde site is, is niet bevestigd.
  Bepaalt waar staging moet staan.
- **WordPress-datamodel.** Nooit ontvangen. Het risico dat we accepteren: hun
  veldnamen wijken af van de onze, wat aan hún kant een mappinglaag vraagt. Zij
  bouwen die koppeling toch al, dus dat is een aanvaardbare kost — maar het is de
  reden om de OpenAPI-spec vroeg te sturen in plaats van na oplevering.
- **Statische caching** staat op `null` in `.env.example` en is dus vandaag uit.
  Zet een klant het aan, dan moet invalidatie na een API-schrijfactie
  gecontroleerd worden. `ClearSitemapCache` hangt aan `EntrySaved` en vuurt wél
  bij een programmatische save.
