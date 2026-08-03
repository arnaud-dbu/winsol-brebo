# Nieuws: van realisaties naar blogartikels — implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/realisaties` vervangen door `/nieuws`, een blogoverzicht met dezelfde opmaak, gefilterd op een nieuwe taxonomie `themes`, met detailpagina's die een redactor-veld renderen in plaats van een page builder.

**Architecture:** De bestaande, ongebruikte `articles`-collectie wordt de blog. De projects-views leveren de opmaak: elke partial krijgt een articles-tegenhanger waarin `range` verandert in `theme` en `?range=` in `?theme=`. Het filter blijft client-side Alpine op server-gerenderde `hidden`-attributen. De projects-kant wordt aan het eind in zijn geheel verwijderd.

**Tech Stack:** Statamic 6, Antlers, Tailwind v4 (`@utility`), Alpine 3, PHPUnit 11.

## Global Constraints

- **Tests draaien met** `vendor/bin/phpunit -d memory_limit=1G`, nooit met `php artisan test`.
- **Conditionals:** één keuze is een ternary (`{{ theme ? 'a' : 'b' }}`), niet een `{{ if }}`-blok. Complexere logica gaat bóven de markup of in een `{{ switch }}`.
- **Overzicht en detail van een collectie horen in dezelfde map:** `resources/views/articles/index.antlers.html` en `.../show.antlers.html`.
- **Styling:** Tailwind-utilities in de markup, geen `style=""`. Herhaalde klassenreeksen worden een `@utility` in `resources/css/` — componentgebonden in `components/<naam>.css`, generiek in `base/`. Nieuwe utility → import toevoegen in `site.css`. Geen arbitrary values als `bg-[#1a2b3c]`; nieuwe kleur/spacing/radius wordt eerst een token in het `@theme`-blok.
- **Typografie** hoort in `resources/css/base/typography.css` of `base/rich-text.css`, niet verspreid over componentbestanden.
- **Iconen:** `{{ icon src="phone" }}`, nooit `{{ svg src="icons/..." }}`. De `svg`-tag blijft voor logo's en decoratieve vormen buiten `resources/svg/icons/`.
- **Sliders:** geen eigen breakpoints-object; `data-slider-per-view` en `data-slider-space` op het slider-element.
- **Commentaar:** alleen wat je niet uit de code kunt lezen — een ontbrekend stuk, een cruciale TODO, of een niet-evidente reden waarom iets zo staat.
- **Formattering:** Prettier doet de Tailwind-klassevolgorde en de Antlers-opmaak. Niet handmatig herschikken.
- **PHP:** `vendor/bin/pint --dirty --format agent` draaien na elke PHP-wijziging.
- **Content-copy:** geen gedachtestreepjes in Nederlandse teksten. Splits de zin of gebruik een komma.
- **`SectionTestCase` heeft geen cascade:** `{{ globals:… }}` is daar altijd leeg. Gebruik `Tests\TestCase` met een echte HTTP-request als je globals nodig hebt.

## Bestandsstructuur

| Bestand | Verantwoordelijkheid |
|---|---|
| `content/taxonomies/themes.yaml` + `themes/*.yaml` | De vier thema's |
| `resources/blueprints/taxonomies/themes/themes.yaml` | Eén veld: `title` |
| `content/collections/articles.yaml` | Route, mount, sortering, taxonomie-koppeling |
| `resources/blueprints/collections/articles/article.yaml` | Velden van een artikel |
| `resources/fieldsets/article_redactor.yaml` | Bard met image-knop en video-set |
| `content/collections/articles/nl/*.md` | Zes artikels |
| `resources/views/partials/articleCard.antlers.html` | Kaart in overzicht én slider |
| `resources/css/components/article-card.css` | `.article-card__category` |
| `resources/views/partials/themeFilter.antlers.html` | Filterkolom |
| `resources/js/components/article-filter.js` | Client-side tonen/verbergen |
| `resources/views/articles/index.antlers.html` | Overzichtspagina |
| `resources/views/partials/headers/article.antlers.html` | Detailheader met chips |
| `resources/css/components/chip.css` | `.chip`, `.chip--dark`, `.chip--light` |
| `resources/css/base/rich-text.css` | `.article-body` naast het bestaande `.rich-text` |
| `resources/views/articles/show.antlers.html` | Detailpagina |
| `resources/views/partials/sections/articles.antlers.html` | Page-builder-slider |

---

## Task 1: Taxonomie `themes`

**Files:**
- Create: `content/taxonomies/themes.yaml`
- Create: `resources/blueprints/taxonomies/themes/themes.yaml`
- Create: `content/taxonomies/themes/energie-en-comfort.yaml`
- Create: `content/taxonomies/themes/ramen-en-deuren.yaml`
- Create: `content/taxonomies/themes/terrasoverkapping.yaml`
- Create: `content/taxonomies/themes/zonwering.yaml`
- Test: `tests/Feature/Content/ThemesContentTest.php`

**Interfaces:**
- Consumes: niets.
- Produces: taxonomie-handle `themes` met de slugs `energie-en-comfort`, `ramen-en-deuren`, `terrasoverkapping`, `zonwering` en de titels `Energie en comfort`, `Ramen en deuren`, `Terrasoverkapping`, `Zonwering`. Task 2 koppelt hem aan de collectie, Task 4 leest hem uit met `{{ taxonomy:themes }}`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/ThemesContentTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Term;
use Tests\TestCase;

class ThemesContentTest extends TestCase
{
    public function test_the_four_themes_exist_with_a_title(): void
    {
        $themes = Term::query()->where('taxonomy', 'themes')->get();

        $this->assertCount(4, $themes);

        foreach ($themes as $theme) {
            $this->assertNotEmpty($theme->get('title'), "Thema {$theme->slug()} heeft geen titel");
        }
    }

    public function test_the_slugs_are_the_ones_the_filter_and_the_articles_refer_to(): void
    {
        $slugs = Term::query()->where('taxonomy', 'themes')->get()
            ->map->slug()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['energie-en-comfort', 'ramen-en-deuren', 'terrasoverkapping', 'zonwering'],
            $slugs
        );
    }

    public function test_the_themes_carry_no_order_field(): void
    {
        // Anders dan `range_categories` heeft dit filter geen ontworpen
        // volgorde: het sorteert alfabetisch. Een `order`-veld zou suggereren
        // dat er wél een bedoelde volgorde is.
        $blueprint = file_get_contents(resource_path('blueprints/taxonomies/themes/themes.yaml'));

        $this->assertStringNotContainsString('order', $blueprint);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ThemesContentTest`
Expected: FAIL — geen termen gevonden (`assertCount(4)` krijgt 0), en `file_get_contents` op een niet-bestaande blueprint.

- [ ] **Step 3: Maak de taxonomie**

`content/taxonomies/themes.yaml`:

```yaml
title: Themes
```

`resources/blueprints/taxonomies/themes/themes.yaml`:

```yaml
title: Thema
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              display: Title
              required: true
              validate:
                - required
```

- [ ] **Step 4: Maak de vier termen**

`content/taxonomies/themes/energie-en-comfort.yaml`:

```yaml
id: 7a3c5e91-0001-4b2d-8f6a-1c2d3e4f5a01
title: 'Energie en comfort'
```

`content/taxonomies/themes/ramen-en-deuren.yaml`:

```yaml
id: 7a3c5e91-0002-4b2d-8f6a-1c2d3e4f5a02
title: 'Ramen en deuren'
```

`content/taxonomies/themes/terrasoverkapping.yaml`:

```yaml
id: 7a3c5e91-0003-4b2d-8f6a-1c2d3e4f5a03
title: Terrasoverkapping
```

`content/taxonomies/themes/zonwering.yaml`:

```yaml
id: 7a3c5e91-0004-4b2d-8f6a-1c2d3e4f5a04
title: Zonwering
```

- [ ] **Step 5: Draai de test en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ThemesContentTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
git add content/taxonomies resources/blueprints/taxonomies/themes tests/Feature/Content/ThemesContentTest.php
git commit -m "content: taxonomie themes met vier thema's"
```

---

## Task 2: Collectie `articles` met verse artikels

**Files:**
- Modify: `content/collections/articles.yaml`
- Modify: `resources/blueprints/collections/articles/article.yaml`
- Create: `resources/fieldsets/article_redactor.yaml`
- Delete: `content/collections/articles/nl/2025-05-14.article-2.md`
- Delete: `content/collections/articles/nl/2025-05-14.est-velit-id-id-culpa-in-enim-exercitation-qui-tempor-occaecat-amet-anim-ad-duis.md`
- Delete: `content/collections/articles/nl/2025-05-14.tempor-sunt-nostrud-adipisicing-esse-deserunt-elit-ipsum-sint-esse-consectetur-amet-cillum-sunt-laboris.md`
- Create: zes bestanden in `content/collections/articles/nl/`
- Test: `tests/Feature/Content/ArticlesContentTest.php`

**Interfaces:**
- Consumes: de taxonomie-handle `themes` en de vier slugs uit Task 1.
- Produces: zes gepubliceerde artikels in de collectie `articles`, elk met `title`, `text`, `image`, `theme` (terms, `max_items: 1`) en `redactor`. De redactor augmenteert naar een array van knopen met `type => 'text'` (sleutel `text`, HTML) en `type => 'video'` (sleutel `video`). Route `/nieuws/{slug}`, `sort_dir: desc`. Task 3 rendert `theme.title`, Task 4 telt de thema's, Task 7 loopt over de redactor.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/ArticlesContentTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ArticlesContentTest extends TestCase
{
    public function test_six_articles_exist_with_an_image_a_theme_and_a_body(): void
    {
        $articles = Entry::query()->where('collection', 'articles')->get();

        $this->assertCount(6, $articles);

        foreach ($articles as $article) {
            $this->assertNotEmpty($article->get('image'), "Artikel {$article->slug()} heeft geen beeld");
            $this->assertNotEmpty($article->get('theme'), "Artikel {$article->slug()} heeft geen thema");
            $this->assertNotEmpty($article->get('redactor'), "Artikel {$article->slug()} heeft een lege redactor");
        }
    }

    public function test_every_theme_resolves_to_a_term_of_the_themes_taxonomy(): void
    {
        // `theme` heeft `max_items: 1` en augmenteert dus naar één term, niet
        // naar een collectie. Deze test legt dat vast, want de header en de
        // kaart lezen `theme.title` met dot-notatie.
        foreach (Entry::query()->where('collection', 'articles')->get() as $article) {
            $term = $article->augmentedValue('theme')->value();

            $this->assertNotNull($term, "Het thema van {$article->slug()} augmenteert niet naar een term");
            $this->assertSame('themes', $term->taxonomy()->handle());
            $this->assertNotEmpty($term->get('title'));
        }
    }

    public function test_the_articles_cover_at_least_three_themes(): void
    {
        // Het filter moet zichtbaar iets doen. Met alles onder één thema is
        // een klik niet van "Toon alles" te onderscheiden.
        $slugs = Entry::query()->where('collection', 'articles')->get()
            ->map(fn ($article) => $article->augmentedValue('theme')->value()->slug())
            ->unique();

        $this->assertGreaterThanOrEqual(3, $slugs->count());
    }

    public function test_at_least_one_article_carries_a_video_block_and_one_an_inline_image(): void
    {
        $articles = Entry::query()->where('collection', 'articles')->get();

        $types = $articles->flatMap(fn ($article) => collect($article->augmentedValue('redactor')->value())
            ->map(fn ($node) => $node['type'] ?? null));

        $this->assertTrue($types->contains('video'), 'Geen enkel artikel heeft een videoblok');

        $html = $articles->flatMap(fn ($article) => collect($article->augmentedValue('redactor')->value())
            ->where('type', 'text')
            ->map(fn ($node) => (string) $node['text']))
            ->implode('');

        $this->assertStringContainsString('<img', $html, 'Geen enkel artikel heeft een beeld in de tekst');
    }

    public function test_the_collection_routes_under_nieuws_and_sorts_newest_first(): void
    {
        $yaml = file_get_contents(base_path('content/collections/articles.yaml'));

        $this->assertStringContainsString("route: '/nieuws/{slug}'", $yaml);
        $this->assertStringContainsString('sort_dir: desc', $yaml);
        $this->assertStringContainsString('- themes', $yaml);
    }

    public function test_the_legal_blueprint_keeps_the_plain_redactor_fieldset(): void
    {
        // `redactor.yaml` is gedeeld met legal. De video-set hoort alleen bij
        // artikels, dus die krijgt een eigen fieldset.
        $shared = file_get_contents(resource_path('fieldsets/redactor.yaml'));
        $this->assertStringNotContainsString('sets:', $shared);

        $legal = file_get_contents(resource_path('blueprints/collections/legal/legal.yaml'));
        $this->assertStringContainsString('import: redactor', $legal);

        $article = file_get_contents(resource_path('blueprints/collections/articles/article.yaml'));
        $this->assertStringContainsString('import: article_redactor', $article);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticlesContentTest`
Expected: FAIL — er staan drie lorem-artikels zonder thema, en `article_redactor.yaml` bestaat niet.

- [ ] **Step 3: Maak de eigen redactor-fieldset**

`resources/fieldsets/article_redactor.yaml`:

```yaml
title: 'Article Redactor'
fields:
  -
    handle: redactor
    field:
      type: bard
      display: Redactor
      container: assets
      remove_empty_nodes: false
      buttons:
        - h2
        - h3
        - bold
        - italic
        - unorderedlist
        - orderedlist
        - anchor
        - table
        - image
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

Laat `resources/fieldsets/redactor.yaml` ongemoeid: die blijft van `legal`.

- [ ] **Step 4: Werk de collectie en de blueprint bij**

`content/collections/articles.yaml` volledig vervangen door:

```yaml
title: Nieuws
icon: collections
template: articles/show
layout: layout
mount: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
revisions: false
route: '/nieuws/{slug}'
date: true
sort_dir: desc
taxonomies:
  - themes
preview_targets:
  -
    label: Entry
    url: '{permalink}'
    refresh: true
```

De `mount`-id is die van de bestaande `realisaties.md`; Task 5 hernoemt die entry naar `nieuws.md` met behoud van de id.

In `resources/blueprints/collections/articles/article.yaml`: vervang `import: redactor` door `import: article_redactor`, en voeg in de `sidebar`-tab ná het `date`-veld toe:

```yaml
          -
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

- [ ] **Step 5: Verwijder de drie lorem-artikels**

```bash
rm content/collections/articles/nl/2025-05-14.*.md
```

- [ ] **Step 6: Schrijf de zes artikels**

`date: true` betekent dat de bestandsnaam een datumprefix draagt. De projects-collectie had dat niet, dus die bestandsnamen zijn geen voorbeeld.

`content/collections/articles/nl/2026-07-28.zip-screens-kiezen-voor-een-nieuwbouw.md`:

```markdown
---
id: a4e8b1c7-0001-4d3f-9a2b-5c6d7e8f9a01
title: 'Zip-screens kiezen voor een nieuwbouw'
text: 'Waarom een screen aan de buitenkant meer doet tegen oververhitting dan welk gordijn ook, en waar je op let bij de keuze.'
theme:
  - 7a3c5e91-0004-4b2d-8f6a-1c2d3e4f5a04
image: dummy-images/test-img-8.jpg
redactor:
  -
    type: text
    content:
      -
        type: heading
        attrs:
          level: 2
        content:
          -
            type: text
            text: 'Buiten tegenhouden, niet binnen'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Een zonnescherm aan de binnenkant houdt de warmte pas tegen als ze het glas al gepasseerd is. Op dat moment zit de energie in de kamer en raakt ze er niet meer uit. Een zip-screen hangt buiten en kaatst het zonlicht terug voor het het glas raakt, wat op een zuidgevel het verschil maakt tussen een leefbare en een onbruikbare namiddag.'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'De ritsgeleiding houdt het doek strak in de zijkanten. Daardoor klappert het niet bij wind en blijft het zicht naar buiten open, ook als het scherm helemaal neer is.'
  -
    type: set
    attrs:
      id: video01
      values:
        type: video
        video: 'https://www.youtube.com/watch?v=aqz-KE-bpKQ'
  -
    type: text
    content:
      -
        type: heading
        attrs:
          level: 3
        content:
          -
            type: text
            text: 'Waar je op let'
      -
        type: bulletList
        content:
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'De openheidsfactor van het doek bepaalt hoeveel je nog naar buiten ziet.'
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'De windklasse bepaalt tot welke windsnelheid het scherm neer mag blijven.'
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'Voorzie de bekabeling tijdens de ruwbouw, dan blijft de motor onzichtbaar.'
---
```

`content/collections/articles/nl/2026-07-21.een-pergola-die-het-hele-jaar-bruikbaar-is.md`:

```markdown
---
id: a4e8b1c7-0002-4d3f-9a2b-5c6d7e8f9a02
title: 'Een pergola die het hele jaar bruikbaar is'
text: 'Lamellen, glazen schuifwanden en verwarming maken van een terras een buitenkamer die in maart al meetelt.'
theme:
  - 7a3c5e91-0003-4b2d-8f6a-1c2d3e4f5a03
image: dummy-images/test-img-7.jpg
redactor:
  -
    type: text
    content:
      -
        type: paragraph
        content:
          -
            type: text
            text: 'De meeste terrassen worden drie maanden per jaar gebruikt. Dat ligt zelden aan de oppervlakte en bijna altijd aan wind, regen en het gebrek aan schaduw op de warmste momenten.'
      -
        type: heading
        attrs:
          level: 2
        content:
          -
            type: text
            text: 'Draaibare lamellen'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Met draaibare lamellen stuur je zon en regen apart. Open staan ze voor de ochtendzon, dicht houden ze een bui tegen, en halfopen laten ze warmte weg zonder het terras donker te maken.'
      -
        type: image
        attrs:
          src: dummy-images/test-img-9.jpg
          alt: 'Pergola met draaibare lamellen boven een terras'
      -
        type: heading
        attrs:
          level: 3
        content:
          -
            type: text
            text: 'Wanden erbij'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Glazen schuifwanden aan de windzijde verlengen het seizoen met twee maanden aan elke kant. Ze schuiven volledig weg, zodat het terras in de zomer weer open staat.'
---
```

`content/collections/articles/nl/2026-07-10.hoeveel-scheelt-nieuw-schrijnwerk-op-je-energiefactuur.md`:

```markdown
---
id: a4e8b1c7-0003-4d3f-9a2b-5c6d7e8f9a03
title: 'Hoeveel scheelt nieuw schrijnwerk op je energiefactuur'
text: 'Een eerlijke inschatting van wat je terugverdient, en waarom de U-waarde van het glas maar de helft van het verhaal is.'
theme:
  - 7a3c5e91-0001-4b2d-8f6a-1c2d3e4f5a01
image: dummy-images/test-img-11.jpg
redactor:
  -
    type: text
    content:
      -
        type: paragraph
        content:
          -
            type: text
            text: 'De winst van nieuw schrijnwerk zit in twee dingen die los van elkaar staan: de isolatiewaarde van het glas en de luchtdichtheid van de aansluiting met de muur. Het eerste staat op de offerte, het tweede hangt volledig af van de plaatsing.'
      -
        type: heading
        attrs:
          level: 2
        content:
          -
            type: text
            text: 'De U-waarde'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Enkel glas uit de jaren zestig zit rond 5,8 W/m²K. Hedendaagse driedubbele beglazing haalt 0,6. Dat is bijna een factor tien, maar alleen op het glasoppervlak zelf.'
      -
        type: heading
        attrs:
          level: 3
        content:
          -
            type: text
            text: 'De aansluiting'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Een kier van twee millimeter rond een raam laat meer warmte door dan het volledige glasoppervlak. Daarom meten we na plaatsing na, en daarom staat de dichting in ons bestek even uitgebreid beschreven als het profiel.'
---
```

`content/collections/articles/nl/2026-06-24.wanneer-vervang-je-je-rolluiken.md`:

```markdown
---
id: a4e8b1c7-0004-4d3f-9a2b-5c6d7e8f9a04
title: 'Wanneer vervang je je rolluiken'
text: 'Vier signalen dat herstellen geen zin meer heeft, en wat een vervanging in de praktijk inhoudt.'
theme:
  - 7a3c5e91-0004-4b2d-8f6a-1c2d3e4f5a04
image: dummy-images/test-img-12.jpg
redactor:
  -
    type: text
    content:
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Een rolluik gaat makkelijk twintig jaar mee. Daarna is de vraag niet of het nog werkt, maar of herstellen nog opweegt tegen vervangen.'
      -
        type: heading
        attrs:
          level: 2
        content:
          -
            type: text
            text: 'De vier signalen'
      -
        type: orderedList
        content:
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'Het luik loopt scheef of blijft halverwege hangen.'
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'Lamellen zijn geknikt of laten licht door waar dat niet hoort.'
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'De kast is niet geïsoleerd en voelt in de winter koud aan.'
          -
            type: listItem
            content:
              -
                type: paragraph
                content:
                  -
                    type: text
                    text: 'Onderdelen zijn niet meer leverbaar voor jouw type.'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Het derde punt weegt het zwaarst. Een oude, niet-geïsoleerde rolluikkast is vaak het koudste punt van de hele gevel, en dat los je met een herstelling niet op.'
---
```

`content/collections/articles/nl/2026-06-12.een-voordeur-die-past-bij-een-jaren-dertig-gevel.md`:

```markdown
---
id: a4e8b1c7-0005-4d3f-9a2b-5c6d7e8f9a05
title: 'Een voordeur die past bij een jaren dertig gevel'
text: 'Hedendaagse isolatiewaarden halen zonder dat de gevel eruitziet alsof er een kantoordeur in hangt.'
theme:
  - 7a3c5e91-0002-4b2d-8f6a-1c2d3e4f5a02
image: dummy-images/test-img-13.jpg
redactor:
  -
    type: text
    content:
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Bij een gerenoveerde gevel uit het interbellum is de voordeur het detail waar het meteen misloopt. De verhouding tussen paneel en glas ligt daar anders dan bij nieuwbouw, en een strak vlak paneel valt onmiddellijk op.'
      -
        type: heading
        attrs:
          level: 2
        content:
          -
            type: text
            text: 'Verticale indeling'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'De originele deuren waren hoger dan breed, met een smal bovenlicht. Die verhouding kun je in aluminium aanhouden zonder in te leveren op isolatie: de profielen zijn smal genoeg om het bovenlicht te behouden.'
      -
        type: heading
        attrs:
          level: 3
        content:
          -
            type: text
            text: 'Kleur'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Een gebroken donkergroen of diepblauw sluit beter aan bij baksteen uit die periode dan het antracietgrijs dat overal opduikt. Beide zijn standaard leverbaar in een structuurlak.'
---
```

`content/collections/articles/nl/2026-05-30.slimme-sturing-zonder-je-hele-huis-te-vernieuwen.md`:

```markdown
---
id: a4e8b1c7-0006-4d3f-9a2b-5c6d7e8f9a06
title: 'Slimme sturing zonder je hele huis te vernieuwen'
text: 'Bestaande rolluiken en screens automatiseren kan meestal met een motor en een brug, zonder breekwerk.'
theme:
  - 7a3c5e91-0001-4b2d-8f6a-1c2d3e4f5a01
image: dummy-images/test-img-15.jpg
redactor:
  -
    type: text
    content:
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Automatiseren klinkt als een verbouwing, maar bij bestaande rolluiken en screens gaat het meestal om twee ingrepen: een motor in de as en een brug die het radiosignaal naar je netwerk vertaalt.'
      -
        type: heading
        attrs:
          level: 2
        content:
          -
            type: text
            text: 'Wat het oplevert'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'De echte winst zit niet in bedienen vanaf de zetel, maar in de automatische stand. Een zonnesensor laat de screens zakken vóór de kamer opwarmt, ook als je niet thuis bent. Dat is precies het moment waarop handmatige bediening tekortschiet.'
      -
        type: heading
        attrs:
          level: 3
        content:
          -
            type: text
            text: 'Per kamer of in één keer'
      -
        type: paragraph
        content:
          -
            type: text
            text: 'Je hoeft niet alles tegelijk te doen. Eén brug bedient tot vijftig punten, dus je kunt met de zuidgevel beginnen en de rest later toevoegen.'
---
```

- [ ] **Step 7: Draai de test en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticlesContentTest`
Expected: PASS, 6 tests.

Als `test_at_least_one_article_carries_a_video_block_and_one_an_inline_image` faalt op de `<img>`-assertie, controleer dan of de `image`-knop in `article_redactor.yaml` staat: zonder die knop laat Bard het `image`-knooptype vallen bij het opslaan.

- [ ] **Step 8: Commit**

```bash
git add content/collections/articles content/collections/articles.yaml resources/blueprints/collections/articles resources/fieldsets/article_redactor.yaml tests/Feature/Content/ArticlesContentTest.php
git commit -m "content: articles-collectie met thema's, eigen redactor en zes artikels"
```

---

## Task 3: `articleCard`

**Files:**
- Create: `resources/views/partials/articleCard.antlers.html`
- Create: `resources/css/components/article-card.css`
- Modify: `resources/css/site.css`
- Test: `tests/Feature/Sections/ArticleCardTest.php`

**Interfaces:**
- Consumes: de artikels uit Task 2 (`title`, `url`, `image`, `theme.title`).
- Produces: de partial `articleCard`, die de klassen `article-card` en `article-card__category` rendert. Task 6 (overzicht) en Task 8 (slider) gebruiken hem.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ArticleCardTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Entry;

class ArticleCardTest extends SectionTestCase
{
    public function test_the_overline_of_a_real_article_is_the_theme_and_not_the_title(): void
    {
        // Array-fixtures dekken deze bug niet af: `theme` heeft `max_items: 1`
        // en augmenteert naar één term. Een pair scoopt daar niet in en laat
        // `{{ title }}` terugvallen op de artikeltitel. Alleen een echte entry
        // legt dat vast.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('slug', 'een-pergola-die-het-hele-jaar-bruikbaar-is')
            ->first();

        $html = $this->render('{{ partial src="articleCard" }}', $article->toAugmentedArray());

        $this->assertStringContainsString(
            '<span class="article-card__category">Terrasoverkapping</span>',
            $html
        );
    }

    public function test_renders_a_linked_card_with_the_theme_as_category(): void
    {
        $html = $this->render('{{ partial src="articleCard" }}', [
            'title' => 'Zip-screens kiezen voor een nieuwbouw',
            'url' => '/nieuws/zip-screens-kiezen-voor-een-nieuwbouw',
            'theme' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
        ]);

        $this->assertStringContainsString('class="article-card', $html);
        $this->assertStringContainsString('href="/nieuws/zip-screens-kiezen-voor-een-nieuwbouw"', $html);
        $this->assertStringContainsString('article-card__category', $html);
        $this->assertStringContainsString('Zonwering', $html);
        $this->assertStringContainsString('<h3>Zip-screens kiezen voor een nieuwbouw</h3>', $html);
    }

    public function test_omits_the_category_when_no_theme_is_set(): void
    {
        $html = $this->render('{{ partial src="articleCard" }}', [
            'title' => 'Los artikel',
            'url' => '/nieuws/los-artikel',
        ]);

        $this->assertStringNotContainsString('article-card__category', $html);
        $this->assertStringContainsString('<h3>Los artikel</h3>', $html);
    }

    public function test_carries_no_slider_markup_of_its_own(): void
    {
        $html = $this->render('{{ partial src="articleCard" }}', [
            'title' => 'Wanneer vervang je je rolluiken',
            'url' => '/nieuws/wanneer-vervang-je-je-rolluiken',
        ]);

        $this->assertStringNotContainsString('swiper-slide', $html);
        $this->assertStringNotContainsString('data-slider', $html);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticleCardTest`
Expected: FAIL — de partial `articleCard` bestaat niet.

- [ ] **Step 3: Maak de partial**

`resources/views/partials/articleCard.antlers.html`:

```antlers
<a href="{{ url }}" class="article-card group flex h-full flex-col gap-6">
    {{ if image }}
        {{ img :src="image" ratio="1/1" sizes="(min-width: 1024px) 33vw, 90vw" class="rounded-md max-h-100" }}
    {{ /if }}

    <div class="flex grow flex-col justify-end gap-2.5">
        {{ if theme }}
            <span class="article-card__category">{{ theme.title }}</span>
        {{ /if }}
        <div class="flex items-end justify-between gap-4">
            <h3>{{ title }}</h3>
            <span aria-hidden="true" class="contents">
                {{ icon src="arrow-right" class="size-6 lg:size-7 shrink-0 -rotate-45 transition-transform group-hover:translate-x-1" }}
            </span>
        </div>
    </div>
    <div class="mt-auto h-px w-full bg-black/10"></div>
</a>
```

Let op: `{{ icon src="arrow-right" }}` en niet `{{ svg src="icons/regular/arrow-right" }}`. `projectCard` gebruikte nog de oude vorm; `App\Tags\Icon` zet er zelf `icons/regular/` omheen.

- [ ] **Step 4: Maak het CSS-bestand**

`resources/css/components/article-card.css`:

```css
.article-card__category {
    @apply font-semibold uppercase text-black/40;

    font-size: clamp(0.75rem, 0.571rem + 0.446vw, 1rem); /* 12 → 16 */
    letter-spacing: 0.02em; /* 2% van de fontsize, gemeten in Figma */
    line-height: 1.1;
}
```

Voeg in `resources/css/site.css` de import toe, direct naast de bestaande `project-card`-import:

```css
@import './components/article-card.css';
```

- [ ] **Step 5: Draai de test en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticleCardTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/articleCard.antlers.html resources/css/components/article-card.css resources/css/site.css tests/Feature/Sections/ArticleCardTest.php
git commit -m "feat: articleCard met het thema als overline"
```

---

## Task 4: `themeFilter`

**Files:**
- Create: `resources/views/partials/themeFilter.antlers.html`
- Test: `tests/Feature/Sections/ThemeFilterTest.php`

**Interfaces:**
- Consumes: de artikels en de taxonomie uit Task 1 en 2.
- Produces: de partial `themeFilter`, die `<nav class="theme-filter …">` rendert met één knop per gebruikt thema plus "Toon alles". Elke knop draagt `data-theme="<slug>"`, `href="?theme=<slug>"` en `@click.prevent="select('<slug>')"`. Task 6 hangt de Alpine-scope `articleFilter()` eromheen.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ThemeFilterTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ThemeFilterTest extends SectionTestCase
{
    public function test_renders_show_all_first_followed_by_every_used_theme(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        $this->assertStringContainsString('<nav class="theme-filter', $html);
        $this->assertStringContainsString('Toon alles', $html);

        // Eén knop voor "Toon alles" plus één per gebruikt thema. Vier thema's
        // bestaan er, en alle vier hangt er minstens één artikel aan.
        $this->assertSame(5, substr_count($html, 'data-theme='));

        $this->assertLessThan(
            strpos($html, 'data-theme="energie-en-comfort"'),
            strpos($html, 'data-theme=""'),
            '"Toon alles" hoort vooraan te staan'
        );
    }

    public function test_the_themes_are_sorted_alphabetically_by_title(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        $order = ['energie-en-comfort', 'ramen-en-deuren', 'terrasoverkapping', 'zonwering'];

        $positions = array_map(
            fn ($slug) => strpos($html, 'data-theme="' . $slug . '"'),
            $order
        );

        $sorted = $positions;
        sort($sorted);

        $this->assertSame($sorted, $positions, 'De thema-pillen horen alfabetisch te staan');
    }

    public function test_show_all_is_active_when_no_theme_is_selected(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        // Doelt op de statische `class`-attribuutwaarde zelf, niet op een
        // substring-scan over de hele tag: `:class`-Alpine-bindings noemen
        // "btn--secondary" ook letterlijk in knoppen die niet actief zijn,
        // dus die tekst alleen bewijst niets over de echte staat.
        $this->assertMatchesRegularExpression(
            '/data-theme=""\s+class="[^"]*btn--secondary[^"]*"/',
            $html,
            '"Toon alles" hoort standaard actief te zijn'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/data-theme="zonwering"\s+class="[^"]*btn--secondary[^"]*"/',
            $html,
            'Zonder ?theme hoort de zonwering-knop niet actief te staan'
        );
    }

    public function test_the_active_state_is_also_exposed_server_side_via_aria_current(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        // `:aria-current` is Alpine-only; zonder JavaScript en vóór Alpine
        // boot moet het echte attribuut er al staan.
        $this->assertMatchesRegularExpression(
            '/data-theme=""\s+class="[^"]*btn--secondary[^"]*"\s+aria-current="page"/',
            $html,
            '"Toon alles" hoort server-side aria-current="page" te dragen'
        );

        // Klasse en aria-current kunnen niet uiteenlopen: precies één pil.
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertSame(
            preg_match_all('/\sclass="[^"]*btn--secondary[^"]*"/', $html),
            substr_count($html, 'aria-current="page"'),
            'Actieve klasse en aria-current horen op dezelfde knoppen te staan'
        );
    }

    public function test_every_theme_button_links_to_its_own_query_string(): void
    {
        $html = $this->render('{{ partial src="themeFilter" }}');

        $this->assertStringContainsString('href="?theme=zonwering"', $html);
        $this->assertStringContainsString("select('zonwering')", $html);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ThemeFilterTest`
Expected: FAIL — de partial `themeFilter` bestaat niet.

- [ ] **Step 3: Maak de partial**

`resources/views/partials/themeFilter.antlers.html`:

```antlers
<nav class="theme-filter min-w-0 lg:sticky lg:top-10 lg:self-start" aria-label="{{ trans:site.filter_label }}">
    {{# `-mx-4 px-4` spiegelt de padding van `.container`: onder lg loopt de rij tot de schermrand. #}}
    <ul
        class="-mx-4 scrollbar-none flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-col lg:items-start lg:gap-4 lg:overflow-x-visible xl:gap-5 lg:px-0">
        <li class="shrink-0">
            <a
                href="{{ url }}"
                data-theme=""
                class="{{ get:theme ? 'btn--tertiary' : 'btn--secondary' }} btn whitespace-nowrap"
                {{ if !get:theme }} aria-current="page"{{ /if }}
                :class="{ 'btn--secondary': active === 'all', 'btn--tertiary': active !== 'all' }"
                :aria-current="active === 'all' ? 'page' : false"
                @click.prevent="select('all')">
                {{ trans:site.filter_all }}
            </a>
        </li>

        {{# `min_count` doet het werk waarvoor `App\Tags\ProjectRanges` bestond:
            alleen thema's tonen waar minstens één artikel aan hangt, zodat een
            klik nooit een lege grid oplevert. #}}
        {{ taxonomy:themes collection="articles" min_count="1" sort="title" }}
            <li class="shrink-0">
                <a
                    href="?theme={{ slug }}"
                    data-theme="{{ slug }}"
                    class="{{ get:theme == slug ? 'btn--secondary' : 'btn--tertiary' }} btn whitespace-nowrap"
                    {{ if get:theme == slug }} aria-current="page"{{ /if }}
                    :class="{ 'btn--secondary': active === '{{ slug }}', 'btn--tertiary': active !== '{{ slug }}' }"
                    :aria-current="active === '{{ slug }}' ? 'page' : false"
                    @click.prevent="select('{{ slug }}')">
                    {{ title | entities }}
                </a>
            </li>
        {{ /taxonomy:themes }}
    </ul>
</nav>
```

- [ ] **Step 4: Draai de test en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ThemeFilterTest`
Expected: PASS, 5 tests.

Als `test_renders_show_all_first_followed_by_every_used_theme` 1 in plaats van 5 telt, controleer dan of `taxonomies: - themes` in `content/collections/articles.yaml` staat: zonder die koppeling ziet de taxonomy-tag geen artikels en filtert `min_count="1"` alles weg.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/themeFilter.antlers.html tests/Feature/Sections/ThemeFilterTest.php
git commit -m "feat: themeFilter op basis van de themes-taxonomie"
```

---

## Task 5: `/nieuws` als pagina, `/realisaties` eruit

**Files:**
- Create: `content/collections/pages/nl/nieuws.md`
- Create: `resources/blueprints/collections/pages/articles_overview.yaml`
- Delete: `content/collections/pages/nl/realisaties.md`
- Delete: `resources/blueprints/collections/pages/projects_overview.yaml`
- Delete: `resources/views/projects/index.antlers.html`
- Modify: `content/trees/collections/nl/pages.yaml`
- Modify: `content/trees/navigation/nl/main.yaml`
- Modify: `tests/Feature/Sections/FooterTest.php:36-38`
- Modify: `tests/Feature/Content/OffertePageTest.php:156`
- Modify: `tests/Feature/Content/ContactPageTest.php:50`
- Delete: `tests/Feature/Content/ProjectsOverviewPageTest.php`
- Test: `tests/Feature/Content/NieuwsPageTest.php`

**Interfaces:**
- Consumes: niets nieuws.
- Produces: de pagina-entry met id `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03`, slug `nieuws`, titel `Nieuws`, blueprint `articles_overview`, template `articles/index`. Die id is de `mount` uit Task 2. Task 6 vult het template.

Deze taak verwijdert `projects/index.antlers.html` terwijl `content/collections/projects.yaml` blijft staan. De collectie routeert op `/realisaties/{slug}` via `projects/show`, dus de detailpagina's blijven werken; alleen het overzicht verdwijnt. `ProjectsOverviewPageTest` gaat daarom mee weg. De volledige opruiming van `projects` gebeurt in Task 9.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/NieuwsPageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class NieuwsPageTest extends TestCase
{
    public function test_the_page_lives_at_nieuws_and_keeps_the_old_entry_id(): void
    {
        // De id wordt overgenomen van realisaties.md, want hij staat in de
        // navigatieboom, in de paginaboom én als `mount` op de collectie.
        // Een nieuwe id zou alle drie moeten bijwerken.
        $page = Entry::find('c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03');

        $this->assertNotNull($page);
        $this->assertSame('nieuws', $page->slug());
        $this->assertSame('Nieuws', $page->get('title'));
        $this->assertSame('articles/index', $page->get('template'));
    }

    public function test_realisaties_is_gone(): void
    {
        $this->assertNull(
            Entry::query()->where('collection', 'pages')->where('slug', 'realisaties')->first()
        );

        $this->get('/realisaties')->assertNotFound();
    }

    public function test_the_collection_is_mounted_on_this_page(): void
    {
        $yaml = file_get_contents(base_path('content/collections/articles.yaml'));

        $this->assertStringContainsString('mount: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03', $yaml);
    }

    public function test_the_main_navigation_points_at_nieuws(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('href="/nieuws"', $html);
        $this->assertStringNotContainsString('href="/realisaties"', $html);
    }

    public function test_the_dead_boilerplate_mount_is_out_of_the_page_tree(): void
    {
        $tree = file_get_contents(base_path('content/trees/collections/nl/pages.yaml'));

        $this->assertStringNotContainsString('8cf703da-5dde-4543-89aa-8f2d5c3011d9', $tree);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=NieuwsPageTest`
Expected: FAIL — de entry heet nog `realisaties`.

- [ ] **Step 3: Maak de blueprint**

`resources/blueprints/collections/pages/articles_overview.yaml`:

```yaml
title: 'Articles Overview'
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            import: page_builder
  preview:
    display: Preview
    sections:
      -
        fields:
          -
            import: preview
  seo:
    display: SEO
    sections:
      -
        fields:
          -
            import: seo
  sidebar:
    display: Sidebar
    sections:
      -
        fields:
          -
            handle: slug
            field:
              type: slug
              localizable: true
              validate: 'max:200'
          -
            import: template
```

```bash
rm resources/blueprints/collections/pages/projects_overview.yaml
```

- [ ] **Step 4: Maak de pagina en verwijder realisaties**

`content/collections/pages/nl/nieuws.md`:

```markdown
---
id: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
blueprint: articles_overview
title: Nieuws
text: 'Wat we leren op de werf, vertaald naar keuzes die je thuis kunt maken.'
template: articles/index
seo_noindex: false
page_builder:
  -
    id: nieuwscta
    type: cta
    overline: Offerte
    title: 'Zin om verder te praten?'
    text: 'Vraag vrijblijvend een offerte aan. We komen langs, meten na en rekenen de opties voor je door.'
    image: dummy-images/test-img-14.jpg
    link:
      -
        type: entry
        entry:
          - b7c8d9e0-0003-4f5a-8b6c-7d8e9f0a1b02
        label: 'Vraag offerte aan'
        new_tab: false
---
```

```bash
rm content/collections/pages/nl/realisaties.md
rm resources/views/projects/index.antlers.html
rm tests/Feature/Content/ProjectsOverviewPageTest.php
```

- [ ] **Step 5: Werk de bomen en de navigatie bij**

In `content/trees/collections/nl/pages.yaml`: verwijder de rij

```yaml
  -
    entry: 8cf703da-5dde-4543-89aa-8f2d5c3011d9
```

Dat is de mount van de boilerplate-`articles` naar een entry die niet bestaat. De rij met `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03` blijft ongewijzigd staan.

In `content/trees/navigation/nl/main.yaml`: wijzig alleen de titel van het item met id `3b6e9620-9efd-4402-8b93-4a4d259d909d`:

```yaml
  -
    id: 3b6e9620-9efd-4402-8b93-4a4d259d909d
    title: Nieuws
    data:
      link_type: entry
      entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
```

De `entry`-verwijzing blijft, want de id is behouden.

- [ ] **Step 6: Werk de drie bestaande tests bij**

In `tests/Feature/Sections/FooterTest.php`, vervang het blok

```php
        // De `nav:main`-lus (Aanbod, Realisaties, Service, Over ons, Contact).
        $this->assertSame(5, substr_count($html, 'href="/aanbod"')
            + substr_count($html, 'href="/realisaties"')
            + substr_count($html, 'href="/service"')
            + substr_count($html, 'href="/over-ons"')
            + substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Realisaties', $html);
```

door

```php
        // De `nav:main`-lus (Aanbod, Nieuws, Service, Over ons, Contact).
        $this->assertSame(5, substr_count($html, 'href="/aanbod"')
            + substr_count($html, 'href="/nieuws"')
            + substr_count($html, 'href="/service"')
            + substr_count($html, 'href="/over-ons"')
            + substr_count($html, 'href="/contact"'));
        $this->assertStringContainsString('Nieuws', $html);
```

In `tests/Feature/Content/OffertePageTest.php`, vervang

```php
        $realisaties = Entry::query()->where('collection', 'pages')->where('slug', 'realisaties')->first();
        $this->assertSame($offerte->id(), $realisaties->get('page_builder')[0]['link'][0]['entry'][0]);
```

door

```php
        $nieuws = Entry::query()->where('collection', 'pages')->where('slug', 'nieuws')->first();
        $this->assertSame($offerte->id(), $nieuws->get('page_builder')[0]['link'][0]['entry'][0]);
```

In `tests/Feature/Content/ContactPageTest.php` staat op regel 50 een commentaarregel die naar realisaties verwijst. Vervang het woord `realisaties` daar door `nieuws`; de assertie eronder verandert niet.

- [ ] **Step 7: Draai de betrokken tests**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter='NieuwsPageTest|FooterTest|OffertePageTest|ContactPageTest'`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A content/collections/pages content/trees resources/blueprints/collections/pages resources/views/projects tests/
git commit -m "content: /realisaties wordt /nieuws met behoud van de entry-id"
```

---

## Task 6: Overzichtspagina `/nieuws`

**Files:**
- Create: `resources/views/articles/index.antlers.html` (overschrijft het bestaande boilerplate-bestand)
- Create: `resources/js/components/article-filter.js`
- Modify: `resources/js/site.js:4,9`
- Delete: `resources/js/components/project-filter.js`
- Delete: `resources/views/partials/rangeFilter.antlers.html`
- Delete: `tests/Feature/Sections/RangeFilterTest.php`
- Test: `tests/Feature/Content/ArticlesOverviewPageTest.php`

**Interfaces:**
- Consumes: `themeFilter` (Task 4), `articleCard` (Task 3), de pagina uit Task 5.
- Produces: `data-section="articles-overview"` met `x-data="articleFilter()"`. De Alpine-component exporteert `articleFilter()` met de state `active` (string, `'all'` of een themaslug), en de methodes `matches(slug: string): boolean` en `select(slug: string): void`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/ArticlesOverviewPageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ArticlesOverviewPageTest extends TestCase
{
    public function test_the_page_renders_with_its_header_and_divider(): void
    {
        $response = $this->get('/nieuws');

        $response->assertOk();
        $response->assertSee('Nieuws', false);
        $response->assertSee('border-t border-black/10', false);
    }

    public function test_the_filter_only_offers_themes_that_have_articles(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertStringContainsString('Toon alles', $html);

        $this->assertStringContainsString('data-theme="energie-en-comfort"', $html);
        $this->assertStringContainsString('data-theme="ramen-en-deuren"', $html);
        $this->assertStringContainsString('data-theme="terrasoverkapping"', $html);
        $this->assertStringContainsString('data-theme="zonwering"', $html);
    }

    public function test_it_renders_every_article_as_a_card_without_a_slider(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertSame(6, substr_count($html, 'article-card '));
        $this->assertStringNotContainsString('data-slider', $html);
        $this->assertStringNotContainsString('swiper-slide', $html);
    }

    public function test_without_a_query_string_nothing_is_hidden(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertSame(0, preg_match_all('/<li\s+hidden/', $html));
    }

    public function test_a_theme_query_string_hides_the_others_without_dropping_them(): void
    {
        $html = $this->get('/nieuws?theme=zonwering')->getContent();

        // Alle zes kaarten blijven in de DOM staan — Alpine moet ze terug
        // kunnen tonen zonder nieuwe request.
        $this->assertSame(6, substr_count($html, 'article-card '));

        // Twee artikels hangen aan `zonwering`, dus vier staan er verborgen.
        $this->assertSame(4, preg_match_all('/<li\s+hidden/', $html));

        $this->assertStringContainsString('Zip-screens kiezen voor een nieuwbouw', $html);
    }

    public function test_a_theme_query_string_marks_that_button_active(): void
    {
        $html = $this->get('/nieuws?theme=zonwering')->getContent();

        // Doelt op de statische `class`-attribuutwaarde zelf, niet op een
        // substring-scan over de hele tag: `:class`-Alpine-bindings noemen
        // "btn--secondary" ook letterlijk in knoppen die niet actief zijn.
        $this->assertMatchesRegularExpression(
            '/data-theme="zonwering"\s+class="[^"]*btn--secondary[^"]*"/',
            $html,
            'De zonwering-knop hoort actief te staan'
        );
    }

    public function test_the_active_pill_carries_a_server_rendered_aria_current(): void
    {
        // Zonder JavaScript en vóór Alpine boot is `aria-current` de enige
        // programmatische actieve staat, dus die moet uit de server komen.
        $html = $this->get('/nieuws?theme=zonwering')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-theme="zonwering"\s+class="[^"]*btn--secondary[^"]*"\s+aria-current="page"/',
            $html,
            'De actieve knop hoort server-side aria-current="page" te dragen'
        );

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertDoesNotMatchRegularExpression(
            '/data-theme=""\s+class="[^"]*"\s+aria-current=/',
            $html,
            '"Toon alles" hoort niet actief te zijn bij ?theme=zonwering'
        );
    }

    public function test_show_all_carries_the_aria_current_when_no_theme_is_selected(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertMatchesRegularExpression(
            '/data-theme=""\s+class="[^"]*btn--secondary[^"]*"\s+aria-current="page"/',
            $html,
            '"Toon alles" hoort standaard aria-current="page" te dragen'
        );

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function test_it_wires_up_the_alpine_filter(): void
    {
        $html = $this->get('/nieuws')->getContent();

        // De "geen animatie"-eis geldt voor het filter en de grid die deze
        // template zelf bouwt, niet voor de rest van het document: de
        // site-brede cookie-consent- en navigatie-partials leven buiten dit
        // section-element en horen niet mee te tellen.
        $start = strpos($html, 'data-section="articles-overview"');
        $end = strpos($html, '</section>', $start);
        $section = substr($html, $start, $end - $start);

        $this->assertStringContainsString('x-data="articleFilter(', $section);
        $this->assertStringContainsString(':hidden="!matches(', $section);
        $this->assertStringNotContainsString('x-transition', $section);
    }

    public function test_the_newest_article_comes_first(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertLessThan(
            strpos($html, 'Slimme sturing zonder je hele huis te vernieuwen'),
            strpos($html, 'Zip-screens kiezen voor een nieuwbouw'),
            'De collectie hoort op datum aflopend te sorteren'
        );
    }

    public function test_the_page_builder_renders_below_the_grid(): void
    {
        $html = $this->get('/nieuws')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertStringContainsString('Zin om verder te praten?', $html);

        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'data-section="articles-overview"'),
            'De page builder hoort onder de grid te staan'
        );
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticlesOverviewPageTest`
Expected: FAIL — `/nieuws` rendert nog het boilerplate-template met een `grid-cols-3` en geen filter.

- [ ] **Step 3: Schrijf het template**

`resources/views/articles/index.antlers.html`, volledig vervangen door:

```antlers
{{ partial:headers/default divider="true" }}
<section class="section section--default" data-section="articles-overview" x-data="articleFilter()">
    <div class="container">
        {{# De filterkolom is zo breed als zijn breedste pil; de grid krijgt de rest. #}}
        <div class="grid grid-gutter gap-8 lg:grid-cols-[max-content_1fr] lg:gap-x-16 2xl:gap-x-20">
            {{ partial:themeFilter }}
            <ul class="grid grid-gutter md:grid-cols-2">
                {{ collection:articles }}
                    {{# Ternary en geen `{{ if }}`-blok: dit zet één waarde.
                        `projects/index` gebruikte hier nog twee regels. #}}
                    {{ theme_slug = themes ? themes:slug : '' }}
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

- [ ] **Step 4: Maak de Alpine-component**

`resources/js/components/article-filter.js`:

```js
/**
 * Filtert de artikelgrid op /nieuws.
 *
 * De server rendert altijd álle artikels en zet `hidden` op de kaarten die
 * bij de eerste paint niet matchen. Dit component neemt datzelfde attribuut
 * over via `:hidden`, zodat server en client hetzelfde mechanisme gebruiken:
 * geen flits bij het booten, geen animatie, en "Toon alles" werkt zonder
 * request omdat alle kaarten al in de DOM staan.
 *
 * Leest `?theme=` zelf uit `window.location.search` in plaats van een
 * server-geïnterpoleerd argument aan te nemen: een ruwe `{{ get:theme }}`
 * in `x-data="articleFilter('...')"` zou de queryparameter ongefilterd in
 * een Alpine-expressie plaatsen, wat een reflected-XSS-gat opent (de browser
 * decodeert HTML-entities vóórdat Alpine de attribuutwaarde evalueert, dus
 * escapen aan de serverkant is hier geen verdediging). Door geen argument
 * aan te nemen verdwijnt het injectiepunt volledig; de server-side
 * `{{ get:theme }}`-vergelijkingen in de template en in `themeFilter`
 * blijven wel bestaan, want die belanden in een HTML-attribuutwaarde of een
 * klassevergelijking, niet in JS-code.
 */
export function articleFilter() {
    const initial = new URLSearchParams(window.location.search).get('theme')

    return {
        active: initial || 'all',

        matches(slug) {
            return this.active === 'all' || this.active === slug
        },

        select(slug) {
            this.active = slug || 'all'

            const url = new URL(window.location)

            if (this.active === 'all') {
                url.searchParams.delete('theme')
            } else {
                url.searchParams.set('theme', this.active)
            }

            window.history.replaceState({}, '', url)
        },
    }
}
```

In `resources/js/site.js`, vervang regel 4 en regel 9:

```js
import { articleFilter } from './components/article-filter'
```

```js
Alpine.data('articleFilter', articleFilter)
```

- [ ] **Step 5: Verwijder de projects-varianten**

```bash
rm resources/js/components/project-filter.js
rm resources/views/partials/rangeFilter.antlers.html
rm tests/Feature/Sections/RangeFilterTest.php
```

- [ ] **Step 6: Bouw de assets**

Run: `npm run build`

Zonder een verse build falen de HTTP-tests met een 500 op een ontbrekend Vite-manifest.

- [ ] **Step 7: Draai de test en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticlesOverviewPageTest`
Expected: PASS, 11 tests.

Als `test_a_theme_query_string_hides_the_others_without_dropping_them` op 4 verborgen kaarten struikelt, tel dan na hoeveel artikels aan `zonwering` hangen in Task 2: dat zijn er twee (`zip-screens-kiezen-voor-een-nieuwbouw` en `wanneer-vervang-je-je-rolluiken`).

- [ ] **Step 8: Commit**

```bash
git add -A resources/views/articles resources/js resources/views/partials tests/
git commit -m "feat: overzichtspagina /nieuws met themafilter"
```

---

## Task 7: Detailpagina met chips en redactor

**Files:**
- Create: `resources/views/partials/headers/article.antlers.html`
- Create: `resources/css/components/chip.css`
- Delete: `resources/css/components/badge.css`
- Modify: `resources/css/site.css`
- Modify: `resources/css/base/rich-text.css`
- Create: `resources/views/articles/show.antlers.html` (overschrijft het boilerplate-bestand)
- Test: `tests/Feature/Sections/ArticleHeaderTest.php`
- Test: `tests/Feature/Content/ArticleShowPageTest.php`

**Interfaces:**
- Consumes: de artikels uit Task 2.
- Produces: `data-header="article"` met twee `.chip`-elementen, en een detailpagina die `.article-body` rendert per tekstknoop en `partial:video` per videoknoop.

- [ ] **Step 1: Schrijf de falende headertest**

Maak `tests/Feature/Sections/ArticleHeaderTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Entry;

class ArticleHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/article" }}', [
            'title' => 'Een pergola die het hele jaar bruikbaar is',
            'text' => 'Lamellen, glazen schuifwanden en verwarming maken van een terras een buitenkamer.',
            'image' => '/img/article.jpg',
            'date' => '2026-07-21',
        ]);

        $this->assertStringContainsString('data-header="article"', $html);
        $this->assertStringContainsString('data-header-media', $html);

        // Pin de layering-workaround (zie header.css): zonder deze assertie
        // zou het vervangen van `.header-title`/`.header-intro` door bv.
        // `text-display` alle bestaande tests groen laten terwijl de tekst
        // stilletjes kleiner wordt.
        $this->assertStringContainsString('<h1 class="header-title max-w-[866px]">Een pergola die het hele jaar bruikbaar is</h1>', $html);
        $this->assertStringContainsString('<p class="header-intro max-w-[866px]">Lamellen, glazen schuifwanden en verwarming maken van een terras een buitenkamer.</p>', $html);
    }

    public function test_the_two_chips_of_a_real_article_are_the_theme_and_the_date(): void
    {
        config(['app.debug' => false]);

        // Array-fixtures dekken deze bug niet af: `theme` heeft `max_items: 1`
        // en augmenteert naar één term. Een pair scoopt daar niet in en laat
        // `{{ title }}` terugvallen op de artikeltitel.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('slug', 'een-pergola-die-het-hele-jaar-bruikbaar-is')
            ->first();

        $html = $this->render('{{ partial src="headers/article" }}', $article->toAugmentedArray());

        $this->assertStringContainsString('<span class="chip chip--dark">Terrasoverkapping</span>', $html);
        $this->assertStringContainsString('<span class="chip chip--light">21 juli 2026</span>', $html);
    }

    public function test_the_date_is_rendered_in_dutch(): void
    {
        config(['app.debug' => false]);

        // `isoFormat` en niet `format`: `format` geeft rauwe PHP-opmaak met
        // Engelse maandnamen. `isoFormat` gaat via Carbon, dat zijn locale
        // krijgt uit `app()->setLocale($site->lang())` in Statamics
        // Localize-middleware. Deze test verifieert die keten.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('slug', 'slimme-sturing-zonder-je-hele-huis-te-vernieuwen')
            ->first();

        $html = $this->render('{{ partial src="headers/article" }}', $article->toAugmentedArray());

        $this->assertStringContainsString('30 mei 2026', $html);
        $this->assertStringNotContainsString('May', $html);
    }

    public function test_omits_the_theme_chip_entirely_without_a_theme(): void
    {
        config(['app.debug' => false]);

        // Er mag geen lege chip achterblijven.
        $html = $this->render('{{ partial src="headers/article" }}', [
            'title' => 'Los artikel',
            'date' => '2026-07-21',
        ]);

        $this->assertStringNotContainsString('chip--dark', $html);
        $this->assertStringContainsString('chip--light', $html);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticleHeaderTest`
Expected: FAIL — de partial `headers/article` bestaat niet.

- [ ] **Step 3: Maak de header**

`resources/views/partials/headers/article.antlers.html`:

```antlers
{{#
    Artikelheader — afgeleid van de projectheader (Figma 301:3304), met de
    eyebrow vervangen door twee chips.

    Afgeleid (geen mobiel frame): het tekstblok volgt `container` in plaats
    van 866px vast, en het beeld houdt een ratio per breakpoint aan.

    `theme` is een terms-veld met `max_items: 1` en augmenteert dus naar één
    term, niet naar een collectie. Een pair (`{{ theme }}…{{ /theme }}`) scoopt
    daar niet in: de body rendert één keer met de bovenliggende scope, waardoor
    `{{ title }}` de artikeltitel teruggeeft in plaats van het thema.
    `{{ theme.title }}` doet wél een echte variabele-lookup.

    `isoFormat` en niet `format`: die laatste geeft Engelse maandnamen.
#}}
<section class="bg-white" data-header="article">
    <div class="container flex flex-col items-center gap-6 pt-10 text-center lg:pt-16">
        <div class="inline-flex items-center gap-2">
            {{ if themes }}<span class="chip chip--dark">{{ themes.title }}</span>{{ /if }}
            <span class="chip chip--light">{{ date | isoFormat('D MMMM YYYY') }}</span>
        </div>

        <h1 class="header-title max-w-[866px]">{{ title }}</h1>

        {{ if text }}
            <p class="header-intro max-w-[866px]">{{ text }}</p>
        {{ /if }}
    </div>

    {{ if image }}
        <div data-header-media class="container mt-10 lg:mt-16">
            {{ img :src="image" ratio="4/3" lg:ratio="2/1" max_width="2560" sizes="100vw" priority="true" class="w-full rounded-md" }}
        </div>
    {{ /if }}
</section>
```

- [ ] **Step 4: Maak de chip-utility**

`resources/css/components/chip.css`:

```css
/*
 * Dezelfde pilvorm als `.btn`, maar een maat kleiner: `--text-sm` (13 → 16px)
 * tegenover `--text-base` (16 → 20px). De kleuren spiegelen `btn--secondary`
 * en `btn--tertiary`, zodat chip en filterpil uit dezelfde familie komen.
 * Accentgeel blijft gereserveerd voor knoppen.
 */
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

```bash
rm resources/css/components/badge.css
```

In `resources/css/site.css`: vervang `@import './components/badge.css';` door `@import './components/chip.css';`.

- [ ] **Step 5: Draai de headertest en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticleHeaderTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Schrijf de falende detailtest**

Maak `tests/Feature/Content/ArticleShowPageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ArticleShowPageTest extends TestCase
{
    public function test_the_article_renders_with_its_header_and_chips(): void
    {
        $response = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw');

        $response->assertOk();
        $response->assertSee('data-header="article"', false);
        $response->assertSee('<span class="chip chip--dark">Zonwering</span>', false);
        $response->assertSee('28 juli 2026', false);
    }

    public function test_the_body_is_centered_and_carries_the_prose_utility(): void
    {
        $html = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw')->getContent();

        $this->assertStringContainsString('container-md', $html);
        $this->assertStringContainsString('class="article-body"', $html);

        // `.rich-text` is de minimale variant voor kaarten en sectiekoppen.
        // De artikeltekst hoort hem niet te gebruiken.
        $start = strpos($html, 'container-md');
        $end = strpos($html, '</section>', $start);
        $body = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('class="rich-text"', $body);
    }

    public function test_the_redactor_loops_instead_of_rendering_one_blob(): void
    {
        // Dit is precies wat in de boilerplate stilzwijgend kapot was: het
        // template loopte op type-namen terwijl de fieldset geen sets had, dus
        // Bard leverde één HTML-string en de lus gaf niets terug.
        $html = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw')->getContent();

        $this->assertStringContainsString('Buiten tegenhouden, niet binnen', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('<ul', $html);

        // Twee tekstknopen rond één videoknoop.
        $this->assertSame(2, substr_count($html, 'class="article-body"'));
    }

    public function test_a_video_node_renders_through_the_video_partial(): void
    {
        $html = $this->get('/nieuws/zip-screens-kiezen-voor-een-nieuwbouw')->getContent();

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('youtube.com/embed/', $html);
    }

    public function test_an_inline_image_survives_inside_the_text_node(): void
    {
        $html = $this->get('/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is')->getContent();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('Pergola met draaibare lamellen boven een terras', $html);
    }

    public function test_the_page_carries_no_page_builder(): void
    {
        $html = $this->get('/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is')->getContent();

        $this->assertStringNotContainsString('data-section="cta"', $html);
        $this->assertStringNotContainsString('data-section="text_image"', $html);
    }
}
```

- [ ] **Step 7: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticleShowPageTest`
Expected: FAIL — het boilerplate-template gebruikt `headers/default`, `container-sm` en `.rich-text`.

- [ ] **Step 8: Maak `.article-body`**

Voeg onderaan `resources/css/base/rich-text.css` toe:

```css
/*
 * De artikeltekst krijgt een eigen utility in plaats van een uitgebreide
 * `.rich-text`: die laatste is bewust minimaal en draait ook in `card`,
 * `sectionHeader` en `sections/text`, waar prose-marges en onderstreepte
 * links verkeerd vallen.
 */
@utility article-body {
    @apply prose max-w-none;

    p,
    li {
        @apply text-base;
    }

    h2,
    h3 {
        @apply font-semibold text-balance first-of-type:mt-0;
    }

    h2 {
        font-size: var(--text-3xl); /* 31 → 49px */
    }

    h3 {
        font-size: var(--text-xl); /* 20 → 31px */
    }

    a {
        @apply font-semibold underline underline-offset-4 hover:no-underline;
    }

    img {
        @apply block w-full rounded-md;
    }

    /* Een brede tabel mag de pagina niet meesleuren. */
    table {
        @apply block w-full overflow-x-auto;
    }
}
```

- [ ] **Step 9: Schrijf het detailtemplate**

`resources/views/articles/show.antlers.html`, volledig vervangen door:

```antlers
{{ partial:headers/article }}
<section class="section section--default">
    <div class="container-md">
        {{# `else` en geen tweede `if type == 'text'`: er zijn maar twee
            knooptypes, en zo kan er geen derde stilzwijgend wegvallen — precies
            de fout die het boilerplate-template maakte. #}}
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

- [ ] **Step 10: Bouw de assets en draai beide tests**

Run: `npm run build && vendor/bin/phpunit -d memory_limit=1G --filter='ArticleHeaderTest|ArticleShowPageTest'`
Expected: PASS, 10 tests.

- [ ] **Step 11: Commit**

```bash
git add -A resources/views/articles resources/views/partials/headers resources/css tests/
git commit -m "feat: artikeldetail met chips en een gerenderde redactor"
```

---

## Task 8: Page-builder-set `articles`

**Files:**
- Create: `resources/views/partials/sections/articles.antlers.html`
- Delete: `resources/views/partials/sections/projects.antlers.html`
- Modify: `resources/fieldsets/page_builder.yaml:320-341`
- Modify: `content/collections/pages/nl/page-builder.md:203-217`
- Modify: `content/collections/pages/nl/home.md:5-6`
- Modify: `tests/Feature/Content/PageBuilderPageTest.php:16,27,49`
- Delete: `tests/Feature/Sections/ProjectsSectionTest.php`
- Test: `tests/Feature/Sections/ArticlesSectionTest.php`

**Interfaces:**
- Consumes: `articleCard` (Task 3), de artikels (Task 2).
- Produces: het settype `articles` in de page builder, met de handles `overline`, `title`, `link` en `articles`. De partial rendert `data-section="articles"`.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/ArticlesSectionTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ArticlesSectionTest extends SectionTestCase
{
    public function test_renders_a_linked_card_per_article(): void
    {
        $html = $this->render('{{ partial src="sections/articles" }}', [
            'title' => 'Recent geschreven',
            'overline' => 'nieuws',
            'articles' => [
                [
                    'title' => 'Een pergola die het hele jaar bruikbaar is',
                    'url' => '/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is',
                    'themes' => ['title' => 'Terrasoverkapping', 'slug' => 'terrasoverkapping'],
                ],
                [
                    'title' => 'Zip-screens kiezen voor een nieuwbouw',
                    'url' => '/nieuws/zip-screens-kiezen-voor-een-nieuwbouw',
                    'themes' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-section="articles"', $html);
        $this->assertStringContainsString('data-slider-from="xl"', $html);
        $this->assertSame(2, substr_count($html, 'article-card '));
        $this->assertStringContainsString('href="/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is"', $html);
        $this->assertStringContainsString('Zonwering', $html);
    }

    public function test_it_does_not_fall_back_to_the_page_intro(): void
    {
        // `text` zit niet in deze set. Zonder expliciete lege waarde valt
        // `sectionHeader` terug op de velden van de pagina zelf; op /home zette
        // dat ooit de hero-intro boven de slider.
        $html = $this->render('{{ partial src="sections/articles" }}', [
            'title' => 'Recent geschreven',
            'text' => 'De intro van de pagina zelf',
            'articles' => [],
        ]);

        $this->assertStringNotContainsString('De intro van de pagina zelf', $html);
    }
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=ArticlesSectionTest`
Expected: FAIL — de partial `sections/articles` bestaat niet.

- [ ] **Step 3: Maak de partial**

`resources/views/partials/sections/articles.antlers.html`:

```antlers
<section class="section section--default" data-section="articles">
    <div class="container">
        <div class="section-y-gap">
            {{#
                `text` staat niet in deze set, dus zonder expliciete lege waarde valt `sectionHeader` terug op de velden van de
                pagina zelf. Op /home zette dat de hero-intro en de knop "Ontdek ons aanbod" boven de aanbodslider.
            #}}
            {{ partial:sectionHeader link_col="true" text="" }}
            {{ if articles }}
                {{ partial:slider per_view="1.15,md:2.15" space="24,md:32,lg:40" from="xl" bleed="true" }}
                    {{ articles }}
                        <div class="swiper-slide">
                            {{ partial:articleCard }}
                        </div>
                    {{ /articles }}
                {{ /partial:slider }}
            {{ /if }}
        </div>
    </div>
</section>
```

```bash
rm resources/views/partials/sections/projects.antlers.html
rm tests/Feature/Sections/ProjectsSectionTest.php
```

- [ ] **Step 4: Werk de fieldset bij**

In `resources/fieldsets/page_builder.yaml`, vervang het `projects`-blok (rond regel 320) door:

```yaml
            articles:
              display: Articles
              fields:
                -
                  handle: overline
                  field:
                    type: text
                    display: Overline
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                -
                  import: link
                -
                  handle: articles
                  field:
                    type: entries
                    collections:
                      - articles
                    display: Articles
```

- [ ] **Step 5: Werk de content bij**

In `content/collections/pages/nl/page-builder.md`, vervang het `sec05`-blok door:

```yaml
  -
    id: sec05
    type: articles
    overline: nieuws
    title: 'Recent geschreven'
    link:
      -
        id: gzejMI7X
        type: entry
        entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
        label: 'Bekijk alle artikels'
        new_tab: false
    articles:
      - a4e8b1c7-0001-4d3f-9a2b-5c6d7e8f9a01
      - a4e8b1c7-0002-4d3f-9a2b-5c6d7e8f9a02
      - a4e8b1c7-0003-4d3f-9a2b-5c6d7e8f9a03
    enabled: true
```

De oude `entry: abe3f0e6-93bd-4c99-9389-393613952117` wees naar de `cases`-boilerplatepagina, niet naar realisaties; hij wordt vervangen door de nieuwe nieuwspagina.

In `content/collections/pages/nl/home.md`, verwijder de dode sleutel:

```yaml
home_projects:
  highlight: color
```

De home-blueprint kent dat veld niet en `home.antlers.html` rendert het nergens.

- [ ] **Step 6: Werk `PageBuilderPageTest` bij**

Drie plekken, alle drie een pure hernoeming:

- regel 16: in de `foreach`-lijst wordt `'projects'` → `'articles'`, zodat de test `data-section="articles"` zoekt
- regel 27: `$response->assertSee('Recent gerealiseerd', false); // projects title` → `$response->assertSee('Recent geschreven', false); // articles title`
- regel 49: het commentaar `// ranges 9 + cards 4 + projects 3 + image_gallery 6` → `projects` wordt `articles`

De assertie onder regel 49 telt `swiper-slide` en blijft op `22`: de set toont nog steeds drie kaarten. Er is in dit bestand geen assertie die op `project-card` telt, dus verder verandert er niets.

- [ ] **Step 7: Draai de betrokken tests**

Run: `npm run build && vendor/bin/phpunit -d memory_limit=1G --filter='ArticlesSectionTest|PageBuilderPageTest'`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A resources/views/partials/sections resources/fieldsets content/collections/pages tests/
git commit -m "feat: page-builder-set articles vervangt projects"
```

---

## Task 9: Projects opruimen

**Files:**
- Delete: `content/collections/projects.yaml`
- Delete: `content/collections/projects/` (zes entries)
- Delete: `resources/blueprints/collections/projects/projects.yaml`
- Delete: `resources/views/projects/show.antlers.html`
- Delete: `resources/views/partials/projectCard.antlers.html`
- Delete: `resources/views/partials/headers/project.antlers.html`
- Delete: `resources/css/components/project-card.css`
- Delete: `app/Tags/ProjectRanges.php`
- Delete: `tests/Feature/Sections/ProjectCardTest.php`
- Delete: `tests/Feature/Sections/ProjectHeaderTest.php`
- Delete: `tests/Feature/Sections/ProjectRangesTagTest.php`
- Modify: `resources/css/site.css`
- Modify: `tests/Feature/Content/CatalogContentTest.php:21-38`

**Interfaces:**
- Consumes: niets. Alles wat de projects-kant leverde, is in Task 3 tot 8 vervangen.
- Produces: een codebase zonder `projects`.

- [ ] **Step 1: Schrijf de falende test**

Vervang in `tests/Feature/Content/CatalogContentTest.php` de methode `test_six_projects_exist_and_reference_a_range` door:

```php
    public function test_the_projects_collection_is_fully_gone(): void
    {
        // De realisaties zijn vervangen door /nieuws. Deze assertie vangt een
        // half opgeruimde staat: een achtergebleven collectie zou stilletjes
        // blijven routeren op /realisaties/{slug}.
        $this->assertFileDoesNotExist(base_path('content/collections/projects.yaml'));
        $this->assertDirectoryDoesNotExist(base_path('content/collections/projects'));
        $this->assertFileDoesNotExist(app_path('Tags/ProjectRanges.php'));
        $this->assertFileDoesNotExist(resource_path('views/partials/projectCard.antlers.html'));
        $this->assertFileDoesNotExist(resource_path('views/partials/headers/project.antlers.html'));
        $this->assertFileDoesNotExist(resource_path('css/components/project-card.css'));

        $this->assertSame(0, Entry::query()->where('collection', 'projects')->count());
    }
```

De `use Statamic\Facades\Entry;` bovenaan blijft staan; `test_every_product_exists_with_an_image` gebruikt hem ook.

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=CatalogContentTest`
Expected: FAIL — de projects-bestanden staan er nog.

- [ ] **Step 3: Verwijder alles**

```bash
rm content/collections/projects.yaml
rm -r content/collections/projects
rm -r resources/blueprints/collections/projects
rm -r resources/views/projects
rm resources/views/partials/projectCard.antlers.html
rm resources/views/partials/headers/project.antlers.html
rm resources/css/components/project-card.css
rm app/Tags/ProjectRanges.php
rm tests/Feature/Sections/ProjectCardTest.php
rm tests/Feature/Sections/ProjectHeaderTest.php
rm tests/Feature/Sections/ProjectRangesTagTest.php
```

In `resources/css/site.css`: verwijder de regel `@import './components/project-card.css';`.

- [ ] **Step 4: Draai de test en zie hem slagen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter=CatalogContentTest`
Expected: PASS.

- [ ] **Step 5: Controleer dat er geen verwijzingen achterblijven**

Run: `grep -rn "projectCard\|rangeFilter\|project_ranges\|projectFilter\|project-card\|headers/project\|realisaties" resources/ app/ content/ tests/`
Expected: geen treffers. Vind je er nog, werk ze bij voor je verdergaat.

- [ ] **Step 6: Pint en de volledige suite**

```bash
vendor/bin/pint --dirty --format agent
npm run build
vendor/bin/phpunit -d memory_limit=1G
```

Expected: alle tests groen.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor: projects-collectie en haar views verwijderd"
```

---

## Zelfreview

**Dekking van de spec.** Elke sectie van `docs/superpowers/specs/2026-08-02-nieuws-blog-design.md` heeft een taak: §1 contentmodel → Task 1, 2 en 5; §2 overzicht → Task 3, 4 en 6; §3 detail → Task 7; §4 ripple → Task 5, 8 en 9; §5 tests → verspreid over alle taken.

**Twee dingen die het plan strakker maakt dan de spec:**

- De spec noemt `{{ svg src="icons/regular/arrow-right" }}` niet expliciet, maar `projectCard` gebruikt die verouderde vorm. Task 3 zet hem om naar `{{ icon src="arrow-right" }}`, conform CLAUDE.md.
- De volgorde is zo gekozen dat de suite tussen elke taak groen is. Daarom verdwijnt `projects` pas in Task 9 en niet bij de eerste beste gelegenheid: `ProjectHeaderTest` en `CatalogContentTest` leunen erop tot dan.

**Eén punt om bij de uitvoering op te letten.** `test_the_two_chips_of_a_real_article_are_the_theme_and_the_date` verwacht `21 juli 2026`. Dat werkt alleen als Carbon de Nederlandse locale krijgt. `SectionTestCase` doet geen HTTP-request, dus Statamics `Localize`-middleware draait daar niet. Blijkt de maandnaam Engels, verplaats die assertie dan naar `ArticleShowPageTest` (die wél via HTTP gaat) en laat `ArticleHeaderTest` alleen de chipstructuur controleren.
