# Contactpagina — implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/contact` bouwen volgens Figma `318:3481` — default header, één licht paneel met de drie vestigingen en hun openingsuren plus een contactbalk, twee quicklinks uit een nieuw paginaveld, en een CTA uit de page builder.

**Architecture:** Vijf lagen, elk apart testbaar. Eerst de content (globals + openingsuren), dan de `contactDetails`-partial die die content rendert en meteen het template overneemt, dan de quicklink-kaart die uit de bestaande collectie-component geëxtraheerd wordt zodat pagina en collectie dezelfde kaart delen, dan het paginaveld met zijn eigen tweekoloms-sectie, en tot slot de CTA als page-builder-set.

**Tech Stack:** Statamic 6 / Laravel 12 / Antlers, Tailwind v4 (CSS-first, `@theme` in `resources/css/site.css`), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-07-27-contact-page-design.md`

## Global Constraints

- **Testcommando:** `php -d memory_limit=1G ./vendor/bin/phpunit`, en `--filter=<TestClass>` voor één klasse. Baseline vóór dit werk: **183 tests, 744 assertions, 1 skipped, OK**. Niet `php artisan test`: dat spawnt een subproces op PHP's standaard 128 MB en loopt betrouwbaar OOM in `AssetUploadCompressionTest` (intervention/image `Cloner.php`); een `-d memory_limit` op het buitenste commando bereikt dat subproces niet.
- **`SectionTestCase::render()` heeft géén Statamic-cascade.** Die helper roept een kale `view()` aan, dus `{{ globals:… }}` is daar **altijd leeg**, ongeacht wat er in `content/globals/` staat. Geverifieerd tegen de runtime. Alles wat globals leest, wordt getest via `$this->get('/contact')`. `{{ collection:… }}`-tags werken wél in `render()`.
- **`regex_replace` is in dit project een stille no-op.** Getest in vier argumentsyntaxen (`("a","b")`, `('a','b')`, `:a:b`, met en zonder lege vervanging): de waarde komt ongewijzigd terug, zonder foutmelding. Gebruik de `replace`-ketting. Geverifieerd: `{{ mobile | replace('+', '') | replace(' ', '') }}` op `+32 470 00 00 00` levert `32470000000`.
- **Nooit een Tailwind-klassenaam interpoleren.** Tailwind's scanner leest broncode statisch en genereert niets voor een runtime-samengestelde string. Schrijf elke klassenstring voluit in élke tak van een ternary of `{{ if }}`.
- **Taal:** codecommentaar, contentcopy en commitberichten in het Nederlands, net als de rest van dit project.
- **Copy letterlijk uit Figma `318:3481`.** Header-tekst: `Een korte vraag? Bel of mail rechtstreeks het filiaal in uw buurt — u krijgt meteen iemand die uw situatie kent.` Openingsuren: `Di - Vr` → `10:30 - 17:30`, `Zaterdag` → `10:00 - 16:00`, `Zo & Ma` → `Gesloten`. Quicklink-titels: `Vraag offerte aan` en `Een herstelling melden`. Knoplabels: `Vraag offerte aan` en `Naar herstelformulier`. Contactbalk: `Whatsapp`, `03 000 00 00`, `info@winsolbrebo.be`.
- **Maten uit Figma `318:3510`** (frame 1744 breed): paneelpadding 64 horizontaal / 80 verticaal, kaartjes 490,67 breed met 32 gap, kaartpadding 32 horizontaal / 56 verticaal, contactbalk 89 hoog op 32 onder de kaartjes, itemafstand in de balk 112.
- **Maten uit Figma `465:1712`:** quicklink-kaart 816 × 343 met 32 gap, foto 138 × 129 op `y = -57` ten opzichte van de kaartrand, links uitgelijnd op het kaartpadding.

## Afwijkingen van de spec

Vier punten die tijdens de verificatie tegen de runtime naar boven kwamen. Ze staan hier zodat de uitvoerder ze niet opnieuw hoeft te ontdekken en een reviewer ziet dat het bewuste keuzes zijn.

1. **`FooterTest` breekt níét.** De spec voorspelde dat het vullen van `globals:contact:phone`/`:email` de derde `footer__column` zou laten verschijnen en die test zou breken. Dat gebeurt niet: `FooterTest` draait via `SectionTestCase::render()`, dat geen cascade heeft, dus de footer ziet daar altijd lege globals en telt onveranderd 2 kolommen. Via HTTP (`$this->get('/contact')`) verschijnt de derde kolom wél — geverifieerd. Taak 1 laat de assertions dus met rust en corrigeert alleen de docblock, die nu een verkeerde reden noemt ("de globals zijn genulld" in plaats van "de harness heeft geen cascade") en de volgende lezer op een dwaalspoor zet.
2. **`regex_replace` vervalt ten gunste van een `replace`-ketting.** Zie Global Constraints.
3. **`contact.yaml` mist `import: template`.** De sidebar van dat blueprint kent alleen `slug`, terwijl `page.yaml` ook `template` importeert. De entry draagt `template: contact` in zijn front matter; zonder het veld in het blueprint kan een CP-bewerking dat stilzwijgend laten vallen, waarna `/contact` terugvalt op `default.antlers.html`. Taak 4 voegt de import toe, in dezelfde diff die de entry op dit blueprint zet.
4. **`QuicklinksContentTest::test_the_contact_blueprint_no_longer_carries_a_dead_quicklinks_field` wordt vervangen.** Die test pint vast dat `collections.pages.contact` géén `quicklinks`-veld heeft. Taak 4 zet er weer een neer — maar met een andere betekenis: het oude veld was een `entries`-picker die dubbelde met de collectie, het nieuwe is een `grid` met pagina-eigen rijen die de collectie niet raakt. De test wordt herschreven in plaats van geschrapt, zodat dat onderscheid vastgelegd blijft.

## Samenloop met de `service-page`-branch

De branch `service-page` (plan: `docs/superpowers/plans/2026-07-27-service-page.md`) raakt hetzelfde bestand. Zijn taak 3 herschrijft `resources/css/components/form.css` en zet daarbij nieuwe klassen op `resources/views/contact.antlers.html`, omdat de oude element-selectors (`input { border border-black }`) anders het nieuwe formulier zouden stijlen.

Taak 2 van dít plan verwijdert het formulier volledig uit `contact.antlers.html`. Wat dat betekent, hangt af van de merge-volgorde:

- **`service-page` eerst:** de rewrite in taak 2 haalt hun class-attributen samen met het formulier weg. Een merge-conflict op dat bestand is te verwachten; los het op door de versie uit dít plan te nemen (vier regels, geen formulier).
- **Dit plan eerst:** hun taak 3 hoeft `contact.antlers.html` niet meer aan te raken — er staat geen formulier meer op `/contact`, dus de "zichtbaar kapotte pagina" die zij wilden voorkomen bestaat niet. Hun `form.css`-herschrijving blijft wél nodig voor `/service`.

Raak `resources/css/components/form.css` in dit plan niet aan.

---

## Bestandsoverzicht

| Bestand | Taak | Verantwoordelijkheid |
|---|---|---|
| `content/globals/globals.yaml` | 1 | contactgegevens (set-bestand) |
| `content/globals/default/globals.yaml` | 1 | contactgegevens (site-variabelen) |
| `content/collections/locations/*.md` | 1 | `opening_hours` per vestiging |
| `tests/Feature/Content/ContactGlobalsTest.php` | 1 | nieuw — de drie globals |
| `tests/Feature/Content/LocationsContentTest.php` | 1 | openingsuren erbij |
| `tests/Feature/Sections/FooterTest.php` | 1 | alleen de docblock |
| `resources/css/site.css` | 2 | `--color-whatsapp`, import van `contact-details.css` |
| `resources/css/components/contact-details.css` | 2 | paneel, kaartje, balk |
| `resources/views/partials/contactDetails.antlers.html` | 2 | het lichte paneel |
| `resources/views/contact.antlers.html` | 2, 4 | herschreven, formulier eruit |
| `tests/Feature/Sections/ContactDetailsTest.php` | 2 | nieuw |
| `resources/views/partials/quicklinkCard.antlers.html` | 3 | nieuw, gedeelde kaart |
| `resources/views/partials/quicklinks.antlers.html` | 3 | gebruikt de gedeelde kaart |
| `resources/css/components/quicklinks.css` | 3 | overhang + gridafstanden |
| `content/collections/quicklinks/*.md` | 3 | `image` gekoppeld |
| `tests/Feature/Sections/QuicklinksTest.php` | 3 | bijgesteld |
| `resources/blueprints/collections/pages/contact.yaml` | 4 | `quicklinks`-grid + `import: template` |
| `resources/views/partials/pageQuicklinks.antlers.html` | 4 | tweekoloms-sectie |
| `content/collections/pages/contact.md` | 4, 5 | blueprint, `text`, de twee items, de CTA |
| `tests/Feature/Sections/PageQuicklinksTest.php` | 4 | nieuw |
| `tests/Feature/Content/QuicklinksContentTest.php` | 4 | blueprint-test herschreven |
| `tests/Feature/Content/ContactPageTest.php` | 5 | nieuw — de hele pagina |

---

## Task 1: Contactgegevens en openingsuren in de content

De datalaag, los van elke rendering. Na deze taak verandert er niets zichtbaars behalve de derde footerkolom op een echte pagerender.

**Files:**
- Modify: `content/globals/globals.yaml`
- Modify: `content/globals/default/globals.yaml`
- Modify: `content/collections/locations/winsol-dilbeek.md`
- Modify: `content/collections/locations/winsol-sint-pieters-leeuw.md`
- Modify: `content/collections/locations/winsol-aartselaar.md`
- Modify: `tests/Feature/Sections/FooterTest.php` (alleen de docblock)
- Create: `tests/Feature/Content/ContactGlobalsTest.php`
- Test: `tests/Feature/Content/LocationsContentTest.php`

**Interfaces:**
- Produces: `globals:contact:mobile` = `+32 470 00 00 00`, `globals:contact:phone` = `03 000 00 00`, `globals:contact:email` = `info@winsolbrebo.be`. Elke `locations`-entry krijgt `opening_hours`: een array van rijen met de sleutels `day` en `time`. Taak 2 leest beide.

- [ ] **Step 1: Schrijf de falende test voor de globals**

Maak `tests/Feature/Content/ContactGlobalsTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class ContactGlobalsTest extends TestCase
{
    public function test_the_contact_details_from_the_design_are_filled_in(): void
    {
        $contact = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact');

        $this->assertSame('+32 470 00 00 00', $contact['mobile']);
        $this->assertSame('03 000 00 00', $contact['phone']);
        $this->assertSame('info@winsolbrebo.be', $contact['email']);
    }

    public function test_the_mobile_number_survives_the_strip_that_wa_me_needs(): void
    {
        // wa.me accepteert alleen cijfers in internationaal formaat: geen +,
        // geen spaties, geen voorloopnul. Daarom staat `mobile` internationaal
        // genoteerd — een nationale `0470 …` zou na de strip een ongeldige
        // wa.me/0470000000 opleveren. Dit pint dat formaatcontract vast, want
        // de partial in taak 2 leunt erop.
        $mobile = GlobalSet::findByHandle('globals')->inDefaultSite()->get('contact')['mobile'];

        $this->assertSame('32470000000', str_replace(['+', ' '], '', $mobile));
    }
}
```

- [ ] **Step 2: Schrijf de falende test voor de openingsuren**

Voeg toe aan `tests/Feature/Content/LocationsContentTest.php`, binnen de bestaande class:

```php
    public function test_every_location_carries_the_designed_opening_hours(): void
    {
        // Alle drie de vestigingen tonen in het design dezelfde uren. Het zijn
        // aparte entries en geen gedeelde global, zodat één vestiging later
        // afwijkende uren kan krijgen zonder codewijziging.
        $expected = [
            ['day' => 'Di - Vr', 'time' => '10:30 - 17:30'],
            ['day' => 'Zaterdag', 'time' => '10:00 - 16:00'],
            ['day' => 'Zo & Ma', 'time' => 'Gesloten'],
        ];

        $entries = Entry::query()->where('collection', 'locations')->get();

        $this->assertCount(3, $entries);

        foreach ($entries as $entry) {
            $hours = $entry->get('opening_hours');

            $this->assertIsArray($hours, "Locatie {$entry->slug()} heeft geen openingsuren");
            $this->assertCount(3, $hours, "Locatie {$entry->slug()} hoort drie regels te tonen");

            foreach ($expected as $i => $row) {
                $this->assertSame($row['day'], $hours[$i]['day'], "Dag {$i} van {$entry->slug()} klopt niet");
                $this->assertSame($row['time'], $hours[$i]['time'], "Tijd {$i} van {$entry->slug()} klopt niet");
            }
        }
    }
```

- [ ] **Step 3: Draai beide tests en controleer dat ze falen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter='ContactGlobalsTest|LocationsContentTest'`
Expected: FAIL — `ContactGlobalsTest` op `null` in plaats van de nummers, `LocationsContentTest` op `assertIsArray` (het veld bestaat nog niet).

- [ ] **Step 4: Vul de globals**

In `content/globals/globals.yaml` én `content/globals/default/globals.yaml` het complete `contact`-blok vervangen door:

```yaml
contact:
  mobile: '+32 470 00 00 00'
  phone: '03 000 00 00'
  email: info@winsolbrebo.be
```

Beide bestanden: `default/globals.yaml` bevat de site-variabelen die de cascade leest, `globals.yaml` het set-bestand. Ze stonden allebei op `null` en horen niet uit elkaar te lopen.

- [ ] **Step 5: Vul de openingsuren**

In alle drie de bestanden `content/collections/locations/*.md`, ná `longitude` en vóór de sluitende `---`:

```yaml
opening_hours:
  -
    day: 'Di - Vr'
    time: '10:30 - 17:30'
  -
    day: Zaterdag
    time: '10:00 - 16:00'
  -
    day: 'Zo & Ma'
    time: Gesloten
```

- [ ] **Step 6: Draai de tests en controleer dat ze slagen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter='ContactGlobalsTest|LocationsContentTest'`
Expected: PASS

Blijft `LocationsContentTest` falen met een leeg `opening_hours`, dan is de Stache nog warm: `php please stache:clear` en opnieuw.

- [ ] **Step 7: Corrigeer de docblock van FooterTest**

`FooterTest` blijft groen — die draait op `SectionTestCase::render()`, dat geen cascade heeft, dus de footer ziet daar nog steeds lege globals. De docblock legt dat verkeerd uit. Vervang in `tests/Feature/Sections/FooterTest.php` de bestaande docblock boven `test_renders_populated_link_columns_and_a_colophon` door:

```php
    /**
     * Deze test draait via SectionTestCase::render(), en die helper roept een
     * kale view() aan — zonder Statamic-cascade. `{{ globals:… }}` is daar
     * altijd leeg, ongeacht wat er in content/globals/ staat. De Contact-
     * `footer__column` valt hier dus weg op zijn `{{ if }}`-guard, ook nu de
     * contactgegevens wél gevuld zijn. Op een echte pagerender
     * ($this->get(…)) verschijnt die kolom wel.
     *
     * Deze test dekt daarom de twee kolommen die zonder cascade gevuld zijn
     * (ranges + hoofdnavigatie) en de legal-links in het colofon.
     */
```

- [ ] **Step 8: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: PASS — 186 tests (183 + 2 in `ContactGlobalsTest` + 1 in `LocationsContentTest`), geen regressies.

- [ ] **Step 9: Commit**

```bash
git add content/globals tests/Feature/Content/ContactGlobalsTest.php \
        content/collections/locations tests/Feature/Content/LocationsContentTest.php \
        tests/Feature/Sections/FooterTest.php
git commit -m "vul de contactgegevens en de openingsuren aan

De drie vestigingen tonen in het design dezelfde uren, maar ze staan per
entry zodat een filiaal later kan afwijken. Het mobiele nummer staat
internationaal genoteerd: wa.me accepteert geen + en geen voorloopnul.

De docblock van FooterTest noemde een verkeerde reden voor de ontbrekende
Contact-kolom — niet de lege globals, maar de cascadeloze testharness."
```

---

## Task 2: `contactDetails` en het herschreven template

**Files:**
- Modify: `resources/css/site.css`
- Create: `resources/css/components/contact-details.css`
- Create: `resources/views/partials/contactDetails.antlers.html`
- Modify: `resources/views/contact.antlers.html` (volledig vervangen)
- Test: `tests/Feature/Sections/ContactDetailsTest.php`

**Interfaces:**
- Consumes: `globals:contact:{mobile,phone,email}` en `opening_hours` uit taak 1.
- Produces: `{{ partial:contactDetails }}` — geen argumenten. Rendert `<section data-section="contact_details">` met per locatie een `.contact-location` en, als minstens één contactglobal gevuld is, een `.contact-bar`. CSS-klassen die later hergebruikt mogen worden: `.contact-panel`, `.contact-location`, `.contact-bar`.

- [ ] **Step 1: Schrijf de falende tests**

Maak `tests/Feature/Sections/ContactDetailsTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class ContactDetailsTest extends SectionTestCase
{
    public function test_it_renders_a_card_per_location(): void
    {
        $html = $this->render('{{ partial:contactDetails }}');

        $this->assertStringContainsString('data-section="contact_details"', $html);
        $this->assertSame(3, substr_count($html, 'contact-location"'));
        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
    }

    public function test_it_composes_the_address_from_the_separate_fields(): void
    {
        $html = $this->render('{{ partial:contactDetails }}');

        $this->assertStringContainsString('Ninoofsesteenweg 000, 1700 Dilbeek', $html);
        $this->assertStringContainsString('Antwerpsesteenweg 000, 2630 Aartselaar', $html);
    }

    public function test_it_renders_the_opening_hours_as_day_time_pairs(): void
    {
        $html = $this->render('{{ partial:contactDetails }}');

        // Een dag-tijdpaar is een beschrijvingslijst; losse spans zouden de
        // koppeling weggooien. Drie regels maal drie vestigingen.
        $this->assertSame(9, substr_count($html, '<dt>'));
        $this->assertSame(9, substr_count($html, '<dd>'));
        $this->assertStringContainsString('<dt>Di - Vr</dt>', $html);
        $this->assertStringContainsString('<dd>10:30 - 17:30</dd>', $html);
        $this->assertStringContainsString('<dt>Zo &amp; Ma</dt>', $html);
        $this->assertStringContainsString('<dd>Gesloten</dd>', $html);
    }

    // De `{{ if opening_hours }}`-guard wordt hier bewust niet gedekt. Alle
    // drie de entries hebben uren, en het kaartje is geen losse partial, dus
    // er is geen manier om vanuit deze test een urenloze variant te renderen:
    // een waarde uit de $context wordt binnen `{{ collection:locations }}`
    // overschreven door de loopscope. Dezelfde afweging staat bij de
    // image-guard in QuicklinksTest. Wil je die tak later wel dekken, dan is
    // een `contactLocationCard`-partial de weg — zoals `locationCard` dat voor
    // de coordinaatloze variant al doet.

    public function test_the_contact_bar_is_absent_without_a_cascade(): void
    {
        // SectionTestCase::render() roept een kale view() aan, zonder Statamic-
        // cascade: `{{ globals:… }}` is daar altijd leeg. Dat is geen bug maar
        // het contract van deze harness, en het is precies waarom de balk
        // hieronder via een echte pagerender getest wordt.
        $html = $this->render('{{ partial:contactDetails }}');

        $this->assertStringNotContainsString('contact-bar', $html);
    }

    public function test_the_contact_bar_renders_from_the_globals_on_a_real_page(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('contact-bar', $html);
        $this->assertStringContainsString('Whatsapp', $html);
        $this->assertStringContainsString('03 000 00 00', $html);
        $this->assertStringContainsString('info@winsolbrebo.be', $html);
    }

    public function test_the_bar_links_are_dialable_and_the_wa_me_number_is_digits_only(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        // De strip is de enige transformatie in de partial en dus het enige
        // dat stil kan breken: wa.me weigert een + of een voorloopnul.
        $this->assertStringContainsString('href="https://wa.me/32470000000"', $html);
        $this->assertStringContainsString('href="tel:030000000"', $html);
        $this->assertStringContainsString('href="mailto:info@winsolbrebo.be"', $html);
    }

    public function test_the_page_no_longer_ships_a_contact_form(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        // Het design toont geen formulier op /contact. De form- en recaptcha-
        // bestanden blijven bestaan voor een latere offerte- of herstelpagina.
        //
        // De assertie mikt op de action van juist dít formulier en niet op
        // '<form', zodat een later formulier elders in de layout (denk aan een
        // nieuwsbrief in de footer) deze test niet vals laat falen.
        $this->assertStringNotContainsString('/!/forms/contact', $html);
        $this->assertStringNotContainsString('g-recaptcha', $html);
    }
}
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=ContactDetailsTest`
Expected: FAIL — de partial `contactDetails` bestaat nog niet.

- [ ] **Step 3: Voeg de WhatsApp-kleur toe**

In `resources/css/site.css`, in het `@theme`-blok, direct onder `--color-dark`:

```css
    /* Merkkleur van WhatsApp, gebruikt door de contactbalk. Als token en niet
       als arbitrary value: hij komt terug zodra er elders een WhatsApp-knop
       opduikt. */
    --color-whatsapp: #25D366;
```

En onderaan bij de componenten, alfabetisch tussen `card.css` en `cookie-consent.css` past hij niet — houd de bestaande, niet-alfabetische volgorde aan en zet hem onder de `quicklinks.css`-import:

```css
@import './components/contact-details.css';
```

- [ ] **Step 4: Schrijf de CSS**

Maak `resources/css/components/contact-details.css`:

```css
/*
 * Figma dgMxUtoYzYrR5FRuwPzQBn, node 318:3510 (desktop 1744): licht paneel op
 * containerbreedte met 64 binnenpadding horizontaal en 80 verticaal, drie
 * witte kaartjes van 490,67 met 32 gap, en 32 daaronder een witte balk van 89
 * hoog met 112 tussen de items.
 */
.contact-panel {
    @apply relative isolate flex flex-col gap-6 overflow-hidden rounded-md bg-light p-6 lg:gap-8 lg:px-16 lg:py-20;
}

/*
 * De blob staat in Figma op x=-277 binnen een paneel van 1664 (-16,6%) en is
 * 1101 breed (66%). `overflow-hidden` op het paneel klipt hem aan de eigen
 * rand: hij groeit het paneel niet en kan dus document.documentElement niet
 * verbreden. Zelfde constructie als gridCta.antlers.html.
 */
.contact-panel__shape {
    @apply pointer-events-none absolute -z-10 h-auto max-w-none text-white/50;

    top: 10%;
    left: -16.6%;
    width: 66%;
}

/*
 * Het kaartje heeft in Figma asymmetrische padding (32 horizontaal, 56
 * verticaal). Daarom niet de `card-padding`-utility: die is symmetrisch, en
 * hem oprekken met een tweede as zou hem voor zijn vijf bestaande gebruikers
 * veranderen.
 */
.contact-location {
    @apply flex flex-col gap-2 rounded-md bg-white p-6 lg:px-8 lg:py-14;
}

.contact-location__name {
    @apply text-xl font-semibold;
}

.contact-location__address {
    @apply text-base;
}

.contact-location__hours {
    @apply mt-4 flex flex-col gap-2 text-sm;
}

.contact-location__hours-row {
    @apply flex justify-between gap-4;
}

.contact-bar {
    @apply flex flex-col items-center gap-4 rounded-md bg-white px-6 py-5 sm:flex-row sm:justify-center sm:gap-10 lg:gap-28;
}

.contact-bar__item {
    @apply flex items-center gap-3 font-semibold transition-opacity hover:opacity-70;
}

.contact-bar__icon {
    @apply flex size-10 shrink-0 items-center justify-center rounded-full;
}

.contact-bar__icon--whatsapp {
    @apply bg-whatsapp text-white;
}

.contact-bar__icon--accent {
    @apply bg-accent text-black;
}
```

- [ ] **Step 5: Schrijf de partial**

Maak `resources/views/partials/contactDetails.antlers.html`:

```antlers
{{#
    Vaste component: geen argumenten, leest de locations-collectie en de
    globals zelf.

    In Figma (318:3510) zitten de adreskaartjes en de contactbalk samen in
    één licht paneel met de shape-blob erachter — niet in twee losse
    containers, zoals de opdracht ze beschreef. De gedeelde achtergrond en de
    blob kloppen anders niet.

    De balk leest `globals:contact:*`. Die cascade bestaat alleen bij een
    echte pagerender: in SectionTestCase::render() is hij altijd leeg. Daarom
    test ContactDetailsTest de balk via $this->get('/contact') en de
    kaartjes via render().

    `regex_replace` is in dit project een stille no-op — geverifieerd in vier
    argumentsyntaxen, de waarde komt ongewijzigd terug zonder foutmelding.
    Vandaar de replace-ketting voor het wa.me-nummer, dat alleen cijfers
    accepteert.
#}}
<section class="section section--default" data-section="contact_details">
    <div class="container">
        <div class="contact-panel">
            {{ svg src="shape" aria-hidden="true" class="contact-panel__shape" }}

            <ul class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                {{ collection:locations }}
                    <li class="contact-location">
                        <h3 class="contact-location__name">{{ name }}</h3>
                        <p class="contact-location__address">{{ street }} {{ number }}, {{ postal_code }} {{ city }}</p>

                        {{ if opening_hours }}
                            <dl class="contact-location__hours">
                                {{ opening_hours }}
                                    <div class="contact-location__hours-row">
                                        <dt>{{ day }}</dt>
                                        <dd>{{ time }}</dd>
                                    </div>
                                {{ /opening_hours }}
                            </dl>
                        {{ /if }}
                    </li>
                {{ /collection:locations }}
            </ul>

            {{ if globals:contact:mobile or globals:contact:phone or globals:contact:email }}
                <ul class="contact-bar">
                    {{ if globals:contact:mobile }}
                        <li>
                            <a class="contact-bar__item" href="https://wa.me/{{ globals:contact:mobile | replace('+', '') | replace(' ', '') }}" target="_blank" rel="noopener">
                                <span class="contact-bar__icon contact-bar__icon--whatsapp">{{ svg src="icons/fill/whatsapp-logo-fill" aria-hidden="true" class="size-6" }}</span>
                                Whatsapp
                            </a>
                        </li>
                    {{ /if }}

                    {{ if globals:contact:phone }}
                        <li>
                            <a class="contact-bar__item" href="tel:{{ globals:contact:phone | replace(' ', '') }}">
                                <span class="contact-bar__icon contact-bar__icon--accent">{{ svg src="icons/fill/phone-fill" aria-hidden="true" class="size-6" }}</span>
                                {{ globals:contact:phone }}
                            </a>
                        </li>
                    {{ /if }}

                    {{ if globals:contact:email }}
                        <li>
                            <a class="contact-bar__item" href="mailto:{{ globals:contact:email }}">
                                <span class="contact-bar__icon contact-bar__icon--accent">{{ svg src="icons/fill/envelope-fill" aria-hidden="true" class="size-6" }}</span>
                                {{ globals:contact:email }}
                            </a>
                        </li>
                    {{ /if }}
                </ul>
            {{ /if }}
        </div>
    </div>
</section>
```

- [ ] **Step 6: Vervang het template**

`resources/views/contact.antlers.html` wordt in zijn geheel:

```antlers
{{ partial:headers/default }}
{{ partial:contactDetails }}
{{ partial:pageBuilder }}
```

Het formulier, de recaptcha en de hardcoded contactrij verdwijnen: het design toont ze niet. `resources/forms/contact.yaml` en `resources/views/partials/recaptcha.antlers.html` blijven staan — die zijn nodig zodra er een offerte- of herstelpagina komt. Raak `resources/css/components/form.css` niet aan; de `service-page`-branch herschrijft dat bestand (zie Samenloop).

Taak 4 zet `{{ partial:pageQuicklinks }}` tussen `contactDetails` en `pageBuilder`.

- [ ] **Step 7: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=ContactDetailsTest`
Expected: PASS

Faalt `test_the_bar_links_are_dialable_…`, controleer dan eerst of taak 1 daadwerkelijk gecommit is: zonder gevulde globals rendert de balk niet.

- [ ] **Step 8: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: PASS — 193 tests, geen regressies.

- [ ] **Step 9: Controleer het beeld in de browser**

Draai `npm run dev` en open `/contact`. Controleer tegen Figma `318:3481`: het lichte paneel met de blob subtiel zichtbaar linksboven, drie witte kaartjes naast elkaar vanaf `lg`, de balk eronder met drie gekleurde icoonvlakjes. Stem de tint van `.contact-panel__shape` (`text-white/50`) visueel af — dat is de enige waarde in dit bestand die niet uit een Figma-maat volgt. Controleer ook dat de pagina onder `lg` netjes stapelt.

- [ ] **Step 10: Commit**

```bash
git add resources/css/site.css resources/css/components/contact-details.css \
        resources/views/partials/contactDetails.antlers.html \
        resources/views/contact.antlers.html \
        tests/Feature/Sections/ContactDetailsTest.php
git commit -m "bouw het contactpaneel met adressen, uren en contactbalk

In Figma zitten de kaartjes en de balk in één licht paneel, niet in twee
losse containers. De balk leest de globals, die alleen in een echte
pagerender bestaan — vandaar dat die assertions via \$this->get() lopen.

Het formulier verdwijnt van /contact: het design toont er geen. De form- en
recaptcha-bestanden blijven staan voor de offerte- en herstelpagina."
```

---

## Task 3: De gedeelde quicklink-kaart met overhang

**Files:**
- Create: `resources/views/partials/quicklinkCard.antlers.html`
- Modify: `resources/views/partials/quicklinks.antlers.html`
- Modify: `resources/css/components/quicklinks.css`
- Modify: `content/collections/quicklinks/vraag-offerte-aan.md`
- Modify: `content/collections/quicklinks/vraag-brochure-aan.md`
- Modify: `content/collections/quicklinks/bezoek-een-showroom.md`
- Test: `tests/Feature/Sections/QuicklinksTest.php`

**Interfaces:**
- Consumes: niets uit taak 1 of 2.
- Produces: `{{ partial:quicklinkCard }}` — geen argumenten, leest `title`, `text`, `image`, `link` en `link_style` uit de omringende loopscope. Rendert `<div class="quicklink-card …">`. CSS-klassen die taak 4 gebruikt: `.quicklink-grid` (het grid dat ruimte voor de overhang reserveert) en `.quicklink-card`.

- [ ] **Step 1: Schrijf de falende tests**

Vervang in `tests/Feature/Sections/QuicklinksTest.php` de methode `test_it_renders_every_card_while_the_photos_are_still_unlinked` door de twee hieronder, en voeg de derde toe. De overige vier methodes blijven ongewijzigd — die dekken de collectie-component en horen groen te blijven.

```php
    public function test_every_card_now_carries_its_photo(): void
    {
        // De foto's stonden al in de assets-container onder quicklinks/; ze
        // waren alleen nog niet aan de entries gekoppeld. Dat sluit open punt 2
        // uit docs/superpowers/specs/2026-07-26-locations-quicklinks-design.md.
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertSame(3, substr_count($html, 'quicklink-media'));
        $this->assertSame(3, substr_count($html, '<img'));
    }

    public function test_the_card_markup_comes_from_the_shared_partial(): void
    {
        // Dezelfde kaart wordt door de collectie-component en door
        // pageQuicklinks gerenderd. Dit pint vast dat er één bron is: de
        // losse kaart levert dezelfde klassen op als de component eromheen.
        $html = $this->render('{{ partial:quicklinkCard }}', [
            'title' => 'Losse kaart',
            'text' => 'Met een tekst.',
            'link_style' => 'outline',
            'link' => [[
                'type' => 'url',
                'url' => 'example.com',
                'label' => 'Naar example',
            ]],
        ]);

        $this->assertStringContainsString('quicklink-card', $html);
        $this->assertStringContainsString('Losse kaart', $html);
        $this->assertStringContainsString('btn--outline', $html);

        // Zonder `image` mag de media-box niet meekomen, anders reserveert de
        // overhang ruimte voor een foto die er niet is.
        $this->assertStringNotContainsString('quicklink-media', $html);
    }

    public function test_the_grid_reserves_room_for_the_overhanging_photo(): void
    {
        // De foto hangt over de bovenrand van het lichte vlak. Zonder die
        // klasse op het grid valt hij over de kaart erboven en tegen de <h2>.
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('quicklink-grid', $html);
    }
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=QuicklinksTest`
Expected: FAIL — `quicklinkCard` bestaat niet, er zijn geen `<img>`-tags en `quicklink-grid` staat er niet.

- [ ] **Step 3: Maak de gedeelde kaart**

Maak `resources/views/partials/quicklinkCard.antlers.html`:

```antlers
{{#
    Eén bron voor de quicklink-kaart: `quicklinks` rendert hem per entry uit
    de collectie (drie kolommen), `pageQuicklinks` per rij van het gridveld
    op een pagina (twee kolommen).

    Bewust `quicklink-media` en niet `quicklink-card__media`: QuicklinksTest
    telt de kaarten met substr_count($html, 'quicklink-card'), en een
    BEM-kind van die naam zou elke kaart dubbel tellen.

    De link_style-mapping is een expliciete ternary met twee volledig
    uitgeschreven klassenstrings. Nooit interpoleren: Tailwind's scanner
    leest broncode statisch en vindt een runtime-samengestelde klassenaam
    niet.

    Zet nooit `overflow-hidden` op deze kaart — dat knipt de overhangende
    foto weg. Zie quicklinks.css.
#}}
<div class="quicklink-card flex flex-col gap-4 rounded-md bg-light card-padding lg:gap-6">
    {{ if image }}
        <div class="quicklink-media">
            {{ img :src="image" max_width="640" sizes="(min-width: 1024px) 30vw, 90vw" class="h-auto max-h-24 w-auto max-w-full object-contain lg:max-h-32" }}
        </div>
    {{ /if }}

    <h3>{{ title }}</h3>

    {{ if text }}
        <p>{{ text }}</p>
    {{ /if }}

    {{ if link }}
        {{ partial:link :style="link_style == 'outline' ? 'btn btn--outline' : 'btn btn--accent'" }}
    {{ /if }}
</div>
```

`w-auto max-w-full` in plaats van het oude `w-full`: in Figma staat de foto links uitgelijnd op zijn eigen breedte, niet gecentreerd over de kaart.

- [ ] **Step 4: Laat de collectie-component de kaart gebruiken**

In `resources/views/partials/quicklinks.antlers.html`: vervang het hele `<ul>`-blok (van `<ul class="grid …">` tot en met `</ul>`) door:

```antlers
            <ul class="quicklink-grid lg:grid-cols-3">
                {{ collection:quicklinks }}
                    <li>{{ partial:quicklinkCard }}</li>
                {{ /collection:quicklinks }}
            </ul>
```

Vervang in de docblock bovenaan de alinea die begint met "De link_style-mapping is een expliciete if/else" door:

```
    De kaart zelf staat in `quicklinkCard`, gedeeld met `pageQuicklinks`.
    Deze partial bepaalt alleen de bron (de collectie) en het aantal
    kolommen.
```

De alinea's over "Vaste component", de kale `<h2>` en "Geen slider" blijven ongewijzigd — die gaan over deze wrapper, niet over de kaart.

- [ ] **Step 5: Schrijf de CSS voor de overhang**

In `resources/css/components/quicklinks.css`: vervang het bestaande `.quicklink-media`-blok inclusief zijn commentaar door onderstaande twee blokken. `.quicklink-card` en de knopregel eronder blijven ongewijzigd.

```css
/*
 * De productfoto's zijn uitgeknipte beelden op transparant, geen bleed-foto's.
 * `.quicklink-media` is de vaste uitlijningsbox (h-24/h-32, items-end) die de
 * kaarten op één lijn houdt. De box stretcht zijn kind niet (items-end i.p.v.
 * items-stretch), dus een hoogte van 100% op de <img> zou tegen een
 * onbepaalde hoogte oplossen en naar auto terugvallen — vandaar dat de klem
 * (`max-h-24 lg:max-h-32`) in de partial op de afbeelding zelf staat.
 *
 * De negatieve marge is de overhang uit Figma 465:1712: de foto is 138 x 129
 * en staat op y=-57, dus ruim 40% steekt boven het lichte vlak uit. Een
 * negatieve marge en geen absolute positionering — zo blijft de foto in de
 * flow, houdt de kaart zijn eigen hoogte, en is er geen px-offset die per
 * breakpoint bijgesteld moet worden.
 *
 * Zet daarom nooit `overflow-hidden` op `.quicklink-card`.
 */
.quicklink-media {
    @apply -mt-10 flex h-24 items-end lg:-mt-14 lg:h-32;
}

/*
 * De rij-afstand moet groter zijn dan de overhang, anders valt de foto van de
 * volgende rij over de onderrand van de vorige — dat gebeurt gestapeld onder
 * `lg`, en vanaf `lg` zodra er een vierde kaart op een tweede rij komt. De
 * `pt` reserveert dezelfde ruimte boven de eerste rij, zodat de foto niet
 * tegen de <h2> botst.
 *
 * Het aantal kolommen staat bewust niet hier maar op de aanroepplek: de
 * collectie-component toont er drie, de paginacomponent twee.
 */
.quicklink-grid {
    @apply grid gap-x-6 gap-y-16 pt-10 lg:gap-x-8 lg:gap-y-20 lg:pt-14;
}
```

- [ ] **Step 6: Koppel de foto's aan de collectie-entries**

De bestanden staan al in de assets-container onder `quicklinks/`. Voeg in elke entry een `image`-regel toe, direct onder `text`:

- `content/collections/quicklinks/vraag-offerte-aan.md` → `image: quicklinks/offerte-1.png`
- `content/collections/quicklinks/vraag-brochure-aan.md` → `image: quicklinks/brochures.png`
- `content/collections/quicklinks/bezoek-een-showroom.md` → `image: quicklinks/winkel.png`

- [ ] **Step 7: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=QuicklinksTest`
Expected: PASS — alle zeven methodes.

- [ ] **Step 8: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: PASS — 195 tests, geen regressies.

- [ ] **Step 9: Commit**

```bash
git add resources/views/partials/quicklinkCard.antlers.html \
        resources/views/partials/quicklinks.antlers.html \
        resources/css/components/quicklinks.css \
        content/collections/quicklinks \
        tests/Feature/Sections/QuicklinksTest.php
git commit -m "trek de quicklink-kaart uit de component en geef hem zijn overhang

De Figma-node van de kaart is alsnog gevonden (465:1712): de foto hangt over
de bovenrand van het lichte vlak. Dat was open punt 3 uit de vorige spec.

De foto's bleken al in de assets-container te staan onder quicklinks/ — ze
waren alleen nog niet gekoppeld. Daarmee vervalt ook open punt 2."
```

---

## Task 4: Het `quicklinks`-paginaveld en zijn sectie

**Files:**
- Modify: `resources/blueprints/collections/pages/contact.yaml`
- Create: `resources/views/partials/pageQuicklinks.antlers.html`
- Modify: `resources/views/contact.antlers.html`
- Modify: `content/collections/pages/contact.md`
- Create: `tests/Feature/Sections/PageQuicklinksTest.php`
- Test: `tests/Feature/Content/QuicklinksContentTest.php`

**Interfaces:**
- Consumes: `{{ partial:quicklinkCard }}` en `.quicklink-grid` uit taak 3.
- Produces: `{{ partial:pageQuicklinks }}` — geen argumenten, leest het `quicklinks`-gridveld uit de cascade. Rendert `<section data-section="page_quicklinks">`, of niets als het veld leeg of afwezig is.

- [ ] **Step 1: Schrijf de falende tests voor de sectie**

Maak `tests/Feature/Sections/PageQuicklinksTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class PageQuicklinksTest extends SectionTestCase
{
    private function twoQuicklinks(): array
    {
        return [
            [
                'title' => 'Vraag offerte aan',
                'text' => 'Met Pergola SO! voorinvuld. Vrijblijvend en op maat.',
                'link_style' => 'primary',
                'link' => [[
                    'type' => 'url',
                    'url' => 'example.com',
                    'label' => 'Vraag offerte aan',
                ]],
            ],
            [
                'title' => 'Een herstelling melden',
                'text' => 'Al klant en werkt er iets niet? Meld het via het herstelformulier.',
                'link_style' => 'outline',
                'link' => [[
                    'type' => 'url',
                    'url' => 'example.com',
                    'label' => 'Naar herstelformulier',
                ]],
            ],
        ];
    }

    public function test_it_renders_a_card_per_row_under_the_hardcoded_title(): void
    {
        $html = $this->render('{{ partial:pageQuicklinks }}', [
            'quicklinks' => $this->twoQuicklinks(),
        ]);

        $this->assertStringContainsString('data-section="page_quicklinks"', $html);
        $this->assertStringContainsString('Zet de volgende stap', $html);
        $this->assertSame(2, substr_count($html, 'quicklink-card'));
        $this->assertStringContainsString('Vraag offerte aan', $html);
        $this->assertStringContainsString('Een herstelling melden', $html);
        $this->assertStringContainsString('Naar herstelformulier', $html);
    }

    public function test_the_first_button_is_filled_and_the_second_is_outlined(): void
    {
        $html = $this->render('{{ partial:pageQuicklinks }}', [
            'quicklinks' => $this->twoQuicklinks(),
        ]);

        // De link_style-mapping is de enige vertakking in de kaart, dus dat is
        // wat vastgepind hoort te worden.
        $this->assertSame(1, substr_count($html, 'btn--accent'));
        $this->assertSame(1, substr_count($html, 'btn--outline'));
    }

    public function test_it_lays_the_cards_out_in_two_columns(): void
    {
        $html = $this->render('{{ partial:pageQuicklinks }}', [
            'quicklinks' => $this->twoQuicklinks(),
        ]);

        // Twee kolommen, waar de collectie-component er drie toont. Het grid
        // zelf komt uit `.quicklink-grid`, dat de overhang-ruimte reserveert.
        $this->assertStringContainsString('quicklink-grid lg:grid-cols-2', $html);
    }

    public function test_it_renders_nothing_without_quicklinks(): void
    {
        // Zodat andere templates de partial gerust mogen includen.
        $html = $this->render('{{ partial:pageQuicklinks }}');

        $this->assertStringNotContainsString('data-section="page_quicklinks"', $html);
        $this->assertStringNotContainsString('Zet de volgende stap', $html);
    }
}
```

- [ ] **Step 2: Schrijf de falende blueprint-test**

Vervang in `tests/Feature/Content/QuicklinksContentTest.php` de methode `test_the_contact_blueprint_no_longer_carries_a_dead_quicklinks_field` door:

```php
    public function test_the_contact_blueprint_carries_a_page_local_quicklinks_grid(): void
    {
        // Het oude `quicklinks`-veld was een entries-picker die dubbelde met
        // de collectie en daarom verdween. Dit veld is iets anders: een grid
        // met pagina-eigen rijen, die de collectie niet raken. Het onderscheid
        // staat hier vast zodat de picker niet terugsluipt.
        $blueprint = Blueprint::find('collections.pages.contact');

        $this->assertNotNull($blueprint, 'Blueprint collections.pages.contact niet gevonden');

        $field = $blueprint->field('quicklinks');

        $this->assertNotNull($field, 'Het quicklinks-veld ontbreekt op de contact-blueprint');
        $this->assertSame('grid', $field->type());

        $subfields = collect($field->get('fields'))->pluck('handle')->all();

        $this->assertSame(['title', 'text', 'image', 'link', 'link_style'], $subfields);
    }

    public function test_the_contact_blueprint_keeps_its_template_picker(): void
    {
        // De entry draagt `template: contact` in zijn front matter. Zonder het
        // veld in het blueprint kan een CP-bewerking dat laten vallen, waarna
        // /contact terugvalt op default.antlers.html.
        $blueprint = Blueprint::find('collections.pages.contact');

        $this->assertNotNull($blueprint->field('template'));
    }
```

- [ ] **Step 3: Draai beide tests en controleer dat ze falen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter='PageQuicklinksTest|QuicklinksContentTest'`
Expected: FAIL — de partial bestaat niet en het blueprint heeft de velden niet.

- [ ] **Step 4: Breid het blueprint uit**

In `resources/blueprints/collections/pages/contact.yaml`: voeg tussen de sectie met `import: page_intro` en die met `import: page_builder` een nieuwe sectie toe, en vul de sidebar aan met de `template`-import.

De `tabs.main.sections`-lijst wordt:

```yaml
      -
        fields:
          -
            import: page_intro
      -
        fields:
          -
            handle: quicklinks
            field:
              type: grid
              display: Quicklinks
              mode: stacked
              add_row: '+ Add quicklink'
              fields:
                -
                  handle: title
                  field:
                    type: text
                    display: Title
                    required: true
                    validate:
                      - required
                -
                  handle: text
                  field:
                    type: textarea
                    display: Text
                -
                  import: image
                -
                  import: link
                -
                  handle: link_style
                  field:
                    type: select
                    display: 'Link Style'
                    default: primary
                    options:
                      primary: Primary
                      outline: Outline
      -
        fields:
          -
            import: page_builder
```

En in `tabs.sidebar.sections[0].fields`, ná het `slug`-veld:

```yaml
          -
            import: template
```

`grid` en niet `replicator`: er is maar één settype, en een replicator met één set is een keuzemenu zonder keuze. `gridCta` gebruikt om dezelfde reden al een `grid` met een genest `link`-grid erin.

- [ ] **Step 5: Schrijf de partial**

Maak `resources/views/partials/pageQuicklinks.antlers.html`:

```antlers
{{#
    Rendert het `quicklinks`-gridveld van de pagina in twee kolommen (Figma
    465:1711). De collectie-component `quicklinks` doet hetzelfde met de
    collectie in drie kolommen; ze delen `quicklinkCard`.

    De <h2> staat hier hardcoded en ook in quicklinks.antlers.html. Dat is
    een bewuste duplicatie van vier woorden: het alternatief is een partial
    van één regel, of één partial die impliciet tussen twee databronnen
    kiest — en op de contactpagina zouden allebei die bronnen bestaan.

    Zonder gevuld veld rendert deze partial niets, zodat andere templates hem
    gerust mogen includen.
#}}
{{ if quicklinks }}
    <section class="section section--default" data-section="page_quicklinks">
        <div class="container">
            <div class="section-y-gap">
                <h2 class="text-center">Zet de volgende stap</h2>

                <ul class="quicklink-grid lg:grid-cols-2">
                    {{ quicklinks }}
                        <li>{{ partial:quicklinkCard }}</li>
                    {{ /quicklinks }}
                </ul>
            </div>
        </div>
    </section>
{{ /if }}
```

- [ ] **Step 6: Include de sectie op het template**

`resources/views/contact.antlers.html` wordt:

```antlers
{{ partial:headers/default }}
{{ partial:contactDetails }}
{{ partial:pageQuicklinks }}
{{ partial:pageBuilder }}
```

- [ ] **Step 7: Zet de entry op het juiste blueprint en vul hem**

In `content/collections/pages/contact.md`: zet `blueprint: contact`, vervang de sleutel `intro:` (met zijn lorem-tekst) door `text:` met de designcopy, en voeg `quicklinks` toe. De front matter wordt:

```yaml
---
id: f0ee3161-1534-4986-9ef1-a92fccfba619
blueprint: contact
title: Contact
text: 'Een korte vraag? Bel of mail rechtstreeks het filiaal in uw buurt — u krijgt meteen iemand die uw situatie kent.'
seo_noindex: false
updated_by: d308c19c-c205-4453-9862-1f62996a3734
updated_at: 1773349039
template: contact
quicklinks:
  -
    id: ql01
    title: 'Vraag offerte aan'
    text: 'Met Pergola SO! voorinvuld. Vrijblijvend en op maat.'
    image: quicklinks/offerte-1.png
    link:
      -
        type: entry
        entry:
          - f0ee3161-1534-4986-9ef1-a92fccfba619
        label: 'Vraag offerte aan'
        new_tab: false
    link_style: primary
  -
    id: ql02
    title: 'Een herstelling melden'
    text: 'Al klant en werkt er iets niet? Meld het via het herstelformulier.'
    image: quicklinks/herstelling.png
    link:
      -
        type: entry
        entry:
          - f0ee3161-1534-4986-9ef1-a92fccfba619
        label: 'Naar herstelformulier'
        new_tab: false
    link_style: outline
---
```

De tweede kaart heet in de Figma-laagnamen nog "Bezoek een showroom"; dat is een verouderde naam, de gerenderde tekst in het design is leidend.

Beide knoppen wijzen naar de contact-entry zelf: er is nog geen `/offerte`- en geen herstelformulier-pagina. Dat is hetzelfde placeholder-patroon als de drie collectie-quicklinks.

- [ ] **Step 8: Draai de tests en controleer dat ze slagen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter='PageQuicklinksTest|QuicklinksContentTest|ContactDetailsTest'`
Expected: PASS

- [ ] **Step 9: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: PASS — 200 tests, geen regressies.

- [ ] **Step 10: Commit**

```bash
git add resources/blueprints/collections/pages/contact.yaml \
        resources/views/partials/pageQuicklinks.antlers.html \
        resources/views/contact.antlers.html \
        content/collections/pages/contact.md \
        tests/Feature/Sections/PageQuicklinksTest.php \
        tests/Feature/Content/QuicklinksContentTest.php
git commit -m "geef de contactpagina haar eigen twee quicklinks

Een grid op de contact-blueprint, met dezelfde velden als de collectie maar
pagina-eigen rijen. Het oude veld met die naam was een entries-picker die
dubbelde met de collectie; dit raakt de collectie niet.

De entry stond op blueprint 'page', waardoor contact.yaml nergens gebruikt
werd, en droeg een wees-sleutel 'intro' die in geen enkel blueprint bestaat
en dus nooit rendeerde. Allebei rechtgezet. Het blueprint kreeg ook de
template-import die het miste."
```

---

## Task 5: De CTA via de page builder

**Files:**
- Modify: `content/collections/pages/contact.md`
- Create: `tests/Feature/Content/ContactPageTest.php`

**Interfaces:**
- Consumes: de pagina uit taak 2 en 4.
- Produces: niets voor latere taken — dit is de laatste.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/ContactPageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class ContactPageTest extends TestCase
{
    public function test_the_page_renders_every_block_from_the_design(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();

        foreach (['contact_details', 'page_quicklinks', 'cta'] as $section) {
            $response->assertSee('data-section="'.$section.'"', false);
        }
    }

    public function test_the_header_shows_the_title_and_the_intro_from_the_design(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('Contact', false);
        $response->assertSee('Bel of mail rechtstreeks het filiaal in uw buurt', false);
    }

    public function test_the_cta_carries_its_copy_and_points_at_the_projects_overview(): void
    {
        $response = $this->get('/contact');

        $response->assertSee('Liever eerst even rondkijken?', false);
        $response->assertSee('Naar realisaties', false);
        $response->assertSee('href="/realisaties"', false);
    }

    public function test_the_blocks_appear_in_the_designed_order(): void
    {
        $html = $this->get('/contact')->getContent();

        $details = strpos($html, 'data-section="contact_details"');
        $quicklinks = strpos($html, 'data-section="page_quicklinks"');
        $cta = strpos($html, 'data-section="cta"');

        $this->assertLessThan($quicklinks, $details, 'Het contactpaneel hoort boven de quicklinks te staan');
        $this->assertLessThan($cta, $quicklinks, 'De quicklinks horen boven de CTA te staan');
    }
}
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=ContactPageTest`
Expected: FAIL — `data-section="cta"` en de CTA-copy ontbreken; de andere drie methodes horen al te slagen.

- [ ] **Step 3: Voeg de CTA toe aan de entry**

In `content/collections/pages/contact.md`, ná het `quicklinks`-blok en vóór de sluitende `---`:

```yaml
page_builder:
  -
    id: contactcta
    type: cta
    overline: Realisaties
    title: 'Liever eerst even rondkijken?'
    text: 'Geen zin om meteen contact op te nemen? Bekijk onze realisaties en ontdek wat we voor andere klanten in uw buurt hebben gemaakt.'
    link:
      -
        type: entry
        entry:
          - c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
        label: 'Naar realisaties'
        new_tab: false
    image: dummy-images/test-img-19.jpg
```

`c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03` is de Realisaties-entry (`content/collections/pages/realisaties.md`).

- [ ] **Step 4: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=ContactPageTest`
Expected: PASS

- [ ] **Step 5: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: PASS — 204 tests, geen regressies.

- [ ] **Step 6: Controleer de hele pagina in de browser**

Draai `npm run dev` en open `/contact`. Loop het af tegen Figma `318:3481`, van boven naar beneden: header, het lichte paneel, "Zet de volgende stap" met twee kaarten waarvan de foto's over de bovenrand hangen, en de CTA-band. Controleer daarna dezelfde volgorde op mobiel — er is geen mobile-frame in Figma, dus dit is de plek waar het gestapelde gedrag beoordeeld wordt: de kaartjes onder elkaar, de contactbalk verticaal, en genoeg ruimte tussen de quicklink-kaarten zodat geen enkele foto over de kaart erboven valt.

- [ ] **Step 7: Commit**

```bash
git add content/collections/pages/contact.md tests/Feature/Content/ContactPageTest.php
git commit -m "zet de CTA onderaan de contactpagina

Via de page builder, zoals elke andere CTA op de site. De test dekt meteen
de volgorde van de vier blokken op de pagina."
```

---

## Na afloop

Werk `docs/superpowers/specs/2026-07-26-locations-quicklinks-followups.md` bij: open punt 2 (het assets-pad van de quicklink-foto's) en open punt 3 (de Figma-node van de quicklink-component) zijn met taak 3 afgesloten.

Twee punten uit de spec blijven bewust open en horen in een volgende opdracht thuis:

1. **Echte contactgegevens.** `mobile`, `phone` en `email` staan als placeholder in de globals en zijn in het CMS aanpasbaar zonder codewijziging.
2. **Bestemming van de twee quicklinks.** Er is nog geen `/offerte`- en geen herstelformulier-pagina; beide knoppen wijzen tot dan naar `/contact`. De `service-page`-branch bouwt het herstelformulier — zodra die geland is, kan de tweede knop daarheen.
