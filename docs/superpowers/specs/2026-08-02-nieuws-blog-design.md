# Nieuws: van realisaties naar blogartikels

**Datum:** 2026-08-02
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

De realisaties omzetten naar een nieuwssectie. `/realisaties` wordt `/nieuws`,
met dezelfde opmaak: dezelfde header, dezelfde filterkolom links, dezelfde
kaartgrid rechts. Het filter draait op een nieuwe taxonomie `themes` in plaats
van op de ranges, en de detailpagina ruilt de page builder in voor een
redactor-veld met beeld en video, gecentreerd in prose-opmaak, met twee chips
(thema en datum) boven de titel.

Referentie voor de redactorverwerking: het luloop-project,
`resources/views/articles/show.antlers.html` en
`resources/views/partials/headers/article.antlers.html`.

## Scope

In scope: de collectie `articles` (bestaand, opgeschoond), een nieuwe taxonomie
`themes`, de pagina `/nieuws`, twee templates, drie partials, drie
CSS-bestanden, de page-builder-set, de navigatie, verse voorbeeldcontent, en de
tests.

Uit scope en verwijderd: de collectie `projects` met haar zes entries, de
pagina `realisaties.md`, `projects_overview.yaml`, de projects-views en -CSS,
`App\Tags\ProjectRanges`, en de drie lorem-artikels uit de statamic-base-
boilerplate.

Buiten scope, ongemoeid gelaten: de `cases`-boilerplate (`resources/views/cases`
en `cases.md`), die los staat van deze wijziging.

## Uitgangspunten

Vier dingen die het vertrekpunt vormen en die bij het narekenen makkelijk
verwarren:

1. **De collectie `articles` bestaat al**, als ongebruikte boilerplate uit
   statamic-base. Ze is gemount op `8cf703da-5dde-4543-89aa-8f2d5c3011d9`, een
   entry die niet meer bestaat maar wel nog in `content/trees/collections/nl/pages.yaml`
   staat. Haar blueprint is bruikbaar als vertrekpunt; haar route, mount en
   views niet.

2. **`articles/show.antlers.html` in de boilerplate heeft nooit gewerkt.** Het
   template loopt over `type == "text" | "image" | "video"`, terwijl de
   `redactor`-fieldset geen enkele set definieert. Zonder sets augmenteert Bard
   naar één HTML-string en levert de lus niets op. Dit ontwerp repareert dat
   bewust, met een test die het vastlegt.

3. **Er is géén projects-slider op `/home`.** `home.md` bevat wel een sleutel
   `home_projects`, maar de home-blueprint kent dat veld niet en
   `home.antlers.html` rendert het nergens. Dode data; gaat weg. De enige
   levende gebruiker van `sections/projects` is de page-builder-set, ingezet op
   de demopagina `page-builder.md`.

4. **`App\Tags\ProjectRanges` bestaat alleen omdat `range` een entries-relatie
   is.** De tag ontdubbelt de ranges van gepubliceerde projecten zodat een klik
   nooit een lege grid oplevert. Met een taxonomie doet Statamic dat native:
   `{{ taxonomy:themes collection="articles" min_count="1" }}`. De tag verdwijnt
   zonder vervanging.

## Bestanden

```
content/taxonomies/themes.yaml                              nieuw
content/taxonomies/themes/*.yaml                            nieuw, vier termen
resources/blueprints/taxonomies/themes/themes.yaml          nieuw
content/collections/articles.yaml                           route, mount, sort_dir, taxonomies
resources/blueprints/collections/articles/article.yaml      veld `theme` erbij
resources/fieldsets/redactor.yaml                           image-knop + video-set
content/collections/articles/nl/*.md                        verse voorbeeldartikels
content/collections/pages/nl/nieuws.md                      vervangt realisaties.md
resources/blueprints/collections/pages/articles_overview.yaml  vervangt projects_overview.yaml
resources/views/articles/index.antlers.html                 herschreven
resources/views/articles/show.antlers.html                  herschreven
resources/views/partials/articleCard.antlers.html           nieuw, uit projectCard
resources/views/partials/themeFilter.antlers.html           nieuw, uit rangeFilter
resources/views/partials/headers/article.antlers.html       nieuw, uit headers/project
resources/views/partials/sections/articles.antlers.html     vervangt sections/projects
resources/js/components/article-filter.js                   vervangt project-filter.js
resources/js/site.js                                        import + Alpine.data
resources/css/components/article-card.css                   vervangt project-card.css
resources/css/components/chip.css                           vervangt het lege badge.css
resources/css/base/rich-text.css                            `.article-body` erbij
resources/css/site.css                                      imports bijgewerkt
resources/fieldsets/page_builder.yaml                        set `projects` → `articles`
content/collections/pages/nl/page-builder.md                settype en verwijzingen
content/collections/pages/nl/home.md                        dode `home_projects` eruit
content/trees/collections/nl/pages.yaml                     wees-entry 8cf703da eruit
content/trees/navigation/nl/main.yaml                       titel Realisaties → Nieuws

verwijderd:
content/collections/projects.yaml
content/collections/projects/nl/*.md
content/collections/pages/nl/realisaties.md
resources/blueprints/collections/pages/projects_overview.yaml
resources/blueprints/collections/projects/projects.yaml
resources/views/projects/                                    beide templates
resources/views/partials/projectCard.antlers.html
resources/views/partials/rangeFilter.antlers.html
resources/views/partials/headers/project.antlers.html
resources/views/partials/sections/projects.antlers.html
resources/js/components/project-filter.js
resources/css/components/project-card.css
app/Tags/ProjectRanges.php
content/collections/articles/nl/2025-05-14.*.md              de drie lorem-artikels
```

## 1. Contentmodel

### Taxonomie `themes`

`content/taxonomies/themes.yaml` met alleen een titel. De blueprint
`resources/blueprints/taxonomies/themes/themes.yaml` krijgt één veld, `title`,
verplicht.

Bewust géén `order`-veld, in tegenstelling tot `range_categories`. Dat veld
bestaat daar omdat de aanbodpagina een vaste categorievolgorde uit Figma volgt.
Het themafilter heeft geen ontworpen volgorde en sorteert alfabetisch, precies
zoals `ProjectRanges` dat deed.

### Collectie `articles`

`content/collections/articles.yaml`:

```yaml
title: Nieuws
template: articles/show
layout: layout
route: '/nieuws/{slug}'
mount: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
date: true
sort_dir: desc
taxonomies:
  - themes
revisions: false
```

Twee wijzigingen ten opzichte van de boilerplate die het narekenen waard zijn:

- `sort_dir` gaat van `asc` naar `desc`. Een nieuwsoverzicht toont het nieuwste
  bovenaan; `asc` was een boilerplate-standaard.
- `mount` wijst naar de bestaande entry-id van `realisaties.md`. Die id wordt
  door `nieuws.md` overgenomen (zie hieronder), waardoor de mount klopt zonder
  dat er ergens een nieuwe id ingevoerd moet worden.

`date: true` betekent dat de entry-bestandsnamen een datumprefix dragen:
`content/collections/articles/nl/2026-07-15.zip-screens-op-nieuwbouw.md`. De
projects-collectie had dat niet, dus de bestaande bestandsnamen zijn geen
voorbeeld.

### Blueprint `articles/article.yaml`

Blijft zoals hij is — `page_header_image` (die `title` verplicht maakt en
`text` en `image` levert), `redactor`, en in de sidebar `slug` en `date` — met
één veld erbij in de sidebar, naast `date`:

```yaml
handle: theme
field:
  type: terms
  taxonomies:
    - themes
  max_items: 1
  create: false
  display: Thema
  required: true
  validate:
    - required
```

`create: false` houdt het aanmaken van thema's in de taxonomie zelf, zodat het
filter niet vervuild raakt met eenmalige termen.

### Pagina `/nieuws`

`content/collections/pages/nl/nieuws.md` vervangt `realisaties.md` en **behoudt
diens id** `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03`. Die id staat in
`content/trees/navigation/nl/main.yaml` en in `content/trees/collections/nl/pages.yaml`;
door hem te behouden hoeft in beide bomen alleen de zichtbare titel te
veranderen, en blijft de mount van de collectie kloppen.

Blueprint `articles_overview.yaml`, een kopie van `projects_overview.yaml`:
`page_intro` plus `page_builder`, met `slug` en `template` in de sidebar.
`template: articles/index` in de entry zelf, zoals `realisaties.md`
`projects/index` aanwees. De bestaande cta-sectie uit `realisaties.md` mag mee,
met een tekst die bij nieuws past.

In `content/trees/collections/nl/pages.yaml` gaat de rij met
`8cf703da-5dde-4543-89aa-8f2d5c3011d9` eruit: dat is de wees-mount van de
boilerplate.

## 2. Overzichtspagina `/nieuws`

`resources/views/articles/index.antlers.html`, structureel identiek aan het
huidige `projects/index`:

```antlers
{{ partial:headers/default divider="true" }}
<section class="section section--default" data-section="articles-overview" x-data="articleFilter()">
    <div class="container">
        {{# De filterkolom is zo breed als zijn breedste pil; de grid krijgt de rest. #}}
        <div class="grid grid-gutter gap-8 lg:grid-cols-[max-content_1fr] lg:gap-x-16 2xl:gap-x-20">
            {{ partial:themeFilter }}
            <ul class="grid grid-gutter md:grid-cols-2">
                {{ collection:articles }}
                    {{ theme_slug = '' }}
                    {{ if theme }}{{ theme_slug = theme:slug }}{{ /if }}
                    <li
                        {{ if get:theme && get:theme != theme_slug }}hidden{{ /if }}
                        :hidden="!matches('{{ theme_slug }}')">
                        {{ partial:articleCard }}
                    </li>
                {{ /collection:articles }}
            </ul>
        </div>
    </div>
</section>

{{ partial:pageBuilder }}
```

### Filter

`resources/views/partials/themeFilter.antlers.html` is `rangeFilter` met twee
wijzigingen: `?range=` wordt `?theme=`, en de `{{ project_ranges }}`-lus wordt

```antlers
{{ taxonomy:themes collection="articles" min_count="1" sort="title" }}
```

`min_count="1"` doet het werk waarvoor `ProjectRanges` bestond: alleen thema's
tonen waar minstens één artikel aan hangt. Pillen blijven `btn btn--secondary`
(actief) en `btn btn--tertiary` (inactief), het sticky-gedrag onder `lg:sticky
lg:top-10` blijft, en de horizontale scrollrij met `-mx-4 px-4` die onder `lg`
tot de schermrand loopt blijft inclusief haar commentaar.

De `aria-current`- en `aria-label`-opzet blijft ongewijzigd; het label komt uit
`trans:site.filter_label` en de eerste pil uit `trans:site.filter_all`.

### Alpine

`resources/js/components/project-filter.js` wordt
`resources/js/components/article-filter.js`, met de functie `articleFilter`.
`resources/js/site.js` past import en `Alpine.data` aan.

De mechaniek verandert niet: de server rendert álle artikels en zet `hidden` op
wat bij de eerste paint niet matcht; Alpine neemt datzelfde attribuut over via
`:hidden`. Geen flits bij het booten, geen animatie, en "Toon alles" werkt
zonder request.

Het commentaarblok blijft integraal staan, met `theme` in plaats van `range`.
De reden om `?theme=` zelf uit `window.location.search` te lezen in plaats van
als server-geïnterpoleerd argument aan te nemen, geldt onverkort: een ruwe
`{{ get:theme }}` in `x-data="articleFilter('...')"` zou de queryparameter
ongefilterd in een Alpine-expressie plaatsen, wat een reflected-XSS-gat opent —
de browser decodeert HTML-entities vóórdat Alpine de attribuutwaarde
evalueert, dus escapen aan de serverkant is daar geen verdediging.

### Kaart

`resources/views/partials/articleCard.antlers.html`, gekopieerd van
`projectCard`. Beeld op ratio 1/1 met `rounded-md max-h-100`, thema als
overline erboven, titel eronder met de `-rotate-45`-pijl die op hover een
kwartje opschuift, en het streepje `mt-auto h-px bg-black/10` onderaan dat de
kaarten op één lijn afsluit. Enige inhoudelijke wijziging: `range.title` wordt
`theme.title`, en de klasse `project-card` wordt `article-card`.

`resources/css/components/project-card.css` wordt
`resources/css/components/article-card.css` met `.article-card__category`. De
fluid font-size `clamp(0.75rem, 0.571rem + 0.446vw, 1rem)` en de
`letter-spacing: 0.02em` uit Figma blijven ongewijzigd. Import in `site.css`
bijwerken.

## 3. Detailpagina `/nieuws/{slug}`

### Header

`resources/views/partials/headers/article.antlers.html`, de `headers/project`
waarin de eyebrow plaatsmaakt voor een chiprij:

```antlers
<section class="bg-white" data-header="article">
    <div class="container flex flex-col items-center gap-6 pt-10 text-center lg:pt-16">
        <div class="inline-flex items-center gap-2">
            {{ if theme }}<span class="chip chip--dark">{{ theme.title }}</span>{{ /if }}
            <span class="chip chip--light">{{ date | isoFormat('D MMMM YYYY') }}</span>
        </div>

        <h1 class="header-title max-w-[866px]">{{ title }}</h1>

        {{ if text }}<p class="header-intro max-w-[866px]">{{ text }}</p>{{ /if }}
    </div>

    {{ if image }}
        <div data-header-media class="container mt-10 lg:mt-16">
            {{ img :src="image" ratio="4/3" lg:ratio="2/1" max_width="2560" sizes="100vw" priority="true" class="w-full rounded-md" }}
        </div>
    {{ /if }}
</section>
```

Uit het commentaarblok van `headers/project` gaat één stuk weg en blijft één
stuk staan. Weg: de uitleg waarom `{{ if range }}` nodig was — die guard hield
de variabele weg van Antlers' ingebouwde `range`/`loop`-tag
(`Statamic\Tags\Range`). `theme` botst met niets, dus de `{{ if theme }}` staat
er puur om een lege chip te vermijden. Blijft staan: dat het tekstblok
`container` volgt in plaats van 866px vast, en dat het beeld een ratio per
breakpoint aanhoudt — beide afgeleid, want er is geen mobiel Figma-frame.

`theme` is net als `range` een veld met `max_items: 1` en augmenteert dus naar
één term, niet naar een collectie. Een pair (`{{ theme }}…{{ /theme }}`) scoopt
daar niet in; `{{ theme.title }}` doet wél een echte lookup. Dezelfde valkuil,
dezelfde oplossing.

**Datum.** `isoFormat` en niet `format`: `format` geeft rauwe PHP-datumopmaak
met Engelse maandnamen. `isoFormat` gaat via Carbon, dat zijn locale krijgt uit
`app()->setLocale($site->lang())` in Statamics `Localize`-middleware. De
`ArticleHeaderTest` legt een Nederlandse maandnaam vast, zodat dit geverifieerd
is en niet aangenomen.

### Chips

Het lege `resources/css/components/badge.css` wordt
`resources/css/components/chip.css`; de import in `site.css` volgt.

```css
@utility chip {
    @apply inline-flex h-fit w-fit items-center rounded-full px-3.5 py-1.5 text-sm font-semibold lg:px-4;
}

@utility chip--dark {
    @apply bg-black text-white;
}

@utility chip--light {
    @apply bg-light text-black;
}
```

Dezelfde pilvorm als `btn`, maar kleiner: `text-sm` (13 → 16px) tegenover
`text-base` (16 → 20px), en krappere padding. De kleuren spiegelen
`btn--secondary` en `btn--tertiary`, zodat de chip en de filterpil herkenbaar
uit dezelfde familie komen. Accentgeel blijft gereserveerd voor knoppen.

### Body

`resources/views/articles/show.antlers.html`:

```antlers
{{ partial:headers/article }}
<section class="section section--default">
    <div class="container-md">
        {{ redactor }}
            {{ if type == 'video' }}
                <div class="my-8 overflow-hidden rounded-md lg:my-12">{{ partial:video }}</div>
            {{ else }}
                <div class="article-body">{{ text }}</div>
            {{ /if }}
        {{ /redactor }}
    </div>
</section>
```

`container-md` (`lg:w-4/5 2xl:w-full`, max `--breakpoint-xl`) bestaat al en is
winsols equivalent van luloops `container-xs`. Het `container-sm` uit de
boilerplate bestaat in dit project niet.

De `{{ else }}` en niet een tweede `{{ if type == 'text' }}`: er zijn maar twee
knooptypes, en een `else` maakt zichtbaar dat er geen derde geval stilzwijgend
wegvalt — precies de fout die de boilerplate maakte.

### Redactor-fieldset

`resources/fieldsets/redactor.yaml` krijgt twee toevoegingen: `image` bij de
buttons, en één set.

```yaml
title: Redactor
fields:
  -
    handle: redactor
    field:
      type: bard
      display: Redactor
      container: assets
      remove_empty_nodes: false
      buttons: [h2, h3, bold, italic, unorderedlist, orderedlist, anchor, table, image]
      sets:
        content:
          sets:
            video:
              display: Video
              fields:
                -
                  handle: video
                  field:
                    type: video
                    display: Video
```

Beeld dat de redacteur via de knop invoegt, komt in de HTML van de tekstknoop
terecht en krijgt zijn opmaak dus uit `.article-body`. Video is een blok tússen
de tekst en wordt gerenderd met de bestaande `partials/video`, die YouTube en
Vimeo via `is_embeddable` / `embed_url` afhandelt en anders op een HTML5
`<video>` terugvalt.

Deze fieldset wordt alleen door de artikel-blueprint geïmporteerd, dus de
wijziging raakt verder niets.

### `.article-body`

Nieuwe utility in `resources/css/base/rich-text.css`, naast het bestaande
`.rich-text` dat ongemoeid blijft. `.rich-text` is bewust minimaal
(`text-base`, `p + p { mt-4 }`) en wordt gebruikt in `card`, `sectionHeader` en
`sections/text` — plekken waar prose-marges en onderstreepte links verkeerd
zouden vallen. De artikeltekst krijgt daarom een eigen utility in plaats van
een uitgebreide `.rich-text`.

```css
@utility article-body {
    @apply prose max-w-none;
    /* koppen, lijsten, links, tabel en img gemapt op de tokens van winsol */
}
```

Mapping:

- `h2` op `--text-3xl` (31 → 49px), `h3` op `--text-xl` (20 → 31px), beide
  `first-of-type:mt-0` zodat de eerste kop niet tegen de header aan botst
- `p`, `li` op `--text-base`
- `a` in de stijl van `link.css`: onderstreept met offset, geen onderstreping
  op hover
- `img` als `block w-full rounded-md`, dezelfde radius als het headerbeeld
- `table` met een `overflow-x-auto`-wrapper, zodat een brede tabel niet de
  pagina meesleept

Typografie hoort volgens CLAUDE.md in `resources/css/base/`; `rich-text.css` is
daar al de plek voor rijke tekst. `@tailwindcss/typography` staat al als plugin
in `site.css`.

## 4. Page builder, navigatie en content

### Page-builder-set

In `resources/fieldsets/page_builder.yaml` wordt de set `projects` de set
`articles`: het entries-veld wijst naar de collectie `articles`, de handles
`overline`, `title` en `link` blijven.

`resources/views/partials/sections/projects.antlers.html` wordt
`sections/articles.antlers.html` met `articleCard` in de slider. Het
slidergedrag verandert niet:

```antlers
{{ partial:slider per_view="1.15,md:2.15" space="24,md:32,lg:40" from="xl" bleed="true" }}
```

Geen eigen breakpoints-object; `buildResponsive()` in
`resources/js/components/sliders.js` stapelt beide assen. Het commentaar over
`{{ partial:sectionHeader link_col="true" text="" }}` blijft staan: `text` zit
niet in deze set, en zonder expliciete lege waarde valt `sectionHeader` terug
op de velden van de pagina zelf — dat zette op `/home` ooit de hero-intro boven
de slider.

In `content/collections/pages/nl/page-builder.md` worden het settype en de
entry-verwijzingen bijgewerkt naar artikels. Uit `content/collections/pages/nl/home.md`
gaat de dode sleutel `home_projects`.

### Navigatie

In `content/trees/navigation/nl/main.yaml` wordt de titel van het item
`3b6e9620-9efd-4402-8b93-4a4d259d909d` "Nieuws". De `entry`-verwijzing blijft
staan, want `nieuws.md` erft de id van `realisaties.md`. De footer-nav loopt
over de pages-collectie en volgt vanzelf.

### Content

Vier themes als termbestanden, gekozen op wat Winsol Brebo daadwerkelijk doet
en breed genoeg om artikels onder te hangen: **Ramen en deuren**, **Zonwering**,
**Terrasoverkapping** en **Energie en comfort**. Alfabetisch in het filter komt
dat neer op Energie en comfort, Ramen en deuren, Terrasoverkapping, Zonwering.

Daaronder zes nl-artikels, verdeeld over minstens drie thema's zodat het filter
zichtbaar iets doet, elk met kop, thema, datum, beeld en gevulde redactor.
Minstens één artikel met een video-blok en minstens één met een inline beeld in
de tekst, zodat elke rendertak dekking krijgt in de tests. Beelden komen uit de
bestaande assets-container. Geen gedachtestreepjes in de teksten; splits de zin
of gebruik een komma.

## 5. Tests

Draaien met `phpunit` op 1G geheugen, niet met `php artisan test`.

Hernoemd en omgeschreven:

| nu | wordt | dekt |
|---|---|---|
| `Sections/ProjectCardTest` | `Sections/ArticleCardTest` | thema als overline, titel, url, beeld |
| `Sections/ProjectHeaderTest` | `Sections/ArticleHeaderTest` | twee chips incl. Nederlandse maandnaam, h1, intro, beeld |
| `Sections/ProjectsSectionTest` | `Sections/ArticlesSectionTest` | slider-set in de page builder |
| `Content/ProjectsOverviewPageTest` | `Content/ArticlesOverviewPageTest` | `/nieuws`, filterpillen, `hidden` bij `?theme=` |
| `Sections/ProjectRangesTagTest` | vervalt | — |

Nieuw: `Content/ArticleShowPageTest`, die vastlegt dat de redactor loopt — een
tekstknoop landt in `.article-body`, een videoknoop rendert via `partial:video`
— en dat een inline beeld in de tekstknoop meekomt. Dat is precies wat in de
boilerplate stilzwijgend kapot was.

Bijwerken:

- `Content/CatalogContentTest`: `test_six_projects_exist_and_reference_a_range`
  wordt een test dat elk artikel een thema heeft
- `Sections/FooterTest`: `href="/realisaties"` wordt `href="/nieuws"`
- `Content/OffertePageTest`: zoekt op de pagina met slug `realisaties`
- `Content/PageBuilderPageTest`: de set `projects` wordt `articles`
- `Content/ContactPageTest`: alleen een commentaarregel die naar realisaties
  verwijst

`Sections/ProjectHeaderTest` en `Content/ProjectsOverviewPageTest` verdwijnen
niet, ze worden hernoemd: hun assertions blijven inhoudelijk overeind, want de
opmaak verandert niet.

Let op bij het schrijven van sectietests: `SectionTestCase` heeft geen cascade,
dus `{{ globals:… }}` is daar altijd leeg.

## Openstaande punten

- **Redirect van `/realisaties`.** Er komt geen redirect. De pagina staat op
  `seo_noindex: false` maar de site is nog niet live, dus er is niets
  geïndexeerd om te bewaren. Zodra de site live gaat en de URL wél bekend is,
  is dit een aparte beslissing.
- **Volgorde van de thema's.** Alfabetisch, zoals bij de ranges. Als het
  ontwerp later een vaste volgorde vraagt, is dat een `order`-veld op de
  taxonomie plus een `sort`-parameter, net als bij `range_categories`.
- **Paginering.** Het overzicht rendert alle artikels in één keer, omdat het
  client-side filter alle kaarten in de DOM nodig heeft. Bij enkele tientallen
  artikels is dat prima; daarboven vraagt het een herziening van het
  filtermechanisme.
