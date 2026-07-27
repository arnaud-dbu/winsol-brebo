# Navigatie Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De header krijgt de vijf navigatie-items uit Figma, een offerte-knop, een taalpill, en een mega menu onder "Aanbod" dat de ranges per taxonomie-categorie toont.

**Architecture:** De navigatiestructuur blijft de bron van waarheid; een nieuw `mega_menu`-toggleveld op het navigatie-item bepaalt welk item het paneel opent. De `<header>` wordt in twee lagen gesplitst — een volle-breedte buitenlaag die de positioneringscontext van het paneel is, en de bestaande `container` daarbinnen. Het paneel is een broer van die container, buiten de `nav:main`-lus, want het is breder dan het item dat het opent. Eén Alpine-`x-data` op de `<header>` stuurt beide.

**Tech Stack:** Statamic 6 (Antlers-templates, `nav`/`taxonomy`/`collection`-tags), Tailwind 4 (`@theme`-tokens in `resources/css/site.css`), Alpine 3 met de `@alpinejs/collapse`-plugin (al geregistreerd in `resources/js/site.js`), PHPUnit.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-27-navigatie-design.md`. Bij twijfel wint de spec.
- Tests draaien met `vendor/bin/phpunit -d memory_limit=1G`. **Nooit** `php artisan test` — die valt om op geheugen.
- Geen nieuw CSS-bestand. Opmaak is Tailwind in de partials; hergebruik `overline`, `.btn--accent` en de `container`-utility.
- Nederlandse teksten en commentaar; Engelse handles in blueprints en code.
- Alle zichtbare chrome-teksten komen uit `lang/nl/site.php`, nooit hardcoded in een template. `lang/en/site.php` bestaat niet en wordt niet aangemaakt.
- Tailwind pikt alleen letterlijke klassetekst op. Nooit een klasse uit een geïnterpoleerde string samenstellen.
- Commit na elke taak. De branch is `navigation`.

---

## File Structure

| Bestand | Verantwoordelijkheid |
|---|---|
| `content/trees/navigation/main.yaml` | de vijf items en hun volgorde |
| `resources/blueprints/navigation/main.yaml` | de velden van een navigatie-item, inclusief de nieuwe `mega_menu`-toggle |
| `resources/views/partials/navigation.antlers.html` | de header: twee lagen, de itemlus, de rechterknoppen, en de aanroep van het paneel |
| `resources/views/partials/megaMenu.antlers.html` | het paneel: strook, kaart, kolommen per categorie, CTA-blok |
| `resources/views/partials/languagePill.antlers.html` | de niet-interactieve taalpill, gebruikt op desktop én in het mobiele paneel |
| `resources/views/partials/mobileNavigation.antlers.html` | het mobiele paneel; krijgt de offerte-knop en de taalpill erbij |
| `lang/nl/site.php` | de vijf nieuwe labels |
| `tests/Feature/Sections/NavigationTest.php` | de header: structuur, knoppen, toegankelijkheid |
| `tests/Feature/Sections/MegaMenuTest.php` | het paneel: categorieën, ranges, CTA, toegankelijkheid |

`languagePill.antlers.html` staat niet in de bestandenlijst van de spec. Hij komt erbij omdat dezelfde pill op twee plekken staat (desktop-header en mobiel paneel) en de markup twee keer uitschrijven de eerste plek is waar ze uiteen gaan lopen. Verder niets afwijkends.

---

### Task 1: De navigatiestructuur en de `mega_menu`-vlag

Vijf items in de Figma-volgorde, en een veld waarmee een item zich als mega-menu-opener kan aanmerken. De template gebruikt de vlag in deze taak nog niet — dat is Task 2. Wat hier af moet: de boom klopt, en de test bewijst dat de titels uit de boom komen en niet uit de template.

**Files:**
- Modify: `content/trees/navigation/main.yaml`
- Modify: `resources/blueprints/navigation/main.yaml`
- Test: `tests/Feature/Sections/NavigationTest.php:5-27`

**Interfaces:**
- Consumes: niets.
- Produces: het veld `mega_menu` (boolean) op elk navigatie-item, in de template te lezen als `{{ if mega_menu }}` binnen `{{ nav:main }}`. Precies één item heeft hem op `true`: "Aanbod".

- [ ] **Step 1: Werk de twee tests bij die niet meer kloppen**

Vervang in `tests/Feature/Sections/NavigationTest.php` de eerste twee testmethodes (`test_menu_is_driven_by_the_main_navigation_structure` en `test_menu_does_not_hardcode_a_fake_aanbod_dropdown`) door onderstaande twee. De drie toegankelijkheidstests eronder blijven ongewijzigd staan.

```php
    public function test_menu_is_driven_by_the_main_navigation_structure(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Deze titels komen uit content/trees/navigation/main.yaml, niet uit de
        // template — bewijst dat het menu geen hardcoded lijst links is.
        $this->assertStringContainsString('Aanbod', $html);
        $this->assertStringContainsString('Realisaties', $html);
        $this->assertStringContainsString('Service', $html);
        $this->assertStringContainsString('Over ons', $html);
        $this->assertStringContainsString('Contact', $html);
    }

    public function test_menu_items_follow_the_order_of_the_structure(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De volgorde uit Figma 332:3244. `strpos` op de eerste treffer volstaat:
        // de items staan één keer in het desktopmenu, in boomvolgorde.
        $positions = [
            'Aanbod' => strpos($html, 'Aanbod'),
            'Realisaties' => strpos($html, 'Realisaties'),
            'Service' => strpos($html, 'Service'),
            'Over ons' => strpos($html, 'Over ons'),
            'Contact' => strpos($html, 'Contact'),
        ];

        foreach ($positions as $title => $position) {
            $this->assertNotFalse($position, "'{$title}' staat niet in het menu.");
        }

        $sorted = $positions;
        asort($sorted);

        $this->assertSame(array_keys($positions), array_keys($sorted));
    }
```

- [ ] **Step 2: Draai de tests en kijk ze falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter NavigationTest`
Expected: FAIL — "Aanbod", "Service" en "Realisaties" staan nog niet in de boom.

- [ ] **Step 3: Schrijf de nieuwe boom**

Vervang de volledige inhoud van `content/trees/navigation/main.yaml`:

```yaml
tree:
  -
    id: 8a1c4f20-5d3b-4e77-9c12-6b0e8f2a4d31
    title: Aanbod
    data:
      link_type: entry
      entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a02
      mega_menu: true
  -
    id: 3b6e9620-9efd-4402-8b93-4a4d259d909d
    title: Realisaties
    data:
      link_type: entry
      entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
  -
    id: 5c7d1e93-2a48-4b06-8f5a-9d3c1b7e0a62
    title: Service
    data:
      link_type: entry
      entry: c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a04
  -
    id: 10f29e7f-517c-4c93-b268-328d68f100ae
    title: 'Over ons'
    data:
      link_type: entry
      entry: 559b2b7e-a511-409c-9eec-51d314cec648
  -
    id: 0f42aa70-0242-4631-86ec-0bc16915f316
    title: Contact
    data:
      link_type: entry
      entry: f0ee3161-1534-4986-9ef1-a92fccfba619
```

Let op het item "Realisaties": dat is het bestaande item dat "Projecten" heette en naar `pages/cases.md` (`abe3f0e6-…`) wees. Het houdt zijn eigen `id` maar krijgt een nieuwe titel én een nieuwe entry — `pages/realisaties.md`, de echte overzichtspagina (blueprint `projects_overview`, template `projects-overview`).

- [ ] **Step 4: Voeg de toggle toe aan het blueprint**

In `resources/blueprints/navigation/main.yaml`, achter het veld `url`, binnen dezelfde `fields:`-lijst:

```yaml
          -
            handle: mega_menu
            field:
              type: toggle
              display: 'Mega menu'
              instructions: 'Toont het volledige aanbod per categorie onder dit item. Alleen zinvol op het item dat naar de aanbod-pagina wijst.'
```

- [ ] **Step 5: Leeg de Stache en draai de tests**

Run: `php please stache:clear && vendor/bin/phpunit -d memory_limit=1G --filter NavigationTest`
Expected: PASS — vijf tests groen.

De Stache cachet de navigatieboom; zonder legen leest de test de oude drie items.

- [ ] **Step 6: Commit**

```bash
git add content/trees/navigation/main.yaml resources/blueprints/navigation/main.yaml tests/Feature/Sections/NavigationTest.php
git commit -m "feat(nav): zet de vijf items uit het ontwerp in de structuur"
```

---

### Task 2: Het mega menu

De header wordt in twee lagen gesplitst, de `children`-dropdown maakt plaats voor het paneel, en het paneel zelf komt erbij. Dit is één taak en geen twee: het paneel kan niet bestaan zonder de buitenlaag die het positioneert, en de buitenlaag heeft in zijn eentje geen zichtbaar resultaat.

**Files:**
- Create: `resources/views/partials/megaMenu.antlers.html`
- Modify: `resources/views/partials/navigation.antlers.html`
- Modify: `resources/views/partials/mobileNavigation.antlers.html:1` (alleen het breekpunt)
- Modify: `lang/nl/site.php`
- Test: `tests/Feature/Sections/MegaMenuTest.php`

**Interfaces:**
- Consumes: `mega_menu` (boolean) op het navigatie-item, uit Task 1.
- Produces: het paneel met `id="mega-menu-panel"`; de knop die het opent draagt `aria-controls="mega-menu-panel"`. De Alpine-scope heet `open` en staat op de `<header>` — Task 4 raakt hem niet aan.

- [ ] **Step 1: Schrijf de falende test**

Nieuw bestand `tests/Feature/Sections/MegaMenuTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class MegaMenuTest extends SectionTestCase
{
    public function test_categories_appear_in_the_order_of_their_order_field(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De drie termen uit content/taxonomies/range_categories/, gesorteerd op
        // hun `order`-veld (1, 2, 3). `&` komt door `| entities` als `&amp;`.
        $voor = strpos($html, 'Voor je woning');
        $rondom = strpos($html, 'Rondom je woning');
        $slim = strpos($html, 'Slim &amp; comfort');

        $this->assertNotFalse($voor);
        $this->assertNotFalse($rondom);
        $this->assertNotFalse($slim);
        $this->assertLessThan($rondom, $voor);
        $this->assertLessThan($slim, $rondom);
    }

    public function test_every_range_appears_with_its_short_description(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Titel plus omschrijving, zodat een kolom die alleen titels rendert
        // hier alsnog op valt. De omschrijvingen staan in
        // content/collections/ranges/*.md.
        $this->assertStringContainsString('Ramen en deuren', $html);
        $this->assertStringContainsString('Ramen, voordeuren en schuiframen op maat.', $html);
        $this->assertStringContainsString('Rolluiken', $html);
        $this->assertStringContainsString('Verduistering, isolatie en inbraakwering.', $html);
        $this->assertStringContainsString('Somfy Smart Home', $html);
        $this->assertStringContainsString('Bedien zonwering, rolluiken en verlichting via app.', $html);
    }

    public function test_ranges_within_a_category_follow_their_order_field(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // "Voor je woning": order 1, 2, 3, 4.
        $ramen = strpos($html, 'Ramen en deuren');
        $stalen = strpos($html, 'Stalen binnendeuren');
        $velux = strpos($html, 'VELUX dakramen');
        $airco = strpos($html, 'Airco');

        $this->assertLessThan($stalen, $ramen);
        $this->assertLessThan($velux, $stalen);
        $this->assertLessThan($airco, $velux);
    }

    public function test_each_range_links_to_its_own_page(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // De route van de ranges-collectie is /aanbod/{slug}.
        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
        $this->assertStringContainsString('href="/aanbod/velux"', $html);
        $this->assertStringContainsString('href="/aanbod/somfy-smart-home"', $html);
    }

    public function test_panel_carries_a_link_to_the_full_range_overview(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('Niet zeker welke oplossing past?', $html);
        $this->assertStringContainsString('Volledig aanbod', $html);
        $this->assertStringContainsString('href="/aanbod"', $html);
    }

    public function test_only_the_flagged_item_opens_a_panel(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Vijf items, precies één knop die een paneel bestuurt. Dit is het
        // bewijs dat het paneel uit de `mega_menu`-vlag komt en niet uit
        // markup die op elk item is geplakt.
        $this->assertSame(1, substr_count($html, 'aria-controls="mega-menu-panel"'));
        $this->assertSame(1, substr_count($html, 'id="mega-menu-panel"'));
    }

    public function test_toggle_reports_its_state_to_assistive_technology(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString(':aria-expanded="open.toString()"', $html);
    }
}
```

- [ ] **Step 2: Draai de test en kijk hem falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter MegaMenuTest`
Expected: FAIL — er is geen paneel, dus "Voor je woning" staat nergens.

- [ ] **Step 3: Voeg de twee labels van het CTA-blok toe**

In `lang/nl/site.php`, achter `'nav_label'`:

```php
    'mega_menu_cta_title' => 'Niet zeker welke oplossing past?',
    'mega_menu_cta_label' => 'Volledig aanbod',
```

- [ ] **Step 4: Schrijf de mega-menu-partial**

Nieuw bestand `resources/views/partials/megaMenu.antlers.html`:

```antlers
{{#
    Het paneel onder het navigatie-item met `mega_menu` (Figma 366:5017).

    Twee lagen. De strook is zo breed als het venster en heeft geen eigen
    achtergrond: hij is `pointer-events-none`, zodat een klik náást de kaart
    doorvalt naar de pagina en de `@click.outside` op de `<header>` hem sluit.
    De kaart daarbinnen is wél klikbaar en is maximaal 85rem (1360px, de
    breedte uit het ontwerp op een venster van 1744px).

    De kaart heeft een schaduw die Figma niet tekent. Zonder die schaduw heeft
    een witte kaart op een witte pagina geen rand — in Figma leest hij als
    kaart doordat het canvas eromheen grijs is, en dat canvas bestaat op de
    site niet.

    De query is dezelfde als in resources/views/range-overview.antlers.html.
    `range_categories:overlaps` filtert op array-membership; `:contains` zou
    een slug matchen die toevallig in een andere zit. Sorteren op `order`
    houdt beide plekken in de Figma-volgorde.

    Het CTA-blok hangt onderaan de laatste categoriekolom, zoals in het
    ontwerp: die kolom is `justify-between`, dus de categorie staat bovenaan
    en het blok onderaan. `last` wordt bewaard in `is_last_category` vóór de
    `collection`-tag, zodat het onmiskenbaar de laatste catégorie is en niet
    de laatste range.

    Twee kolommen tot `xl`, daarboven drie. Het ontwerp geeft de derde kolom
    407px vaste breedte; hier is dat een gelijke `1fr`. Op 1744px scheelt dat
    een vijftigtal pixels, en het scheelt een raster dat onder 1600px in
    elkaar klapt.

    De tekstgroottes zijn vaste pixelwaarden en geen `text-*`-tokens. Die
    tokens zijn vloeiende clamp()-waarden voor paginacontent; een menu-item
    van 14px dat meeschaalt naar 18px is niet wat het ontwerp toont.
#}}
<div
    id="mega-menu-panel"
    x-show="open"
    x-cloak
    x-collapse
    class="pointer-events-none absolute top-full left-0 z-40 w-full border-b border-black/25">
    <div class="container py-4">
        <div class="pointer-events-auto mx-auto grid w-full max-w-[85rem] grid-cols-2 items-stretch gap-10 rounded-md bg-white p-6 shadow-lg xl:grid-cols-3 xl:gap-20 xl:p-10">
            {{ taxonomy:range_categories sort="order:asc" }}
                {{ is_last_category = last }}
                {{ collection:ranges range_categories:overlaps="{slug}" sort="order:asc" as="ranges_in_category" }}
                    {{ if ranges_in_category }}
                        <div class="flex flex-col justify-between gap-10">
                            <div class="flex flex-col gap-6">
                                {{ partial:overline :label="title | entities" }}

                                <ul class="flex flex-col gap-6">
                                    {{ ranges_in_category }}
                                        <li>
                                            <a href="{{ url }}" class="flex items-start gap-3 transition-opacity hover:opacity-70">
                                                {{ if image }}
                                                    <span class="flex size-[46px] shrink-0 items-center justify-center rounded-sm bg-light p-1.5">
                                                        {{ img :src="image" max_width="92" sizes="46px" class="h-auto max-h-full w-auto max-w-full object-contain" }}
                                                    </span>
                                                {{ /if }}

                                                <span class="flex flex-col gap-1">
                                                    <span class="text-[20px] leading-[1.1] font-semibold text-black">{{ title }}</span>
                                                    {{ if short_description }}
                                                        <span class="text-[14px] leading-[1.5] text-black/75">{{ short_description }}</span>
                                                    {{ /if }}
                                                </span>
                                            </a>
                                        </li>
                                    {{ /ranges_in_category }}
                                </ul>
                            </div>

                            {{ if is_last_category }}
                                <a href="/aanbod" class="flex flex-col items-start gap-4 rounded-md bg-light px-8 py-6 transition-opacity hover:opacity-70">
                                    <span class="text-[20px] leading-[1.5] font-semibold text-black">{{ trans:site.mega_menu_cta_title }}</span>
                                    <span class="inline-flex w-fit items-center justify-center rounded-full bg-black px-5 py-3.5 text-[16px] leading-[1.5] font-semibold tracking-[0.02em] text-white">
                                        {{ trans:site.mega_menu_cta_label }}
                                    </span>
                                </a>
                            {{ /if }}
                        </div>
                    {{ /if }}
                {{ /collection:ranges }}
            {{ /taxonomy:range_categories }}
        </div>
    </div>
</div>
```

- [ ] **Step 5: Herschrijf de header**

Vervang de volledige inhoud van `resources/views/partials/navigation.antlers.html`. De offerte-knop en de taalpill komen in Task 3; hier staat alleen wat het paneel nodig heeft.

```antlers
{{#
    De header. Twee lagen: de `<header>` staat op volle breedte en is de
    positioneringscontext van het mega-menu-paneel, dat een strook over de
    hele vensterbreedte is. De `container` daarbinnen houdt logo, menu en
    knoppen op de sitebreedte.

    Die container is óók `relative`, en dat is niet overbodig: de hamburger
    van partials/mobileNavigation.antlers.html is absoluut gepositioneerd
    (zie resources/css/components/hamburger.css) en moet aan de containerrand
    hangen. Zonder `relative` hier zou hij naar de vensterrand springen.

    `x-data` staat op de `<header>` en niet op het `<li>` van het
    mega-menu-item: het paneel is breder dan dat item en staat daarom buiten
    de `nav:main`-lus. Er is precies één item met `mega_menu`, dus één
    `open`-boolean volstaat — de test daarop staat in MegaMenuTest.

    `@resize.window` sluit het paneel bij een sprong van desktop naar mobiel.
    Het menu is daaronder `display: none`, dus de knop is dan onbereikbaar en
    een open paneel zou blijven hangen zonder iets om het mee te sluiten.

    De omslag naar de desktopvariant staat op `lg` en niet op `md`. Dat kon
    met drie items en geen knoppen; met vijf items plus een offerte-knop plus
    een taalpill niet meer. Ruwe telling op 768px: logo ±120px, vijf items met
    `gap-8` ±470px, knop ±180px, pill ±80px — ruim 850px in een container van
    688px.
#}}
<header
    class="relative"
    x-data="{ open: false }"
    @click.outside="open = false"
    @keyup.escape="open = false"
    @resize.window="open = false">
    <div
        class="relative container flex items-center justify-between border-b border-black/12 py-4 lg:border-b-0 lg:py-6">
        <a href="/" class="flex flex-col items-start gap-2">
            <span class="sr-only">Home Link</span>
            {{ svg src="logo" class="h-8 md:h-10 2xl:h-12 w-auto" }}
        </a>

        <nav class="hidden lg:block" aria-label="{{ trans:site.nav_label }}">
            <ul class="flex items-center gap-8 xl:gap-10">
                {{ nav:main }}
                    <li>
                        {{ if mega_menu }}
                            <button
                                type="button"
                                class="flex items-center gap-2 text-base text-black transition-opacity hover:opacity-70"
                                aria-controls="mega-menu-panel"
                                :aria-expanded="open.toString()"
                                @click="open = !open">
                                {{ title }}
                                <svg
                                    class="size-3.5 shrink-0 transition-transform duration-200"
                                    :class="{ 'rotate-180': open }"
                                    viewBox="0 0 14 14"
                                    fill="none"
                                    aria-hidden="true">
                                    <path
                                        d="M2.5 4.5L7 9.5L11.5 4.5"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </button>
                        {{ else }}
                            <a
                                href="{{ entry.url }}"
                                class="text-base text-black transition-opacity hover:opacity-70 aria-[current=page]:opacity-60"
                                {{ if entry.url == current_url }}aria-current="page"{{ /if }}>
                                {{ title }}
                            </a>
                        {{ /if }}
                    </li>
                {{ /nav:main }}
            </ul>
        </nav>

        {{ partial:mobileNavigation }}
    </div>

    {{ nav:main }}
        {{ if mega_menu }}
            {{ partial:megaMenu }}
        {{ /if }}
    {{ /nav:main }}
</header>
```

De `children`-dropdown is hier weg. De boom is plat, het ontwerp kent maar één dropdown-patroon, en twee mechanismen in dezelfde `<li>` zouden bij een item met zowel kinderen als `mega_menu` twee panelen openen.

- [ ] **Step 6: Laat de hamburger met het breekpunt meeschuiven**

Het menu verschijnt nu pas op `lg`; de hamburger moet dus ook pas op `lg` verdwijnen, anders is er tussen 768px en 1024px géén navigatie. In `resources/views/partials/mobileNavigation.antlers.html`, de eerste regel:

```antlers
<div class="lg:hidden" data-mobile-nav>
```

Dat is de enige wijziging in dat bestand in deze taak. Laat de overige `md:`-klassen in de andere partials met rust.

- [ ] **Step 7: Draai beide testklassen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "NavigationTest|MegaMenuTest"`
Expected: PASS — twaalf tests groen.

- [ ] **Step 8: Bouw de assets en controleer het paneel in de browser**

Run: `npm run build`

Open de site op een breed venster, klik op "Aanbod". Controleer: het paneel schuift open, de drie kolommen staan naast elkaar, klikken náást de kaart sluit het, `Escape` sluit het, en de caret draait. Sleep daarna naar 900px: de hamburger verschijnt, het menu verdwijnt, en er blijft geen open paneel hangen.

- [ ] **Step 9: Commit**

```bash
git add resources/views/partials/megaMenu.antlers.html resources/views/partials/navigation.antlers.html resources/views/partials/mobileNavigation.antlers.html lang/nl/site.php tests/Feature/Sections/MegaMenuTest.php
git commit -m "feat(nav): voeg het mega menu onder Aanbod toe

Het breekpunt van de header schuift mee van md naar lg: met vijf items
loopt de rij op 768px over, en drie kolommen passen daar sowieso niet."
```

---

### Task 3: De offerte-knop en de taalpill

**Files:**
- Create: `resources/views/partials/languagePill.antlers.html`
- Modify: `resources/views/partials/navigation.antlers.html`
- Modify: `lang/nl/site.php`
- Test: `tests/Feature/Sections/NavigationTest.php`

**Interfaces:**
- Consumes: `.btn--accent` uit `resources/css/components/button.css`.
- Produces: `{{ partial:languagePill }}`, zonder argumenten. Task 4 roept hem aan.

- [ ] **Step 1: Schrijf de falende tests**

Voeg onderaan `tests/Feature/Sections/NavigationTest.php` toe, binnen de klasse:

```php
    public function test_header_carries_a_quote_button_from_the_lang_file(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        $this->assertStringContainsString('Gratis offerte', $html);
        $this->assertStringContainsString('href="/offerte"', $html);
    }

    public function test_language_pill_is_labelled_but_not_interactive(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Eén site, dus er valt niets te kiezen: de pill toont de taal maar
        // opent niets. Een knop met aria-expanded zou een paneel beloven dat
        // niet bestaat. Er zijn er precies twee in de header — de knop van
        // het mega menu en de hamburger — en de pill voegt er geen derde aan
        // toe.
        $this->assertStringContainsString('Taal: Nederlands', $html);
        $this->assertStringContainsString('>NL<', $html);
        $this->assertSame(2, substr_count($html, 'aria-expanded'));
    }
```

- [ ] **Step 2: Draai de tests en kijk ze falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter NavigationTest`
Expected: FAIL — "Gratis offerte" staat nergens.

- [ ] **Step 3: Voeg de drie labels toe**

In `lang/nl/site.php`, bij de andere `nav_`-sleutels:

```php
    'nav_quote' => 'Gratis offerte',
    'nav_language' => 'Taal: Nederlands',
    'nav_language_short' => 'NL',
```

- [ ] **Step 4: Schrijf de taalpill-partial**

Nieuw bestand `resources/views/partials/languagePill.antlers.html`:

```antlers
{{#
    De taalpill uit Figma 332:3237. Bewust géén knop: `multisite` staat op
    `false` in config/statamic/system.php en er is één site, dus er valt niets
    te kiezen. Een `<button>` met `aria-expanded` zou een paneel beloven dat
    niet bestaat; de caret is decoratie en staat er alleen omdat het ontwerp
    hem tekent.

    Zodra er een tweede site is, wordt dit hetzelfde `x-data`-patroon als het
    mega menu, gevoed door `Site::all()`.
#}}
<p class="flex items-center gap-0.5 rounded-full border border-black/25 px-[18px] py-5 text-base leading-none font-semibold text-black">
    <span class="sr-only">{{ trans:site.nav_language }}</span>
    <span aria-hidden="true">{{ trans:site.nav_language_short }}</span>
    <svg class="size-3.5 shrink-0" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <path
            d="M2.5 4.5L7 9.5L11.5 4.5"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round" />
    </svg>
</p>
```

- [ ] **Step 5: Hang de knoppen in de header**

In `resources/views/partials/navigation.antlers.html`, tussen het sluitende `</nav>` en `{{ partial:mobileNavigation }}`:

```antlers
        <div class="hidden items-center gap-2.5 lg:flex">
            <a href="/offerte" class="btn btn--accent">{{ trans:site.nav_quote }}</a>
            {{ partial:languagePill }}
        </div>
```

`/offerte` is hardcoded. Die pagina wordt parallel gebouwd volgens `docs/superpowers/specs/2026-07-27-offerte-page-design.md`, met exact die route. Landt ze niet, dan is dit één regel om naar `/contact` om te hangen.

- [ ] **Step 6: Draai de tests**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter "NavigationTest|MegaMenuTest"`
Expected: PASS — veertien tests groen.

- [ ] **Step 7: Bouw de assets en controleer de balk**

Run: `npm run build`

Controleer op 1024px en breder: de volledige balk past zonder overloop, de gele knop staat rechts van het menu en de NL-pill rechts daarvan.

- [ ] **Step 8: Commit**

```bash
git add resources/views/partials/languagePill.antlers.html resources/views/partials/navigation.antlers.html lang/nl/site.php tests/Feature/Sections/NavigationTest.php
git commit -m "feat(nav): voeg de offerte-knop en de taalpill toe"
```

---

### Task 4: Het mobiele paneel

**Files:**
- Modify: `resources/views/partials/mobileNavigation.antlers.html`
- Test: `tests/Feature/Sections/NavigationTest.php`

**Interfaces:**
- Consumes: `{{ partial:languagePill }}` uit Task 3, `.btn--accent`.
- Produces: niets.

- [ ] **Step 1: Schrijf de falende test**

Voeg onderaan `tests/Feature/Sections/NavigationTest.php` toe, binnen de klasse:

```php
    public function test_mobile_panel_repeats_the_quote_button_and_language_pill(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Twee keer: één keer in de desktopheader, één keer in het mobiele
        // paneel. Figma tekent geen open-state voor mobiel; dit is ingevuld.
        $this->assertSame(2, substr_count($html, 'href="/offerte"'));
        $this->assertSame(2, substr_count($html, 'Taal: Nederlands'));
    }

    public function test_mobile_panel_links_straight_to_the_range_overview(): void
    {
        $html = $this->render('{{ partial:navigation }}');

        // Op mobiel is Aanbod een gewone link; het mega menu rendert alleen
        // vanaf `md`. Het paneel bevat dus geen tweede paneel-id.
        $this->assertSame(1, substr_count($html, 'id="mega-menu-panel"'));
    }
```

- [ ] **Step 2: Draai de tests en kijk ze falen**

Run: `vendor/bin/phpunit -d memory_limit=1G --filter NavigationTest`
Expected: FAIL — `href="/offerte"` staat er één keer, niet twee.

- [ ] **Step 3: Vul het mobiele paneel aan**

In `resources/views/partials/mobileNavigation.antlers.html`, direct na het sluitende `</nav>` en vóór het sluitende `</div>` van `.mobile-navigation__content`:

```antlers
                <div class="mt-8 flex flex-col items-start gap-4">
                    <a href="/offerte" class="btn btn--accent w-full">{{ trans:site.nav_quote }}</a>
                    {{ partial:languagePill }}
                </div>
```

De `children`-lus in dit bestand blijft staan. Hij rendert niets zolang de boom plat is, en hem weghalen is opruimwerk zonder ontwerp om op terug te vallen.

- [ ] **Step 4: Draai de volledige suite**

Run: `vendor/bin/phpunit -d memory_limit=1G`
Expected: PASS — alles groen, ook de tests die de header via andere pagina's renderen.

- [ ] **Step 5: Bouw de assets en controleer mobiel**

Run: `npm run build`

Open de site op een smal venster, open het paneel met de hamburger. Controleer: de vijf items staan onder elkaar, Aanbod is een gewone link naar `/aanbod`, en onderaan staan de offerte-knop over de volle breedte en de taalpill.

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/mobileNavigation.antlers.html tests/Feature/Sections/NavigationTest.php
git commit -m "feat(nav): geef het mobiele paneel de offerte-knop en de taalpill"
```

---

## Wat dit plan bewust niet doet

- **Multisite.** De taalpill is decoratie tot er een tweede site is. Zie de spec.
- **`/offerte`.** Wordt parallel gebouwd. De knop wijst er alvast heen.
- **De `overline`-regel.** Figma tekent hem 3px dik, `overline.css` doet `h-px`. Dat verschil geldt voor élke overline op de site en hoort in een aparte ronde thuis.
- **`cases.md`.** Blijft achter zonder navigatie-item. Contentvraag, geen navigatievraag.
