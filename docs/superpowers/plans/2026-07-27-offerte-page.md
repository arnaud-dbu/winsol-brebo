# Offertepagina — implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/offerte` bouwen volgens Figma `318:3956` — links een titel, intro en stilleven, rechts een offerteformulier waarvan de producten uit de `ranges`-collectie komen en de filialen uit `locations`, met daaronder de page builder en één CTA.

**Architecture:** Vijf lagen, elk apart testbaar. Twee eigen fieldtypes leveren hun opties uit een collectie, zodat de productlijst en de filiaallijst niet een tweede keer in YAML bestaan. Het formulier hergebruikt de generieke `{{ sections }}`/`{{ fields }}`-loop en de `form.css`-klassen die `service-page` heeft neergezet; alleen de pillen, de kaart en het paginaraster zijn nieuw. Het template rijgt het geheel aan elkaar en laat de page builder eronder starten.

**Tech Stack:** Statamic 6 / Laravel 12 / Antlers, Tailwind v4 (CSS-first, `@theme` in `resources/css/site.css`), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-07-27-offerte-page-design.md`

## Global Constraints

- **Testcommando:** `php -d memory_limit=1G ./vendor/bin/phpunit`. Zonder de memory-flag crasht de suite in `intervention/image`. Baseline vóór dit werk, op deze basis: **213 tests, 815 assertions, 1 skipped, OK**.
- **Deze branch staat op `service-page`, niet op `main`.** Zie "Samenloop" hieronder. `offerte-page` kan pas naar main nadat `service-page` daar is.
- **Nooit een Tailwind-klassenaam interpoleren.** Tailwind's scanner leest broncode statisch. Schrijf elke klassenstring voluit in elke tak van een `{{ if }}`.
- **Tailwind-utilities verliezen van element-basisregels.** `resources/css/base/typography.css` staat ongelaagd, Tailwind's utilities staan in `@layer utilities`, en ongelaagde CSS wint altijd van gelaagde. `<h1 class="text-display">` doet dus **niets**. Gebruik `.header-title` uit `components/header.css` — die zet `font-size` rechtstreeks. Zelfde verhaal voor `<p class="text-lg">`.
- **`{{ field class="…" }}` bestaat niet.** Antlers parseert dat als modifier en gooit `Modifier [class] not found`. Fieldtype-views accepteren geen class-attribuut; `form.css` stijlt de controls via `.form-field`.
- **`{{ if }}`-blokken lekken witruimte** — de indentatie vóór de tag en de newline erna blijven staan als de conditie onwaar is.
- **`app/Fieldtypes/` wordt automatisch geregistreerd.** `Statamic\Providers\ExtensionServiceProvider::registerAppExtensions()` scant `app/Fieldtypes`, `app/Tags` en `app/Modifiers` en roept `::register()` aan op elke klasse die van het juiste basistype erft. Er is **geen** wijziging in `AppServiceProvider` nodig — de spec zei van wel, dat klopt niet.
- **De fieldtype-handle volgt uit de klassenaam.** `Fieldtype::handle()` doet `Str::removeRight(traitHandle(), '_fieldtype')` op de snake_case-klassenaam: `RangeCheckboxes` → `range_checkboxes`, `LocationSelect` → `location_select`.
- **Taal:** codecommentaar en contentcopy in het Nederlands. Commitberichten in het Nederlands.
- **Kleuren uit Figma `318:3956`.** De variabelen op die node zijn precies vier: `accent #f8d71c`, `black #121b22`, `white #ffffff`, `light #f1f6f8`. De kaart is `light`, de invoervelden zijn `white`. Dat is het enige verschil met het herstellingsformulier, dat `#f5f5f5`-velden op een witte kaart heeft.

## Samenloop met de andere branches

Deze branch is **op `service-page` gerebased**, niet op `main`. Reden: `service-page` heeft in commit `5c2ef22` `resources/css/components/form.css` herschreven tot herbruikbare klassen (`.form`, `.form-section`, `.form-grid`, `.form-field`, `.form-field--half`, `.form-label`, `.form-select-wrap`, `.form-dropzone`, `.form-error`) plus een werkende dropzone. Dat is bijna alles wat `/offerte` nodig heeft. Op `main` bouwen zou een derde formulierimplementatie opleveren.

Gevolgen:

- **Raak `resources/css/components/form.css` in dit plan niet aan.** Alle offerte-specifieke opmaak gaat in `offerte-form.css`, dat ná `form.css` geïmporteerd wordt.
- **Raak `resources/views/partials/reparationForm.antlers.html` niet aan.** Zie het openstaande punt onderaan over het samenvoegen van de twee filiaal-selects.
- `offerte-page` kan pas naar `main` nadat `service-page` daar is. Verandert `service-page` in review nog aan `form.css`, dan verandert de basis van deze branch mee.
- `contact-page` raakt geen van beide bestanden die dit plan aanpast.

## Afwijkingen van de spec

Vijf punten. Alle vijf komen voort uit code die na het schrijven van de spec op `service-page` is geland, of uit verificatie tegen de runtime. Ze maken het werk kleiner.

1. **Het formulier gebruikt de generieke `{{ sections }}`/`{{ fields }}`-loop.** De spec koos voor alles met de hand uitschrijven, met het tweekolomsraster als argument. `reparationForm.antlers.html` laat zien dat de loop dat aankan via `width: 50` in het blueprint, met een `{{ if handle == … }}`-uitzondering per bijzonder veld. Een tweede patroon naast dat ene zou slechter zijn.
2. **Er komt geen JavaScript.** De spec plande `offerte-upload.js` voor drag-and-drop. Onnodig: in `.form-dropzone` ligt de `<input type="file">` transparant over de hele zone, en browsers accepteren een drop rechtstreeks op een file-input. Slepen én klikken werken dus zonder JS. De actieve staat van de pillen gaat via `:has(input:checked)` in CSS, ook zonder JS.
3. **Geen registratie in `AppServiceProvider`.** `app/Fieldtypes/` wordt automatisch gescand; zie Global Constraints.
4. **De pillen staan buiten `.form-field`.** De spec zei niets over de wrapper. Dit is bewust: `form.css` bevat `.form-field :is(input:not([type='file']), textarea, select) { … }`, en dat zou de checkbox-inputs in de pillen een volle-breedte grijze invoervulling geven. Ze in een eigen `.offerte-products`-wrapper zetten haalt ze buiten het bereik van die selector, in plaats van er met specificiteit tegenaan te duwen.
5. **De `in:`-regel voor `products` gaat via `extraRules()`, niet via `rules()`.** `Statamic\Fields\Field::rules()` hangt `rules()` aan de veld-handle zelf; voor een array moet de regel op `products.*` staan, en dat kan alleen via `extraRules()`. Geverifieerd tegen `Fieldtypes/DictionaryFields.php`, dat dezelfde `$this->field->handle().'.…'`-sleutel gebruikt.

---

## Bestandsoverzicht

| Bestand | Taak | Verantwoordelijkheid |
|---|---|---|
| `app/Fieldtypes/RangeCheckboxes.php` | 1 | opties + `in:`-regel uit de `ranges`-collectie |
| `tests/Unit/Fieldtypes/RangeCheckboxesTest.php` | 1 | pint lijst, volgorde en regel vast |
| `app/Fieldtypes/LocationSelect.php` | 2 | opties + `in:`-regel uit de `locations`-collectie |
| `tests/Unit/Fieldtypes/LocationSelectTest.php` | 2 | pint lijst, volgorde en regel vast |
| `resources/blueprints/forms/offerte.yaml` | 3 | de acht velden |
| `resources/forms/offerte.yaml` | 3 | titel + e-mailnotificatie |
| `resources/views/partials/offerteForm.antlers.html` | 3 | de formulierkaart |
| `resources/css/components/offerte-form.css` | 3, 4 | pillen, kaart, paginaraster |
| `resources/css/site.css` | 3 | import van `offerte-form.css` |
| `tests/Feature/Sections/OfferteFormTest.php` | 3 | markup van het formulier |
| `tests/Feature/Content/OfferteFormBlueprintTest.php` | 3 | types, verplicht/optioneel, container |
| `resources/blueprints/collections/pages/offerte.yaml` | 4 | intro + beeld + page builder |
| `content/collections/pages/offerte.md` | 4, 5 | de entry, en in taak 5 de CTA |
| `resources/views/offerte.antlers.html` | 4 | het template |
| `tests/Feature/Content/OffertePageTest.php` | 4, 5 | entry, blueprint, render, CTA |
| `content/collections/quicklinks/vraag-offerte-aan.md` | 5 | link naar de nieuwe entry |
| `content/collections/pages/realisaties.md` | 5 | cta-link naar de nieuwe entry |

---

### Task 1: `RangeCheckboxes`-fieldtype

**Files:**
- Create: `app/Fieldtypes/RangeCheckboxes.php`
- Test: `tests/Unit/Fieldtypes/RangeCheckboxesTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken.
- Produces: fieldtype-handle `range_checkboxes`, bruikbaar als `type:` in een form-blueprint. Levert aan de `{{ fields }}`-loop een `options`-map `slug => titel` (via de geërfde `extraRenderableFieldData()`). Taak 3 gebruikt beide.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Unit/Fieldtypes/RangeCheckboxesTest.php`:

```php
<?php

namespace Tests\Unit\Fieldtypes;

use Statamic\Fields\Field;
use Tests\TestCase;

class RangeCheckboxesTest extends TestCase
{
    /**
     * De volgorde is die van het `order`-veld op de ranges-entries (1 t/m 9,
     * uniek over alle negen), niet de volgorde uit het Figma-ontwerp. Die
     * laatste is willekeurige vulling en zou de lijst hier opnieuw
     * hardcoderen.
     */
    public function test_the_options_are_the_ranges_in_their_own_order(): void
    {
        $field = new Field('products', ['type' => 'range_checkboxes']);

        $this->assertSame([
            'ramen-en-deuren' => 'Ramen en deuren',
            'stalen-binnendeuren' => 'Stalen binnendeuren',
            'velux' => 'VELUX dakramen',
            'airco' => 'Airco',
            'rolluiken' => 'Rolluiken',
            'zonwering' => 'Zonwering',
            'pergolas' => "Terrasoverkappingen & pergola's",
            'garagepoorten' => 'Garagepoorten',
            'somfy-smart-home' => 'Somfy Smart Home',
        ], $field->fieldtype()->extraRenderableFieldData()['options']);
    }

    /**
     * De regel hoort op `products.*` en niet op `products`: het is een array.
     * Zonder deze regel kan een vervalste POST willekeurige tekst in de
     * notificatiemail zetten.
     */
    public function test_a_forged_product_value_is_rejected(): void
    {
        $field = new Field('products', ['type' => 'range_checkboxes']);

        $this->assertContains(
            'in:ramen-en-deuren,stalen-binnendeuren,velux,airco,rolluiken,zonwering,pergolas,garagepoorten,somfy-smart-home',
            $field->rules()['products.*'],
        );
    }

    /**
     * De labels in de CP-submissielijst en in de notificatiemail komen van
     * `getLabel()`, dat op dezelfde opties leunt. Rendert dit ruwe slugs, dan
     * is de koppeling met de collectie stuk.
     */
    public function test_a_stored_slug_augments_to_its_title(): void
    {
        $field = new Field('products', ['type' => 'range_checkboxes']);

        $augmented = $field->fieldtype()->augment(['rolluiken']);

        $this->assertSame('Rolluiken', $augmented[0]['label']);
    }
}
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=RangeCheckboxesTest`
Expected: FAIL. Statamic kent de handle `range_checkboxes` niet, dus `$field->fieldtype()` gooit `Statamic\Exceptions\FieldtypeNotFoundException`.

- [ ] **Step 3: Schrijf de fieldtype**

Maak `app/Fieldtypes/RangeCheckboxes.php`:

```php
<?php

namespace App\Fieldtypes;

use Statamic\Facades\Entry;
use Statamic\Fieldtypes\Checkboxes;

/**
 * Checkboxes waarvan de opties uit de `ranges`-collectie komen, zodat de
 * productlijst niet naast die collectie een tweede keer in YAML bestaat.
 *
 * Eén overschreven `getOptions()` volstaat voor drie dingen, omdat de trait
 * `Statamic\Fieldtypes\HasSelectOptions` alles daaruit afleidt en die
 * aanroepen via `$this->` lopen:
 *
 *   - `extraRenderableFieldData()` geeft de opties door aan de
 *     `{{ fields }}`-loop in Antlers;
 *   - `getLabel()` zet een opgeslagen slug om naar de titel, waardoor de
 *     CP-submissielijst en de notificatiemail "Rolluiken" tonen in plaats
 *     van `rolluiken`.
 *
 * De opgeslagen waarde is de slug en niet de entry-id: die blijft leesbaar
 * in de mail en in een CSV-export, en overleeft het opnieuw aanmaken van
 * een entry.
 *
 * De handle `range_checkboxes` volgt uit de klassenaam
 * (`Fieldtype::handle()`), en `app/Fieldtypes` wordt automatisch gescand
 * door Statamic's ExtensionServiceProvider. Er is dus geen registratie
 * nodig.
 */
class RangeCheckboxes extends Checkboxes
{
    protected function getOptions(): array
    {
        return $this->ranges()
            ->map(fn ($entry) => ['value' => $entry->slug(), 'label' => $entry->get('title')])
            ->values()
            ->all();
    }

    /**
     * `Statamic\Fields\Field::rules()` hangt wat `rules()` teruggeeft aan de
     * veld-handle zelf. Voor een array moet de regel op `products.*` staan,
     * en dat kan alleen via `extraRules()`.
     */
    public function extraRules(): array
    {
        return [
            $this->field->handle().'.*' => 'in:'.$this->ranges()->map->slug()->implode(','),
        ];
    }

    /**
     * `order` is op de ranges-blueprint beschreven als volgorde binnen de
     * categorie, maar loopt in de praktijk uniek van 1 tot 9 over alle negen
     * entries en werkt dus als globale volgorde.
     */
    private function ranges()
    {
        return Entry::query()
            ->where('collection', 'ranges')
            ->orderBy('order')
            ->get();
    }
}
```

- [ ] **Step 4: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=RangeCheckboxesTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: OK, 216 tests, 1 skipped.

- [ ] **Step 6: Commit**

```bash
git add app/Fieldtypes/RangeCheckboxes.php tests/Unit/Fieldtypes/RangeCheckboxesTest.php
git commit -m "feat(offerte): fieldtype dat zijn opties uit de ranges-collectie haalt"
```

---

### Task 2: `LocationSelect`-fieldtype

**Files:**
- Create: `app/Fieldtypes/LocationSelect.php`
- Test: `tests/Unit/Fieldtypes/LocationSelectTest.php`

**Interfaces:**
- Consumes: niets uit taak 1. De twee fieldtypes zijn onafhankelijk.
- Produces: fieldtype-handle `location_select`. Omdat het van `Select` erft, rendert `{{ field }}` er een volledige `<select>` mee, inclusief `placeholder`-optie, `required` en `aria-invalid`. Taak 3 leunt daarop en schrijft dus géén select met de hand.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Unit/Fieldtypes/LocationSelectTest.php`:

```php
<?php

namespace Tests\Unit\Fieldtypes;

use Statamic\Fields\Field;
use Tests\TestCase;

class LocationSelectTest extends TestCase
{
    /**
     * De volgorde is die van de structuurboom
     * (content/trees/collections/locations.yaml), niet alfabetisch: het
     * ontwerp toont Dilbeek, Sint-Pieters-Leeuw, Aartselaar in die volgorde.
     */
    public function test_the_options_are_the_locations_in_tree_order(): void
    {
        $field = new Field('location', ['type' => 'location_select']);

        $this->assertSame([
            'winsol-dilbeek' => 'Winsol Dilbeek',
            'winsol-sint-pieters-leeuw' => 'Winsol Sint-Pieters-Leeuw',
            'winsol-aartselaar' => 'Winsol Aartselaar',
        ], $field->fieldtype()->extraRenderableFieldData()['options']);
    }

    /**
     * Enkelvoudige waarde, dus de regel hoort op de handle zelf — anders dan
     * bij RangeCheckboxes, dat een array is.
     */
    public function test_a_forged_location_value_is_rejected(): void
    {
        $field = new Field('location', ['type' => 'location_select']);

        $this->assertContains(
            'in:winsol-dilbeek,winsol-sint-pieters-leeuw,winsol-aartselaar',
            $field->rules()['location'],
        );
    }

    /**
     * Het veld is optioneel. Zonder `nullable` zou een lege keuze op de
     * `in:`-regel stuklopen.
     */
    public function test_an_empty_choice_stays_allowed(): void
    {
        $field = new Field('location', ['type' => 'location_select']);

        $this->assertContains('nullable', $field->rules()['location']);
    }
}
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=LocationSelectTest`
Expected: FAIL met `FieldtypeNotFoundException` op `location_select`.

- [ ] **Step 3: Schrijf de fieldtype**

Maak `app/Fieldtypes/LocationSelect.php`:

```php
<?php

namespace App\Fieldtypes;

use Statamic\Facades\Entry;
use Statamic\Fieldtypes\Select;

/**
 * Select waarvan de opties uit de `locations`-collectie komen. Zelfde
 * constructie en zelfde reden als App\Fieldtypes\RangeCheckboxes; zie de
 * toelichting daar over waarom één overschreven `getOptions()` volstaat.
 *
 * Anders dan bij RangeCheckboxes staat de `in:`-regel hier op de handle
 * zelf: dit is één waarde, geen array. `Statamic\Fields\Field::rules()`
 * voegt zelf `nullable` toe zolang het veld niet verplicht is.
 */
class LocationSelect extends Select
{
    protected function getOptions(): array
    {
        return $this->locations()
            ->map(fn ($entry) => ['value' => $entry->slug(), 'label' => $entry->get('name')])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return ['in:'.$this->locations()->map->slug()->implode(',')];
    }

    /**
     * `locations` is een gestructureerde collectie; `orderBy('order')` volgt
     * daar de boom uit content/trees/collections/locations.yaml.
     */
    private function locations()
    {
        return Entry::query()
            ->where('collection', 'locations')
            ->orderBy('order')
            ->get();
    }
}
```

- [ ] **Step 4: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=LocationSelectTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: OK, 219 tests, 1 skipped.

- [ ] **Step 6: Commit**

```bash
git add app/Fieldtypes/LocationSelect.php tests/Unit/Fieldtypes/LocationSelectTest.php
git commit -m "feat(offerte): fieldtype dat zijn opties uit de locations-collectie haalt"
```

---

### Task 3: Het formulier

**Files:**
- Create: `resources/blueprints/forms/offerte.yaml`
- Create: `resources/forms/offerte.yaml`
- Create: `resources/views/partials/offerteForm.antlers.html`
- Create: `resources/css/components/offerte-form.css`
- Modify: `resources/css/site.css` (import erbij, ná `form.css`)
- Test: `tests/Feature/Sections/OfferteFormTest.php`
- Test: `tests/Feature/Content/OfferteFormBlueprintTest.php`

**Interfaces:**
- Consumes: `range_checkboxes` uit taak 1 en `location_select` uit taak 2. Verder de klassen die `service-page` in `form.css` heeft gezet: `.form`, `.form-section`, `.form-grid`, `.form-field`, `.form-field--half`, `.form-label`, `.form-select-wrap`, `.form-dropzone`, `.form-error`.
- Produces: `{{ partial:offerteForm }}` — geen argumenten. Rendert `<form class="form offerte-form">`. Taak 4 include't dit als tweede kind van het paginaraster.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Sections/OfferteFormTest.php`:

```php
<?php

namespace Tests\Feature\Sections;

class OfferteFormTest extends SectionTestCase
{
    public function test_renders_every_field_from_the_blueprint(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        foreach (['location', 'name', 'phone', 'email', 'postal_code', 'project', 'attachment'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }

        // products is een array, dus de naam draagt haakjes.
        $this->assertStringContainsString('name="products[]"', $html);
    }

    /**
     * Negen pillen met de titels uit de ranges-collectie. Dit is de assertie
     * die stukgaat als de koppeling met die collectie wegvalt.
     */
    public function test_the_product_pills_come_from_the_ranges_collection(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertSame(9, substr_count($html, 'offerte-pill"'));

        foreach (['Ramen en deuren', 'VELUX dakramen', "Terrasoverkappingen &amp; pergola's", 'Somfy Smart Home'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
    }

    public function test_the_branch_options_come_from_the_locations_collection(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
        $this->assertStringContainsString('Kies een filiaal…', $html);
    }

    /**
     * name+phone en email+postal_code staan in het ontwerp naast elkaar. De
     * klasse volgt uit `width: 50` in het blueprint, niet uit het template.
     */
    public function test_marks_exactly_the_four_paired_fields_as_half_width(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertSame(4, substr_count($html, 'form-field--half'));
    }

    public function test_the_attachment_field_is_a_file_input_inside_the_dropzone(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('form-dropzone', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('Sleep een foto hierheen of klik om te uploaden', $html);
    }

    /**
     * Statamic zet enctype alleen als het blueprint een assets-veld heeft.
     * Valt `attachment` weg, dan verdwijnt dit attribuut stil en uploadt de
     * browser alleen de bestandsnaam.
     */
    public function test_accepts_file_uploads(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function test_carries_a_honeypot(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('name="honeypot"', $html);
    }

    /**
     * De pillen staan bewust buiten `.form-field`: form.css stijlt daarbinnen
     * elke input met een volle-breedte grijze vulling, en dat zou de
     * checkboxes van de pillen raken.
     *
     * `products` is het eerste veld in het blueprint, dus alle pillen horen
     * vóór de eerste `.form-field`-wrapper te staan. Dat is de assertie —
     * niet een vast aantal tekens vanaf `offerte-products`, want de pillen
     * groeien mee met de collectie.
     */
    public function test_the_pills_stay_outside_the_generic_field_wrapper(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $firstFieldWrapper = strpos($html, 'class="form-field');

        $this->assertNotFalse($firstFieldWrapper, 'Geen enkel veld gebruikt de generieke wrapper.');
        $this->assertLessThan($firstFieldWrapper, strpos($html, 'offerte-products'));
        $this->assertLessThan($firstFieldWrapper, strrpos($html, 'offerte-pill"'));
    }
}
```

Maak daarnaast `tests/Feature/Content/OfferteFormBlueprintTest.php`. Dit dekt wat de markup-test niet ziet: de container van de upload en de verdeling verplicht/optioneel.

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Form;
use Tests\TestCase;

class OfferteFormBlueprintTest extends TestCase
{
    public function test_every_field_carries_its_agreed_type(): void
    {
        $fields = Form::find('offerte')->blueprint()->fields()->all();

        $this->assertSame('range_checkboxes', $fields->get('products')->type());
        $this->assertSame('location_select', $fields->get('location')->type());
        $this->assertSame('textarea', $fields->get('project')->type());
        $this->assertSame('assets', $fields->get('attachment')->type());
    }

    /**
     * Naam, e-mail en minstens een product zijn verplicht; de andere vijf
     * niet. Verhardt de drempel per ongeluk, dan kost dat conversie.
     */
    public function test_exactly_three_fields_are_required(): void
    {
        $fields = Form::find('offerte')->blueprint()->fields()->all();

        foreach (['products', 'name', 'email'] as $handle) {
            $this->assertTrue($fields->get($handle)->isRequired(), "{$handle} hoort verplicht te zijn.");
        }

        foreach (['location', 'phone', 'postal_code', 'project', 'attachment'] as $handle) {
            $this->assertFalse($fields->get($handle)->isRequired(), "{$handle} hoort optioneel te zijn.");
        }
    }

    /**
     * Klantfoto's en bouwplannen horen niet op een raadbare publieke URL.
     * Verschuift dit naar `assets`, dan is elke upload publiek leesbaar.
     */
    public function test_uploads_land_in_the_private_container(): void
    {
        $attachment = Form::find('offerte')->blueprint()->fields()->all()->get('attachment');

        $this->assertSame('private', $attachment->get('container'));
        $this->assertSame(1, $attachment->get('max_files'));
    }
}
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=OfferteFormTest`
Expected: FAIL — de partial `offerteForm` bestaat niet.

- [ ] **Step 3: Schrijf het form-blueprint**

Maak `resources/blueprints/forms/offerte.yaml`:

```yaml
title: Offerte
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: products
            field:
              type: range_checkboxes
              display: 'Voor welke producten?'
              validate:
                - required
          -
            handle: location
            field:
              type: location_select
              display: 'Naar welk filiaal?'
              placeholder: 'Kies een filiaal…'
          -
            handle: name
            field:
              type: text
              display: Naam
              placeholder: 'Voor- en achternaam'
              width: 50
              validate:
                - required
          -
            handle: phone
            field:
              type: text
              input_type: tel
              display: Telefoon
              placeholder: '+32 …'
              width: 50
          -
            handle: email
            field:
              type: text
              input_type: email
              display: E-mail
              placeholder: 'naam@voorbeeld.be'
              width: 50
              validate:
                - required
                - email
          -
            handle: postal_code
            field:
              type: text
              display: Postcode
              placeholder: 'bv. 1700'
              width: 50
          -
            handle: project
            field:
              type: textarea
              display: 'Vertel kort over uw project'
              placeholder: 'Wat wilt u laten plaatsen? Afmetingen, wensen, timing…'
          -
            handle: attachment
            field:
              type: assets
              container: private
              folder: offertes
              max_files: 1
              display: 'Foto of plan toevoegen (optioneel)'
```

Eén sectie, geen tweede: het ontwerp toont geen scheidingslijn in deze kaart.

- [ ] **Step 4: Schrijf de formulierconfiguratie**

Maak `resources/forms/offerte.yaml`:

```yaml
title: Offerte
email:
  -
    id: offerte-notification
    to: hello@stuw.agency
    reply_to: '{{ email }}'
    subject: 'Nieuwe offerteaanvraag van {{ name }}'
```

`hello@stuw.agency` is dezelfde placeholder als in `contact.yaml`; het echte adres moet nog van de klant komen. `reply_to` is de reden dat de handle van het e-mailveld `email` moet heten.

- [ ] **Step 5: Schrijf de partial**

Maak `resources/views/partials/offerteForm.antlers.html`:

```antlers
{{#
    Het offerteformulier (Figma 318:3956).

    Zelfde opzet als partials/reparationForm.antlers.html: de veldloop is
    generiek en leest labels, breedtes en placeholders uit
    resources/blueprints/forms/offerte.yaml. `{{ width }}` zet Statamic
    altijd, met 100 als default.

    Twee velden wijken af van de generieke tak, en één valt zelfs buiten de
    veldwrapper:

    - `products`: pillen in plaats van een checkboxlijst. De vendor-view
      (vendor/statamic/cms/resources/views/extend/forms/fields/
      checkboxes.antlers.html) rendert een kale `<label><input><br>` en
      accepteert geen classes, dus die markup staat hier. De opties komen uit
      App\Fieldtypes\RangeCheckboxes en dus uit de ranges-collectie.

      Dit blok staat bewust NIET in `.form-field`. form.css bevat
      `.form-field :is(input:not([type='file']), textarea, select)`, wat de
      checkboxes van de pillen een volle-breedte grijze vulling zou geven.
      Een eigen wrapper houdt ze buiten het bereik van die selector, in
      plaats van er met specificiteit tegenaan te duwen.

      Er staat hier geen `<input type="hidden" name="products[]">` zoals in
      de vendor-view. Die is er om een waarde te kunnen wissen bij het
      bewerken van een entry, maar zou hier de required-melding kapotmaken:
      het veld is dan nooit leeg, dus `required` vuurt niet en de bezoeker
      krijgt de `in:`-fout te zien in plaats van "dit veld is verplicht".

    - `location`: alleen een wrapper met caret eromheen. De `<select>` zelf
      komt van `{{ field }}`, omdat App\Fieldtypes\LocationSelect de opties
      aanlevert. Daardoor komen `required`, `aria-invalid` en de
      placeholder-optie uit de vendor-view en hoeven ze hier niet met de
      hand nagebouwd te worden.

    - `attachment`: de streepjeszone. De `<input type="file">` ligt
      transparant over de hele zone; browsers accepteren drag-and-drop
      rechtstreeks op een file-input, dus slepen én klikken werken zonder
      JavaScript.

    Nergens `{{ field class="…" }}`: Antlers parseert dat als modifier en
    gooit "Modifier [class] not found".
#}}
{{ form:offerte class="form offerte-form" }}
    {{ sections }}
        <div class="form-section">
            <div class="form-grid">
                {{ fields }}
                    {{ if handle == "products" }}
                        <div class="offerte-products">
                            <span class="form-label" id="{{ id }}-label">{{ display }}</span>

                            <ul class="offerte-pills" role="group" aria-labelledby="{{ id }}-label">
                                {{ foreach:options as="option|label" }}
                                    <li>
                                        <label class="offerte-pill">
                                            <input type="checkbox" name="{{ name }}[]" value="{{ option }}"{{ if value|in_array:option }} checked{{ /if }}>
                                            {{ icon src="check" class="offerte-pill__check" }}
                                            <span>{{ label }}</span>
                                        </label>
                                    </li>
                                {{ /foreach:options }}
                            </ul>

                            {{ if error }}<p class="form-error" id="{{ id }}-error">{{ error }}</p>{{ /if }}
                        </div>
                    {{ else }}
                        <div class="form-field{{ if width == 50 }} form-field--half{{ /if }}">
                            <label class="form-label" for="{{ id }}">{{ display }}</label>

                            {{ if handle == "location" }}
                                <div class="form-select-wrap">
                                    {{ field }}
                                    {{ icon src="caret-down" }}
                                </div>
                            {{ elseif handle == "attachment" }}
                                <div class="form-dropzone">
                                    {{ icon src="upload" class="size-7" }}
                                    <span>Sleep een foto hierheen of klik om te uploaden</span>
                                    {{ field }}
                                </div>
                            {{ else }}
                                {{ field }}
                            {{ /if }}

                            {{ if error }}<p class="form-error" id="{{ id }}-error">{{ error }}</p>{{ /if }}
                        </div>
                    {{ /if }}
                {{ /fields }}
            </div>
        </div>
    {{ /sections }}

    <input type="text" class="hidden" name="{{ honeypot ?? 'honeypot' }}">

    {{ if success }}
        <div class="offerte-success">
            <h2>Uw aanvraag is verstuurd</h2>
            <p>Een lokale expert bekijkt uw vraag en neemt binnen twee werkdagen contact op. Ondertussen kunt u alvast rondkijken bij wat we eerder plaatsten.</p>
            <a class="btn btn--accent" href="/realisaties">Naar realisaties</a>
        </div>
    {{ else }}
        <button type="submit" class="btn btn--accent">Vraag offerte aan</button>
    {{ /if }}
{{ /form:offerte }}
```

- [ ] **Step 6: Schrijf de CSS**

Maak `resources/css/components/offerte-form.css`:

```css
/*
 * Offertepagina (Figma 318:3956).
 *
 * Alleen wat hier anders is dan het herstellingsformulier. De velden,
 * labels, dropzone en foutmeldingen komen uit components/form.css; dat
 * bestand blijft ongemoeid.
 *
 * Het enige echte verschil in het ontwerp: daar staan grijze velden op een
 * witte kaart, hier witte velden op een `light`-kaart. De variabelen op de
 * Figma-node zijn precies vier — accent, black, white en light — dus dit is
 * geen benadering.
 */

.offerte-form {
    @apply rounded-md bg-light card-padding lg:p-10;
}

/*
 * Wint van `.form-field :is(input…)` in form.css: gelijke specificiteit
 * (0,2,1), en offerte-form.css wordt ná form.css geïmporteerd. Beide staan
 * ongelaagd, dus de bronvolgorde beslist. Verplaats de import in site.css
 * dus niet naar boven.
 */
.offerte-form .form-field :is(input:not([type='file']), textarea, select) {
    @apply bg-white;
}

.offerte-products {
    @apply flex flex-col gap-2 sm:col-span-2;
}

.offerte-pills {
    @apply flex flex-wrap gap-2;
}

/*
 * De actieve staat komt volledig uit CSS, zonder JavaScript: `:has()` leest
 * de checkbox die in de label zit. Vorm en actieve kleuren zijn dezelfde
 * als `.range-filter__btn`, maar dit is een eigen klasse — dat zijn
 * filterlinks, dit zijn checkboxes met een vinkje.
 */
.offerte-pill {
    @apply inline-flex cursor-pointer items-center gap-2 rounded-full bg-white px-5 py-3 font-semibold text-black;

    font-size: clamp(0.6875rem, 0.554rem + 0.335vw, 0.875rem);
    line-height: 1.1;
}

.offerte-pill input[type='checkbox'] {
    @apply sr-only;
}

.offerte-pill:has(input:checked) {
    @apply bg-black text-white;
}

.offerte-pill__check {
    @apply hidden size-4;
}

.offerte-pill:has(input:checked) .offerte-pill__check {
    @apply block;
}

.offerte-success {
    @apply flex flex-col items-start gap-4;
}

/* --- Paginaraster (taak 4) --- */

/*
 * Twee kolommen vanaf `lg`: links de titel op rij 1 en het stilleven op rij
 * 2, rechts de formulierkaart over beide rijen. Onder `lg` valt alles terug
 * op de DOM-volgorde, en die is bewust titel → formulier → beeld: het
 * formulier is het doel van de pagina en het beeld is decoratief.
 *
 * Er is geen mobile-frame voor deze pagina in Figma; deze volgorde is een
 * keuze uit de spec, geen meting.
 */
.offerte-layout {
    @apply grid gap-10 lg:grid-cols-12 lg:gap-8;
}

.offerte-heading {
    @apply flex flex-col gap-6 lg:col-span-4 lg:col-start-1 lg:row-start-1;
}

.offerte-still {
    @apply lg:col-span-4 lg:col-start-1 lg:row-start-2 lg:self-end;
}

.offerte-form {
    @apply lg:col-span-7 lg:col-start-6 lg:row-span-2 lg:row-start-1;
}
```

- [ ] **Step 7: Voeg de import toe**

In `resources/css/site.css`, ná de bestaande `@import './components/form.css';` en onderaan de componentenlijst:

```css
@import './components/offerte-form.css';
```

- [ ] **Step 8: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=OfferteForm`
Expected: PASS, 11 tests (8 markup + 3 blueprint). Het filter zonder `Test`-achtervoegsel pakt beide klassen.

- [ ] **Step 9: Bouw de assets en draai de volledige suite**

Run: `npm run build && php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: OK, 230 tests, 1 skipped.

- [ ] **Step 10: Commit**

```bash
git add resources/blueprints/forms/offerte.yaml resources/forms/offerte.yaml resources/views/partials/offerteForm.antlers.html resources/css/components/offerte-form.css resources/css/site.css tests/Feature/Sections/OfferteFormTest.php tests/Feature/Content/OfferteFormBlueprintTest.php
git commit -m "feat(offerte): het offerteformulier met producten uit de ranges-collectie"
```

---

### Task 4: De pagina

**Files:**
- Create: `resources/blueprints/collections/pages/offerte.yaml`
- Create: `content/collections/pages/offerte.md`
- Create: `resources/views/offerte.antlers.html`
- Test: `tests/Feature/Content/OffertePageTest.php`

**Interfaces:**
- Consumes: `{{ partial:offerteForm }}` uit taak 3, en de rasterklassen `.offerte-layout`, `.offerte-heading`, `.offerte-still` uit hetzelfde bestand.
- Produces: de entry `offerte` met een gevulde `page_builder`-sleutel waar taak 5 de CTA in zet.

- [ ] **Step 1: Schrijf de falende test**

Maak `tests/Feature/Content/OffertePageTest.php`:

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class OffertePageTest extends TestCase
{
    public function test_the_entry_exists_on_its_own_blueprint_and_template(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();

        $this->assertNotNull($entry, 'De offerte-entry ontbreekt.');
        $this->assertSame('offerte', $entry->blueprint()->handle());
        $this->assertSame('offerte', $entry->get('template'));
    }

    /**
     * Het stilleven uit de briefing. Staat het pad hier niet vast, dan valt
     * het beeld stil weg zonder dat iets faalt.
     */
    public function test_the_still_life_image_is_set(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();

        $this->assertSame('quicklinks/offerte-2.png', $entry->get('image'));
    }

    public function test_the_page_renders_the_heading_the_form_and_the_image(): void
    {
        $html = $this->get('/offerte')->assertOk()->getContent();

        $this->assertStringContainsString('Vraag een offerte aan', $html);
        $this->assertStringContainsString('offerte-form', $html);
        $this->assertStringContainsString('offerte-still', $html);
    }

    /**
     * De DOM-volgorde is de mobiele volgorde: kop, formulier, beeld. Op
     * desktop verzet het raster de kolommen. Draait dit om, dan duwt het
     * beeld het formulier onder de vouw op telefoon.
     */
    public function test_the_form_comes_before_the_image_in_the_markup(): void
    {
        $html = $this->get('/offerte')->getContent();

        $this->assertLessThan(
            strpos($html, 'offerte-still'),
            strpos($html, 'offerte-form'),
        );
    }

    /**
     * De H1 moet `.header-title` dragen en niet `text-display`: Tailwind's
     * utilities staan in `@layer utilities` en verliezen van de ongelaagde
     * `h1`-basisregel in base/typography.css.
     */
    public function test_the_heading_uses_the_unlayered_display_size(): void
    {
        $html = $this->get('/offerte')->getContent();

        $this->assertStringContainsString('<h1 class="header-title">', $html);
    }
}
```

- [ ] **Step 2: Draai de test en controleer dat hij faalt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=OffertePageTest`
Expected: FAIL — de entry bestaat niet, dus `assertNotNull` slaat aan en `/offerte` geeft 404.

- [ ] **Step 3: Schrijf het paginablueprint**

Maak `resources/blueprints/collections/pages/offerte.yaml`:

```yaml
title: Offerte
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            import: page_intro
          -
            import: image
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

- [ ] **Step 4: Schrijf de entry**

Maak `content/collections/pages/offerte.md`:

```yaml
---
id: b7c8d9e0-0003-4f5a-8b6c-7d8e9f0a1b02
blueprint: offerte
title: 'Vraag een offerte aan'
text: 'Vrijblijvend en zonder verplichting. Vul kort in wat u zoekt — een lokale expert rekent het voor u door en neemt contact op.'
image: quicklinks/offerte-2.png
seo_noindex: false
template: offerte
---
```

Titel en intro komen letterlijk uit Figma `318:3984`.

Zet de entry ook in de paginaboom. In `content/trees/collections/pages.yaml`, onderaan de `tree`-lijst:

```yaml
  -
    entry: b7c8d9e0-0003-4f5a-8b6c-7d8e9f0a1b02
```

- [ ] **Step 5: Schrijf het template**

Maak `resources/views/offerte.antlers.html`:

```antlers
{{#
    De offertepagina (Figma 318:3956).

    Géén `{{ partial:headers/default }}`: die zet titel en tekst gecentreerd
    in een smalle kolom, terwijl ze hier links naast het formulier staan.

    De H1 draagt `.header-title` en niet `text-display`. Tailwind's utilities
    staan in `@layer utilities` en ongelaagde CSS wint daarvan, dus de
    `h1`-basisregel uit base/typography.css zou een `text-*`-utility
    overrulen. Zie de toelichting bovenaan components/header.css.

    De volgorde in de markup — kop, formulier, beeld — is de mobiele
    volgorde. Vanaf `lg` verzet `.offerte-layout` het beeld terug naar de
    linkerkolom onder de kop.
#}}
<section class="section section--default">
    <div class="container">
        <div class="offerte-layout">
            <div class="offerte-heading">
                <h1 class="header-title">{{ title }}</h1>

                {{ if text }}<p>{{ text }}</p>{{ /if }}
            </div>

            {{ partial:offerteForm }}

            {{ if image }}
                <div class="offerte-still">
                    {{ img :src="image" max_width="1024" sizes="(min-width: 1024px) 30vw, 90vw" class="h-auto w-full object-contain" }}
                </div>
            {{ /if }}
        </div>
    </div>
</section>

{{ partial:pageBuilder }}
```

- [ ] **Step 6: Draai de test en controleer dat hij slaagt**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=OffertePageTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: OK, 235 tests, 1 skipped.

- [ ] **Step 8: Commit**

```bash
git add resources/blueprints/collections/pages/offerte.yaml content/collections/pages/offerte.md content/trees/collections/pages.yaml resources/views/offerte.antlers.html tests/Feature/Content/OffertePageTest.php
git commit -m "feat(offerte): de pagina met het formulier naast titel en stilleven"
```

---

### Task 5: De CTA en de bestaande verwijzingen

**Files:**
- Modify: `content/collections/pages/offerte.md` (`page_builder` erbij)
- Modify: `content/collections/quicklinks/vraag-offerte-aan.md`
- Modify: `content/collections/pages/realisaties.md`
- Test: `tests/Feature/Content/OffertePageTest.php` (drie tests erbij)

**Interfaces:**
- Consumes: de entry uit taak 4, en de bestaande `cta`-set uit `resources/fieldsets/page_builder.yaml` met `partials/sections/cta.antlers.html`.
- Produces: niets voor latere taken; dit is de laatste.

- [ ] **Step 1: Schrijf de falende tests**

Voeg toe aan `tests/Feature/Content/OffertePageTest.php`:

```php
    public function test_the_page_builder_holds_exactly_one_cta_pointing_at_realisaties(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();

        $builder = $entry->get('page_builder');

        $this->assertCount(1, $builder);
        $this->assertSame('cta', $builder[0]['type']);
        $this->assertSame('Nog niet klaar voor een offerte?', $builder[0]['title']);
        $this->assertSame('Naar realisaties', $builder[0]['link'][0]['label']);
        $this->assertSame(
            'c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03',
            $builder[0]['link'][0]['entry'][0],
        );
    }

    public function test_the_cta_renders_below_the_form(): void
    {
        $html = $this->get('/offerte')->getContent();

        $this->assertStringContainsString('data-section="cta"', $html);
        $this->assertLessThan(
            strpos($html, 'data-section="cta"'),
            strpos($html, 'offerte-form'),
        );
    }

    /**
     * Beide wezen naar /contact omdat /offerte nog niet bestond, terwijl hun
     * label "Vraag offerte aan" is.
     */
    public function test_the_existing_offerte_links_point_at_this_page(): void
    {
        $offerte = Entry::query()->where('collection', 'pages')->where('slug', 'offerte')->first();

        $quicklink = Entry::query()->where('collection', 'quicklinks')->where('slug', 'vraag-offerte-aan')->first();
        $this->assertSame($offerte->id(), $quicklink->get('link')[0]['entry'][0]);

        $realisaties = Entry::query()->where('collection', 'pages')->where('slug', 'realisaties')->first();
        $this->assertSame($offerte->id(), $realisaties->get('page_builder')[0]['link'][0]['entry'][0]);
    }
```

- [ ] **Step 2: Draai de tests en controleer dat ze falen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=OffertePageTest`
Expected: 3 failures. `page_builder` is nog leeg, en beide links wijzen nog naar de contact-entry `f0ee3161-1534-4986-9ef1-a92fccfba619`.

- [ ] **Step 3: Zet de CTA in de entry**

Voeg in `content/collections/pages/offerte.md`, in de front matter vóór `template:`, toe:

```yaml
page_builder:
  -
    id: offertecta
    type: cta
    overline: 'In de kijker'
    title: 'Nog niet klaar voor een offerte?'
    text: 'Bekijk eerst wat we bij anderen plaatsten — echte projecten in uw buurt, met de gekozen materialen en afwerking erbij.'
    image: dummy-images/test-img-12.jpg
    link:
      -
        type: entry
        entry:
          - c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03
        label: 'Naar realisaties'
        new_tab: false
```

Overline, titel en knoplabel komen letterlijk uit Figma. Alleen de body is herschreven: de tekst in het ontwerp is overgenomen van de CTA op `/realisaties` en vraagt daar om een offerte aan te vragen, wat vreemd leest op de pagina waar dat formulier al staat.

`test-img-12` is een willekeurige keuze uit de negentien dummy's, maar bewust niet `test-img-14` — dat is het beeld van de CTA op `/realisaties`, waar deze CTA naartoe linkt.

- [ ] **Step 4: Zet de twee bestaande links om**

In `content/collections/quicklinks/vraag-offerte-aan.md`, vervang in het `link`-blok:

```yaml
      - f0ee3161-1534-4986-9ef1-a92fccfba619
```

door:

```yaml
      - b7c8d9e0-0003-4f5a-8b6c-7d8e9f0a1b02
```

Doe exact hetzelfde in het `link`-blok van de `cta`-set in `content/collections/pages/realisaties.md`.

Laat de CTA op `content/collections/pages/aanbod.md` ongemoeid: die heet "Neem contact op" en bedoelt echt contact.

- [ ] **Step 5: Draai de tests en controleer dat ze slagen**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit --filter=OffertePageTest`
Expected: PASS, 8 tests.

- [ ] **Step 6: Draai de volledige suite**

Run: `php -d memory_limit=1G ./vendor/bin/phpunit`
Expected: OK, 238 tests, 1 skipped.

`QuicklinksContentTest` en `ProjectsOverviewPageTest` blijven groen: de eerste controleert het label en `type: entry` maar niet het doel, de tweede alleen dát er een `cta`-sectie rendert.

- [ ] **Step 7: Commit**

```bash
git add content/collections/pages/offerte.md content/collections/quicklinks/vraag-offerte-aan.md content/collections/pages/realisaties.md tests/Feature/Content/OffertePageTest.php
git commit -m "feat(offerte): de CTA onder het formulier en de links die hierheen wijzen"
```

---

## Open punten na dit plan

- **De twee filiaal-selects lopen uiteen.** `reparationForm.antlers.html` schrijft zijn `<select>` met de hand omdat er toen geen fieldtype was; `offerteForm` gebruikt `{{ field }}` met `App\Fieldtypes\LocationSelect`. Zodra beide branches op main staan, kan het herstellingsformulier op hetzelfde fieldtype over en verdwijnt daar een blok handgeschreven markup inclusief de nagebouwde `required`- en `aria-invalid`-condities. Niet in dit plan: dat raakt een bestand op een branch die nog in review is.
- **reCAPTCHA werkt nergens.** `app/Listeners/VerifyRecaptcha.php` wordt nooit geregistreerd — er is geen `Event::listen` voor `FormSubmitted` — en op `/contact` staat `{{ recaptcha }}` buiten het `<form>`, waardoor `input.closest('form')` null teruggeeft. Beide formulieren leunen nu op de honeypot alleen.
- **Notificaties gaan naar één vast adres.** Routeren per filiaal vraagt een e-mailveld op de locations-blueprint, drie echte adressen, en een fallback voor aanvragen zonder gekozen filiaal.
- **Een product voorinvullen via de URL.** Bewust niet gebouwd; de pagina start met alle pillen leeg. Een latere `?product=`-parameter kan dit toevoegen zonder de rest te raken.
- **De echte ontvanger en de globals.** `hello@stuw.agency` is de placeholder uit `contact.yaml`; `content/globals/` is nog leeg.
