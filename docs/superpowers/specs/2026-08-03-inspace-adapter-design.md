# Inspace/Nova adapter — ontwerp

Datum: 2026-08-03
Status: goedgekeurd ontwerp, nog niet geïmplementeerd
Vervangt: `2026-07-29-inspace-adapter-design.md`

## Wat er veranderd is sinds 29 juli

De vorige versie legde de adapter in `statamic-base` en nam winsol-brebo als
latere overname. Dat is omgedraaid: **de adapter wordt gebouwd in winsol-brebo**.
Alle vier de argumenten voor de base zijn intussen vervallen.

| Argument van 29 juli | Vandaag |
|---|---|
| blueprints zijn in beide repo's identiek | niet meer: winsol heeft een eigen `article_redactor` (mét `video`-set en `image`-button), een verplichte `themes`-taxonomie, `page_header_image` en een `preview`-fieldset |
| de templatebug komt uit de base, daar één keer repareren | winsol heeft hem al opgelost: `articles/show.antlers.html` doet `if type == 'video'` / `else`, met een comment die de boilerplate-fout benoemt |
| de base heeft de werkende testsuite | winsol heeft ~30 Feature-tests, Unit-tests, `SectionTestCase` en `CreatesTemporaryContent` |
| winsol zit midden in een UI-refactor | de werkboom is schoon |

Daarnaast zijn drie inhoudelijke aannames van de vorige spec achterhaald, alle
drie geverifieerd tegen de codebase:

1. **De `image`-set bestaat hier niet.** Afbeeldingen zitten als inline
   Bard-node ín de tekst (button `image`); alleen `video` is een set. De
   blokkenlijst is dus `text` + `video`, niet de drie types van de vorige spec.
2. **`articles` heeft wél een taxonomie.** `themes` is verplicht, `max_items: 1`,
   `create: false`, met vier vaste termen. De vorige spec ging uit van "geen
   taxonomie, dus `categories` → `422`".
3. **`intro` versus `text`.** De fieldset `page_header` declareert `intro`, maar
   alle zes artikels en `partials/headers/article` gebruiken `text`, net als de
   47 andere contentbestanden en elk ander header-partial.

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
collecties heen, met titel, URL, collectie en type. Alleen artikels zijn
schrijfbaar. Dat dekt Inspace' leesrequirement en geeft Nova de sitestructuur die
het nodig heeft om interne links te leggen naar aanbod- en productpagina's — net
waar de SEO-waarde zit.

**Fase 2 is `ranges` en `products`, en is bewust nog geen ontwerp.** Beide zijn
page-builder-pagina's met twaalf sets (`cta`, `cards`, `image_gallery`,
`technical_details`, `ranges`, `text`, `text_image`, `embed`, `products`,
`articles`, `features`, `grid_cta`). Wat Nova daarop moet kunnen — tekst
herschrijven, secties toevoegen, herordenen, of ook nieuwe pagina's aanmaken — is
een vraag aan Inspace die eerst beantwoord moet worden. Zolang dat antwoord
uitblijft ontwerpen we er niets voor.

Het contract is wel zo gebouwd dat uitbreiden additief is: `collection` staat al
in elk object en `content` is een blokkenlijst die niet artikelspecifiek is. De
dichte doos die video's vandaag krijgen is exact het mechanisme dat de
page-builder-sets straks nodig hebben.

**Service pages zijn daarmee fase 2, en dat is een bewust risico.** Dirk herhaalt
bij alle vier de pagina-endpoints — óók bij aanmaken en aanpassen — dat
subscription 2/3 klanten toegang tot service pages nodig hebben. Letterlijk
gelezen wil Nova die dus schrijven, niet alleen lezen. Fase 1 bedient daarmee
subscription 1. Inspace moet dat weten vóór ze aan hun 16 uur beginnen, anders
bouwen ze tegen een contract dat meteen moet uitbreiden.

**Geen `DELETE`.** Inspace vroeg er niet om. Terugtrekken kan via
`status: draft`.

## Plaats in de codebase

De adapter wordt app-code in **winsol-brebo**, onder `app/Inspace/`, met routes
onder `/api/inspace/v1/`. Die namespace staat bewust naast Statamic's eigen
`/api/`: die laatste is read-only en moet apart aangezet worden, en een eigen
namespace voorkomt collisie en verwarring.

De code blijft sitegeneriek geschreven. Geen enkele verwijzing naar `articles`,
`redactor`, `themes` of veldnamen in de klassen zelf; alles wat sitespecifiek is
staat in `config/inspace.php`. Bij een tweede gekoppelde site is overnemen dan
`app/Inspace/` plus de config, de routes en één regel in de service provider —
een kopieerhandeling, geen herschrijving.

**Extractie naar `statamic-base` gebeurt pas wanneer die tweede site er is.**
Niet vooruitlopend. De prijs die we tot dan accepteren: een bug wordt op één
plek gerepareerd, want er ís maar één plek.

## Endpoints

| Methode | Pad | Doel |
|---|---|---|
| `GET` | `/api/inspace/v1/schema` | schrijfbare collecties, hun velden, verplichtingen en toegestane themawaarden |
| `GET` | `/api/inspace/v1/pages` | entries over alle leesbare collecties, gepagineerd |
| `GET` | `/api/inspace/v1/pages/{id}` | detail van één entry |
| `POST` | `/api/inspace/v1/pages` | nieuw artikel |
| `PATCH` | `/api/inspace/v1/pages/{id}` | artikel bijwerken, partieel |
| `POST` | `/api/inspace/v1/media` | afbeelding uploaden, met `alt` |

`{id}` is de Statamic entry-UUID, niet de slug. Die overleeft een hernoeming.

Elk endpoint accepteert een optionele `site`-parameter. Winsol-brebo heeft geen
`resources/sites.yaml` en draait dus single-site, maar de parameter nu opnemen
kost een default en voorkomt dat we Inspace later een tweede contractversie
moeten sturen zodra er een FR-site komt. Ontbreekt hij, dan geldt de default
site.

### GET /schema

Het endpoint dat de vorige spec schrapte en dat nu terug is. Reden: `themes` is
verplicht met een gesloten vocabularium van vier termen, en zonder dit endpoint
moet elke koppeling die lijst per mail krijgen. Dit is wat Inspace' "±30 minuten
per klant" waarmaakt.

```json
{
  "collections": {
    "articles": {
      "writable": true,
      "route": "/nieuws/{slug}",
      "fields": {
        "title":            { "type": "string", "required": true },
        "intro":            { "type": "string", "required": false },
        "theme":            { "type": "enum", "required": true,
                              "values": ["energie-en-comfort", "ramen-en-deuren",
                                         "terrasoverkapping", "zonwering"] },
        "image":            { "type": "asset", "required": true },
        "content":          { "type": "blocks", "required": true,
                              "writable_types": ["text"],
                              "opaque_types": ["video"],
                              "allowed_html": ["h2","h3","strong","em","ul","ol",
                                               "li","a","table","img"] },
        "slug":             { "type": "string", "required": false },
        "date":             { "type": "date",   "required": false },
        "status":           { "type": "enum",   "required": false,
                              "values": ["draft", "published"] },
        "external_id":      { "type": "string", "required": false },
        "meta_title":       { "type": "string", "required": false, "max": 60 },
        "meta_description": { "type": "string", "required": false, "max": 160 },
        "meta_image":       { "type": "asset",  "required": false },
        "seo_noindex":      { "type": "bool",   "required": false }
      }
    }
  }
}
```

De themawaarden komen live uit de taxonomie, niet uit config. Voegt de klant een
thema toe, dan weet Nova dat zonder dat er iemand tussenkomt.

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
blokkenlijst. Voor niet-schrijfbare entries: `id`, `collection`, `title`, `url`,
`editable: false`, de SEO-velden, en `content: null`. De page builder laat zich
niet betrouwbaar als platte content weergeven en we doen ook niet alsof — tot
fase 2 daar een antwoord op geeft.

### POST /pages

Verplicht: `title`, `theme`, `image`, `content`. Optioneel: `intro`, `slug`,
`date`, `status`, `external_id`, `meta_title`, `meta_description`, `meta_image`,
`seo_noindex`.

Ontbreekt `slug`, dan wordt die van de titel afgeleid. Bestaat die al, dan krijgt
hij een numeriek achtervoegsel — Nova hergebruikt titels bij het herschrijven en
mag daar geen bestaand artikel mee overschrijven. Ontbreekt `date`, dan vandaag.

Antwoordt synchroon met `201` en het volledige object inclusief `id` en `url`.

De kleinst mogelijke oproep:

```json
{
  "title": "Zip-screens kiezen voor een nieuwbouw",
  "theme": "zonwering",
  "image": "3f2a…",
  "content": [{ "type": "text", "html": "<h2>Buiten tegenhouden</h2><p>…</p>" }],
  "status": "draft",
  "external_id": "nova-4711"
}
```

**`external_id`** is Nova's eigen identifier. Wordt die meegestuurd en bestaat er
al een artikel mee, dan antwoordt de adapter met `200` en het bestaande object in
plaats van een duplicaat aan te maken. Zonder dit levert één timeout gevolgd door
een retry twee identieke artikels op. De waarde slaat op als frontmatter-sleutel
met een eigen Stache-index, zodat de lookup betrouwbaar is; of die index werkt
zoals bedoeld is een implementatiecheck, geen aanname.

### PATCH /pages/{id}

Partieel: alleen meegestuurde velden worden aangepast. Op een niet-schrijfbare
entry: `403` met de lijst van wél schrijfbare collecties.

### POST /media

`multipart/form-data`, veld `file`, plus een optionele `alt`. Toegestaan: jpg,
png, webp, gif. Maximum uit config. Landt in container `assets`. Antwoordt met
`id`, `url`, `width`, `height`, `filename`, `alt`.

De upload gaat door Statamic's eigen uploadpad, zodat `AssetUploaded` vuurt en de
bestaande `CompressUploadedAsset`-listener zijn werk doet. Een asset die
rechtstreeks naar disk geschreven wordt slaat die listener over, en dan komen
Nova's beelden ongecomprimeerd binnen — precies het tegendeel van wat de dienst
moet bereiken.

De `id` uit deze response is wat Nova in `image`, `meta_image` of een `<img src>`
binnen een text-blok zet.

## Veldmapping

Config-gedreven, in `config/inspace.php`:

| API-veld | blueprint-handle | Type | Bijzonderheid |
|---|---|---|---|
| `title` | `title` | text | verplicht |
| `intro` | `text` | textarea | naam wijkt af, zie hieronder |
| `content` | `redactor` | bard met `video`-set | blokkenlijst |
| `image` | `image` | assets, max 1 | asset-ID uit `/media`, verplicht bij aanmaken |
| `theme` | `themes` | terms | verplicht, één van vier, `create: false` |
| `slug` | `slug` | slug | afgeleid van titel als hij ontbreekt |
| `date` | `date` | date | vandaag als hij ontbreekt |
| `status` | published-vlag van de entry | bool | `draft` of `published` |
| `meta_title` | `meta_title` | text | |
| `meta_description` | `meta_description` | textarea | |
| `meta_image` | `meta_image` | assets, max 1 | |
| `seo_noindex` | `seo_noindex` | toggle | |

Twee namen wijken bewust af van de handle, en dat is precies waarvoor de
mappinglaag bestaat:

- **`intro` heet in het blueprint `text`.** Een API-veld `text` náást `content`
  is voor Inspace niet te onderscheiden.
- **`theme` is enkelvoud** omdat `max_items: 1` het al tot één term beperkt,
  terwijl de handle `themes` heet.

`preview_title`, `preview_intro` en `preview_image` blijven buiten het contract.
`partials/articleCard.antlers.html` gebruikt ze niet, dus ze zouden nergens
landen.

`status` accepteert `draft` en `published`, zoals WordPress. Nova bepaalt zelf of
iets live gaat; er is geen goedkeuringsstap aan onze kant.

**Onbekende velden geven `422`**, met de geldige veldnamen erbij. Stil slikken
laat Inspace geloven dat het werkt.

**Een onbekend thema geeft `422`** met de geldige waarden in de foutmelding.
`create: false` betekent dat Nova er geen mag bijmaken.

`image` is verplicht bij aanmaken — in het contract, niet in het blueprint.
Ontbreekt hij, dan crasht er niets (`app/Tags/Img.php:41-45` throwt alleen bij
`app.debug` en geeft anders een lege string), maar je krijgt een leeg kader op
het overzicht en een header zonder beeld. Een SEO-artikel zonder afbeelding is
geen artikel dat je wil publiceren, dus de API weigert het.

## Contentvorm

`content` is een lijst van getypeerde blokken:

```json
[
  {"type": "text", "html": "<h2>Kop</h2><p>Tekst met <strong>nadruk</strong> en <img src=\"…\"></p><ul><li>…</li></ul>"},
  {"type": "video", "id": "video01", "opaque": true}
]
```

Voor een nieuw artikel is dat één element.

**De lijst zit op set-niveau, niet op mark-niveau.** Alle opmaak — koppen, vet,
cursief, lijsten, links, tabellen en inline afbeeldingen — is gewone HTML binnen
`html`. Statamic doet die opsplitsing zelf al: de Augmentor rendert de hele
ProseMirror-doc naar HTML en knipt hem alleen op waar een set staat. Je ziet het
terug in `articles/show.antlers.html`, waar elke iteratie van `{{ redactor }}`
óf een video is, óf een blok met `{{ text }}` dat volledige HTML draagt.

Voor Inspace betekent dat: dezelfde HTML die ze naar WordPress sturen, met één
laagje eromheen. Conceptueel staat dit dichter bij Gutenberg — dat ook blokken
gebruikt, met `<!-- wp:paragraph -->` als scheiding — dan bij klassiek WordPress.

**Waarom geen platte HTML-string.** Statamic serialiseert een Bard-set in HTML
als `<set>index-0</set>`: een positienummer zonder data. De veldwaarden houdt de
Augmentor apart in geheugen (`Augmentor.php:97`) en splitst de HTML daar achteraf
op (`convertToSets`, regel 137). `renderHtmlToProsemirror()` leest zo'n marker
terug als een lege set, waarmee de video verdwijnt. Statamic's eigen conversie is
dus alleen symmetrisch voor een Bard zónder sets.

Overwogen en verworpen: één HTML-string waarbij de adapter de video's eruit knipt
en achteraf terugplakt (geen stabiel ankerpunt zodra Nova de tekst herschrijft),
en een HTML-string met een comment-marker per video (valt of staat met wat hun
sanitizer met comments doet, en faalt stil).

**Video's zijn een dichte doos.** Nova hoeft ze niet te kunnen schrijven, maar ze
mogen ook niet sneuvelen. Ze komen terug als `{"type": "video", "id": "…",
"opaque": true}` en dragen verder niets: de URL, de row-ID en de `enabled`-vlag
uit `attrs` blijven aan onze kant. Bij een `PATCH` zoekt de adapter de originele
set terug op `id` en zet die ongewijzigd op de plaats waar Nova de doos liet
staan.

Nova mag een doos dus herordenen of weglaten — weglaten betekent verwijderen —
maar niet wijzigen. Een doos met een onbekende `id` geeft `422`; zo kan Nova geen
set verzinnen die niet bestond. Ditzelfde mechanisme dekt straks de
page-builder-sets van fase 2.

### Alleen gewijzigde blokken worden herschreven

Geverifieerd op een echt artikel: de round-trip ProseMirror → HTML →
ProseMirror is **structureel verliesvrij maar normaliseert**. 5 nodes in, 5
nodes uit, tekst en opmaak identiek — maar hij zet `textAlign: "left"` op elke
paragraaf en kop. Dat attribuut staat vandaag in geen van de zes artikels.

De adapter houdt daarvoor geen state bij. Bij een `PATCH` rendert hij de
opgeslagen ProseMirror opnieuw naar HTML — exact wat een `GET` op dat moment zou
teruggeven — en vergelijkt dat per blok met de binnenkomende `html`. Is die
byte-identiek, dan blijft de opgeslagen ProseMirror onaangeroerd. Een `PATCH` die
alleen `meta_title` wijzigt laat de body dus letterlijk met rust, en alleen echt
herschreven blokken normaliseren.

Zonder deze regel is "`GET` → ongewijzigde `PATCH` levert identieke opslag" niet
haalbaar, want de normalisatie is inherent aan Statamic's conversie.

### Afbeeldingen en alt-teksten

`Bard\ImageNode::addAttributes()` declareert alleen `src`. Een `<img alt="…">`
die Nova stuurt wordt weggegooid — geverifieerd. Statamic haalt de alt niet uit
de node maar **uit het asset zelf** (`ImageNode.php:70`,
`Asset::find($id)->data()->get('alt')`), en alleen wanneer `src` de vorm
`asset::<uuid>` heeft.

Daarom:

- **alt hoort bij het asset.** `POST /media` accepteert een `alt` naast het
  bestand en schrijft die op het asset. Het veld staat al in
  `resources/blueprints/assets/assets.yaml`, dus dit vraagt geen uitbreiding.
- **elke `<img src>` moet naar een bekend asset wijzen.** De adapter herschrijft
  hem naar `asset::<uuid>`, want alleen in die vorm resolvet de alt mee. Een
  externe URL geeft `422` met de verwijzing naar `/media`. Geen hotlinks.
- **Nova's eigen `alt`-attribuut wordt genegeerd**, niet stil maar met een
  vermelding in `warnings`. Eén afbeelding heeft daarmee overal dezelfde alt.

Overwogen en verworpen: Bard uitbreiden met een eigen image-node die `alt` wel
als attribuut bewaart, zodat dezelfde afbeelding per plaatsing een andere alt kan
hebben. Correcter SEO-model, maar het overschrijft een Statamic-core-node en kost
onderhoud bij elke upgrade. Herzien als blijkt dat Nova hier tegenaan loopt.

### Interne links

Uitgaand worden `statamic://entry::uuid`-links naar echte URL's gerenderd, anders
krijgt Nova onbruikbare hrefs terug. Binnenkomend gaat de omgekeerde weg: een URL
die naar een bestaande entry wijst wordt weer `statamic://entry::uuid`, zodat de
link een slug-wijziging overleeft. Dit is een expliciete testcase, geen aanname.

### Sanitizing

De `html` binnen een text-blok gaat door een sanitize-stap met een whitelist
afgeleid uit de Bard-buttonconfig van het veld, niet uit een hardgecodeerde
lijst. `article_redactor` staat vandaag h2, h3, bold, italic, lijsten, anchor,
table en image toe.

Wat gestript wordt komt terug in een `warnings`-array op de response. Nova stuurt
gegarandeerd ooit `<h1>`, `<blockquote>` of inline styles, en dan moet zichtbaar
zijn dat die hier niet bestaan.

## Voorwerk

Eén hernoeming, en verder niets.

**`resources/fieldsets/page_header.yaml`: handle `intro` → `text`.** Vandaag
schrijft dat CP-veld naar een sleutel die niets rendert, terwijl alle zes
artikels en `partials/headers/article.antlers.html` `text` gebruiken — net als de
47 andere contentbestanden en elk ander header-partial. De fieldset wordt alleen
geïmporteerd door `legal` (dat geen van beide velden gebruikt) en
`page_header_image` → `article`, dus de hernoeming raakt verder niets.
`content/collections/pages/nl/cases.md` blijft ongemoeid: `pages/page.yaml`
importeert `page_header` niet.

De templatebug uit de vorige spec bestaat hier niet meer, en `alt` staat al in
het assets-blueprint. Er is dus geen ander voorwerk.

## Schrijfacties serialiseren

Statamic is flat-file met een Stache-index. Nova die parallel tientallen artikels
post kan die index in de knoop leggen. Schrijfacties lopen daarom door een lock,
zodat er nooit twee tegelijk zijn.

De adapter controleert bij het opstarten of revisions aan staan op een
schrijfbare collectie en weigert dan te starten. `articles.yaml` heeft
`revisions: false`, maar met revisions aan maakt `save()` een working copy in
plaats van te publiceren, waardoor Nova denkt dat het publiceerde terwijl er
niets live staat. Stil falen is hier erger dan niet starten.

## Authenticatie

`Authorization: Bearer <token>`. Tokens gehasht in config, gevoed uit `.env`,
vergeleken met `hash_equals`. Middleware op de hele routegroep, met rate limit.

Geen Sanctum: dat geeft per-token scopes en intrekking via de database, maar
vraagt migrations en een DB-afhankelijkheid. Voor één koppelende partij per site
is een geroteerde env-variabele genoeg. Als er ooit meerdere partijen tegelijk
koppelen is dit het punt om te herzien.

Elke schrijfactie wordt gelogd: token-label, IP, methode, entry-ID.

## Foutafhandeling

JSON, consistent van vorm:

- `401` ontbrekend of ongeldig token
- `403` schrijven op een niet-schrijfbare entry, met de wél schrijfbare
  collecties erbij
- `404` onbekende entry
- `422` validatiefout, onbekend veld, onbekend thema, externe afbeeldings-URL of
  een opaque blok met een onbekende `id`, met `errors` per veld
- `429` rate limit

Naast `errors` kan elke succesvolle schrijfrespons een `warnings`-array dragen
voor niet-blokkerende zaken: gestripte HTML-nodes en genegeerde `alt`-attributen.

## Configuratie

`config/inspace.php`:

- `readable`: welke collecties `GET` teruggeeft, standaard alle
- `writable`: per collectie het blueprint en de veldmapping
- `assets`: container (`assets`), map en maximale bestandsgrootte
- `tokens`: gehashte tokens met een label per partij
- `rate_limit`

## Testen

Via `vendor/bin/phpunit` met 1G geheugen, nooit `php artisan test`.

- `GET` → ongewijzigde `PATCH` laat de opgeslagen ProseMirror byte-identiek;
  geen `textAlign` op onaangeroerde blokken
- een herschreven blok normaliseert wél, en alleen dat blok
- de `video`-set overleeft een update, inclusief row-ID en `enabled: false`
- een weggelaten opaque blok verdwijnt, een herordend blok verhuist mee, en een
  onbekende `id` geeft `422`
- `theme`: onbekende waarde geeft `422` mét de geldige lijst; ontbrekend geeft
  `422`
- afbeeldingen: bekende asset-URL wordt `asset::<uuid>`, externe URL geeft `422`
- de `alt` uit `/media` landt op het asset en komt terug in de gerenderde `<img>`
- interne links: uitgaand echte URL, binnenkomend weer `statamic://entry::uuid`
- `external_id`: tweede `POST` met dezelfde waarde geeft `200`, geen duplicaat
- slug-collisie krijgt een achtervoegsel en overschrijft niets
- auth: ontbrekend, fout en geldig token
- `403` bij schrijven op een niet-schrijfbare collectie
- `422` bij een onbekend veld
- media-upload, inclusief afgewezen bestandstypes en compressie die effectief
  loopt
- paginering op `GET /pages`
- `/schema` geeft de vier thema's live uit de taxonomie, niet hardgecodeerd

## Aanlevering aan Inspace

**De staging van winsol-brebo is de referentieomgeving**, niet die van
`statamic-base`. Dat beantwoordt Dirk's vraag om een gesplitste productie- en
testomgeving: staging is de test, de live site is de productie met hetzelfde
contract.

Twee voorwaarden aan die omgeving. Met een schrijftoken kan Inspace er echte
artikels aanmaken, dus ze moet als wegwerpbaar behandeld worden. En ze mag niet
indexeerbaar zijn: anders staat er AI-gegenereerde SEO-content op een klantsite,
als duplicaat van wat later live komt.

Zes dingen gaan de deur uit, en **parallel met de bouw, niet erna**. Inspace deed
drie maanden over één antwoord; wachten tot het af is stapelt hun reactietijd op
je eigen doorlooptijd.

1. **Een OpenAPI 3.1-spec.** Machineleesbaar, dus ze genereren er hun client uit.
   Dit is het eigenlijke antwoord op "stuur ons jullie API-documentatie".
2. **Een korte begeleidende markdown** met wat de spec niet kan zeggen: fase 1 is
   blog, `articles` is het enige schrijfbare, aanbod- en productpagina's zijn wél
   leesbaar maar nog niet schrijfbaar, en dat is een bewuste fasering.
3. **De staging-URL plus een bearer token.** Een endpoint dat ze kunnen aanroepen
   krijgt sneller antwoord dan een document.
4. **CMS-toegang op die staging**, zodat ze zien waar hun content landt.
5. **Het `/schema`-endpoint zelf**, live.
6. **Twee uitgewerkte voorbeelden**: een volledige `GET` en de kleinst mogelijke
   `POST`.

**Stel één gesloten vraag.** Niet "is dit zo goed?", want dat nodigt uit tot
stilte of tot scope die erbij komt. Wel: *kunnen jullie hiermee bouwen?*

## Openstaande punten

Naar Inspace, in volgorde van belang:

1. **Kan Nova `content` aan als lijst van blokken, of gaat hun integratie uit van
   één veld met HTML?** Bepaalt of het contract blijft zoals het hier ligt. Een
   adapter die blokken spreekt kan later een HTML-view erbij krijgen; andersom
   niet.
2. **Bewerkt Nova bestaande aanbod- en productpagina's, of maakt het ze ook aan?**
   Aanmaken is het dure stuk: een nieuwe pagina bestaat uit blokken die Nova niet
   kent.
3. **Wat moet Nova op zo'n pagina kunnen** — tekst herschrijven, of ook secties
   toevoegen, herordenen en verwijderen?
4. **Op welke subscription zit de eerste white-label klant?** Bepaalt of service
   pages fase 1 of fase 2 zijn.

Intern:

- **WordPress-datamodel.** Nooit ontvangen. Het risico dat we accepteren: hun
  veldnamen wijken af van de onze, wat aan hún kant een mappinglaag vraagt. Zij
  bouwen die koppeling toch al, dus dat is een aanvaardbare kost — maar het is de
  reden om de OpenAPI-spec vroeg te sturen in plaats van na oplevering.
- **Statische caching** staat op `null` in `.env.example` en is dus vandaag uit.
  Zet iemand het aan, dan moet invalidatie na een API-schrijfactie gecontroleerd
  worden. `ClearSitemapCache` hangt aan `EntrySaved` en vuurt wél bij een
  programmatische save.
- **Extractie naar `statamic-base`** komt op tafel zodra een tweede site
  koppelt, niet eerder.
