# Structured data (JSON-LD) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eén sitewide JSON-LD `@graph` op elke pagina, opgebouwd uit data die al in de CMS staat.

**Architecture:** Een PHP-laag in `app/Schema/` bouwt PHP-arrays; `SchemaGraph` ontdubbelt op `@id` en encodeert met `json_encode`. Een custom Antlers-tag `{{ schema }}` bepaalt welke bouwers relevant zijn voor de huidige pagina en rendert één `<script type="application/ld+json">` in de `<head>`. Handmatige JSON-string-samenstelling komt nergens voor: dat is precies het escapingprobleem dat dit ontwerp wegneemt.

**Tech Stack:** PHP 8.4, Laravel 12, Statamic 6, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-08-14-structured-data-design.md`

## Global Constraints

- **Tests draaien met** `php -d memory_limit=1G vendor/bin/phpunit`. **Nooit** `php artisan test` (projectregel uit `CLAUDE.md`).
- **Na elke PHP-wijziging** `vendor/bin/pint --dirty --format agent` draaien.
- **Elke test wordt één keer bewust gebroken** aan de bronkant om te zien dat hij rood wordt. Een test die nooit rood is geweest, bewijst niets.
- **Bestaande suite heeft 15 failures + 1 error op `main`** (RangeHeaderTest, ProductHeaderTest, NavigationTest, LocationsTest, CardLayoutCascadeTest, MultisiteTest en vier Content-tests). Die staan los van dit werk. Vergelijk altijd tegen dat aantal; maak ze niet groter.
- **Geen blueprint-wijzigingen.** Alleen bouwen op velden die vandaag bestaan.
- **Invariant:** een node bevat nooit `null`, `''` of `[]`. Ontbreekt iets essentieels, dan valt het veld of de hele node weg.
- **JSON-encoding vlaggen, overal identiek:** `JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`.
- **Naamgeving:** er bestaat al een `App\Inspace\SchemaBuilder` (blueprint-schema voor de Inspace-API, niets met schema.org te maken). Gebruik die naam niet opnieuw.
- **Taal:** testnamen en commentaar in het Nederlands, zoals de rest van de suite.

## Bestandsoverzicht

| Bestand | Verantwoordelijkheid |
|---|---|
| `app/Schema/SiteUrl.php` | Absolute URL's samenstellen vanaf een pad |
| `app/Schema/OpeningHours.php` | NL-openingstijden → `OpeningHoursSpecification` |
| `app/Schema/SchemaGraph.php` | Nodes verzamelen, ontdubbelen op `@id`, snoeien, encoderen |
| `app/Schema/OrganizationSchema.php` | Globals → `Organization` |
| `app/Schema/LocationsSchema.php` | `locations`-collectie → 3× `LocalBusiness` |
| `app/Schema/BreadcrumbSchema.php` | URI-hiërarchie → `BreadcrumbList` |
| `app/Schema/ServiceSchema.php` | product/range-entry → `Service` |
| `app/Schema/ArticleSchema.php` | article-entry → `Article` |
| `app/Tags/Schema.php` | De tag `{{ schema }}`; kiest bouwers per paginatype |
| `resources/views/layout.antlers.html` | `{{ schema }}` in de `<head>` |

Statamic registreert tags in `app/Tags/` automatisch op basis van `protected static $handle`; er is geen provider-registratie nodig (zie `app/Tags/Img.php`).

---

### Task 1: SiteUrl

**Files:**
- Create: `app/Schema/SiteUrl.php`
- Test: `tests/Unit/Schema/SiteUrlTest.php`

**Interfaces:**
- Consumes: niets
- Produces: `SiteUrl::absolute(string $path): string` — geeft `https://host/pad` zonder dubbele slashes; `absolute('/')` eindigt op precies één slash.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Schema;

use App\Schema\SiteUrl;
use Tests\TestCase;

class SiteUrlTest extends TestCase
{
    public function test_it_builds_an_absolute_url_without_double_slashes(): void
    {
        $this->assertSame(
            rtrim(config('app.url'), '/').'/aanbod/rolluiken',
            SiteUrl::absolute('/aanbod/rolluiken'),
        );
    }

    public function test_a_path_without_a_leading_slash_works_too(): void
    {
        $this->assertSame(
            SiteUrl::absolute('/aanbod'),
            SiteUrl::absolute('aanbod'),
        );
    }

    public function test_the_root_keeps_exactly_one_trailing_slash(): void
    {
        $this->assertSame(rtrim(config('app.url'), '/').'/', SiteUrl::absolute('/'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/SiteUrlTest.php`
Expected: FAIL — `Class "App\Schema\SiteUrl" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Schema;

use Statamic\Facades\Site;

class SiteUrl
{
    public static function absolute(string $path): string
    {
        $base = rtrim(Site::current()->absoluteUrl(), '/');
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? $base.'/' : $base.$path;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/SiteUrlTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Break it once to prove the test bites**

Verander `rtrim(..., '/')` in `Site::current()->absoluteUrl()` (zonder rtrim) en draai opnieuw. Verwacht: de eerste test faalt op een dubbele slash. Zet daarna terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/SiteUrl.php tests/Unit/Schema/SiteUrlTest.php
git commit -m "feat: SiteUrl voor absolute schema-URL's"
```

---

### Task 2: OpeningHours

**Files:**
- Create: `app/Schema/OpeningHours.php`
- Test: `tests/Unit/Schema/OpeningHoursTest.php`

**Interfaces:**
- Consumes: niets
- Produces: `OpeningHours::specifications(array $rows): array` — `$rows` is een lijst van `['day' => string, 'time' => string]`; retourneert een lijst van `OpeningHoursSpecification`-arrays.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Schema;

use App\Schema\OpeningHours;
use PHPUnit\Framework\TestCase;

class OpeningHoursTest extends TestCase
{
    public function test_a_day_range_expands_to_every_day_in_it(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Di - Vr', 'time' => '10:30 - 17:30'],
        ]);

        $this->assertCount(1, $specs);
        $this->assertSame(
            ['Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            $specs[0]['dayOfWeek'],
        );
        $this->assertSame('10:30', $specs[0]['opens']);
        $this->assertSame('17:30', $specs[0]['closes']);
    }

    public function test_a_single_day_is_not_wrapped_in_an_array(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Zaterdag', 'time' => '10:00 - 16:00'],
        ]);

        $this->assertSame('Saturday', $specs[0]['dayOfWeek']);
    }

    public function test_gesloten_becomes_the_documented_zero_range(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Zondag', 'time' => 'Gesloten'],
        ]);

        $this->assertSame('Sunday', $specs[0]['dayOfWeek']);
        $this->assertSame('00:00', $specs[0]['opens']);
        $this->assertSame('00:00', $specs[0]['closes']);
    }

    /**
     * Schema.org kent geen "op afspraak", en een specificatie zonder
     * opens/closes is ongeldig. Weglaten is dan beter dan gokken.
     */
    public function test_op_afspraak_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Maandag', 'time' => 'Op afspraak'],
        ]));
    }

    public function test_an_unreadable_time_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Maandag', 'time' => 'van 9 tot 5'],
        ]));
    }

    public function test_an_unknown_day_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Someday', 'time' => '10:00 - 16:00'],
        ]));
    }

    public function test_a_reversed_range_yields_no_specification(): void
    {
        $this->assertSame([], OpeningHours::specifications([
            ['day' => 'Vr - Di', 'time' => '10:00 - 16:00'],
        ]));
    }

    public function test_the_real_winsol_week_produces_three_specifications(): void
    {
        $specs = OpeningHours::specifications([
            ['day' => 'Maandag', 'time' => 'Op afspraak'],
            ['day' => 'Di - Vr', 'time' => '10:30 - 17:30'],
            ['day' => 'Zaterdag', 'time' => '10:00 - 16:00'],
            ['day' => 'Zondag', 'time' => 'Gesloten'],
        ]);

        $this->assertCount(3, $specs);
        $this->assertSame('OpeningHoursSpecification', $specs[0]['@type']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/OpeningHoursTest.php`
Expected: FAIL — `Class "App\Schema\OpeningHours" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Schema;

/**
 * Vertaalt de menselijk geschreven openingstijden uit de `locations`-collectie
 * naar schema.org-specificaties. De opgeslagen vorm bevat dagreeksen
 * ("Di - Vr") en waarden die geen tijd zijn ("Op afspraak", "Gesloten").
 */
class OpeningHours
{
    private const DAYS = [
        'maandag' => 'Monday',    'ma' => 'Monday',
        'dinsdag' => 'Tuesday',   'di' => 'Tuesday',
        'woensdag' => 'Wednesday', 'wo' => 'Wednesday',
        'donderdag' => 'Thursday', 'do' => 'Thursday',
        'vrijdag' => 'Friday',    'vr' => 'Friday',
        'zaterdag' => 'Saturday', 'za' => 'Saturday',
        'zondag' => 'Sunday',     'zo' => 'Sunday',
    ];

    private const WEEK = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
    ];

    /**
     * @param  array<int, array{day?: string, time?: string}>  $rows
     * @return list<array<string, mixed>>
     */
    public static function specifications(array $rows): array
    {
        $specs = [];

        foreach ($rows as $row) {
            $days = self::days((string) ($row['day'] ?? ''));
            $hours = self::hours((string) ($row['time'] ?? ''));

            if ($days === [] || $hours === null) {
                continue;
            }

            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => count($days) === 1 ? $days[0] : $days,
                'opens' => $hours[0],
                'closes' => $hours[1],
            ];
        }

        return $specs;
    }

    /**
     * @return list<string>
     */
    private static function days(string $value): array
    {
        $value = trim(mb_strtolower($value));

        if ($value === '') {
            return [];
        }

        if (! str_contains($value, '-')) {
            $day = self::DAYS[$value] ?? null;

            return $day === null ? [] : [$day];
        }

        [$from, $to] = array_map(trim(...), explode('-', $value, 2));

        $from = self::DAYS[$from] ?? null;
        $to = self::DAYS[$to] ?? null;

        if ($from === null || $to === null) {
            return [];
        }

        $start = (int) array_search($from, self::WEEK, true);
        $end = (int) array_search($to, self::WEEK, true);

        if ($start > $end) {
            return [];
        }

        return array_values(array_slice(self::WEEK, $start, $end - $start + 1));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function hours(string $value): ?array
    {
        $value = trim($value);

        if (mb_strtolower($value) === 'gesloten') {
            return ['00:00', '00:00'];
        }

        if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $value, $matches)) {
            return [
                str_pad($matches[1], 5, '0', STR_PAD_LEFT),
                str_pad($matches[2], 5, '0', STR_PAD_LEFT),
            ];
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/OpeningHoursTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Break it once**

Haal de `if ($start > $end) { return []; }`-guard weg en draai opnieuw. Verwacht: `test_a_reversed_range_yields_no_specification` faalt. Zet terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/OpeningHours.php tests/Unit/Schema/OpeningHoursTest.php
git commit -m "feat: OpeningHours vertaalt NL-openingstijden naar schema.org"
```

---

### Task 3: SchemaGraph

**Files:**
- Create: `app/Schema/SchemaGraph.php`
- Test: `tests/Unit/Schema/SchemaGraphTest.php`

**Interfaces:**
- Consumes: niets
- Produces:
  - `(new SchemaGraph)->add(?array $node): static`
  - `->addAll(array $nodes): static`
  - `->isEmpty(): bool`
  - `->toJson(): string`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Schema;

use App\Schema\SchemaGraph;
use PHPUnit\Framework\TestCase;

class SchemaGraphTest extends TestCase
{
    public function test_it_wraps_nodes_in_a_context_and_graph(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Organization', '@id' => 'https://x.test/#organization'])
            ->toJson();

        $decoded = json_decode($json, true);

        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertCount(1, $decoded['@graph']);
    }

    public function test_nodes_with_the_same_id_are_deduplicated(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Organization', '@id' => 'https://x.test/#o'])
            ->add(['@type' => 'Organization', '@id' => 'https://x.test/#o'])
            ->toJson();

        $this->assertCount(1, json_decode($json, true)['@graph']);
    }

    public function test_null_nodes_are_ignored(): void
    {
        $this->assertTrue((new SchemaGraph)->add(null)->isEmpty());
    }

    public function test_empty_values_are_pruned_recursively(): void
    {
        $decoded = json_decode((new SchemaGraph)->add([
            '@type' => 'LocalBusiness',
            '@id' => 'https://x.test/#l',
            'name' => 'Winsol Dilbeek',
            'telephone' => '',
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => null],
            'sameAs' => [],
        ])->toJson(), true);

        $node = $decoded['@graph'][0];

        $this->assertSame('Winsol Dilbeek', $node['name']);
        $this->assertArrayNotHasKey('telephone', $node);
        $this->assertArrayNotHasKey('geo', $node);
        $this->assertArrayNotHasKey('sameAs', $node);
    }

    public function test_pruned_lists_keep_sequential_keys(): void
    {
        $decoded = json_decode((new SchemaGraph)->add([
            '@type' => 'Service',
            '@id' => 'https://x.test/#s',
            'areaServed' => ['Dilbeek', '', 'Aartselaar'],
        ])->toJson(), true);

        $this->assertSame(['Dilbeek', 'Aartselaar'], $decoded['@graph'][0]['areaServed']);
    }

    /**
     * Dit is de reden dat de graph in PHP gebouwd wordt en niet in Antlers:
     * een titel met </script> mag het scriptblok niet kunnen sluiten.
     */
    public function test_angle_brackets_are_hex_escaped_so_a_script_tag_cannot_close(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Article', '@id' => 'https://x.test/#a', 'headline' => 'Kapot </script> titel'])
            ->toJson();

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringContainsString('<', $json);
        $this->assertSame('Kapot </script> titel', json_decode($json, true)['@graph'][0]['headline']);
    }

    public function test_accented_characters_stay_readable(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Article', '@id' => 'https://x.test/#a', 'headline' => 'één systeem'])
            ->toJson();

        $this->assertStringContainsString('één', $json);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/SchemaGraphTest.php`
Expected: FAIL — `Class "App\Schema\SchemaGraph" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Schema;

/**
 * Verzamelt schema.org-nodes en encodeert ze als één @graph.
 *
 * Kent bewust niets van Winsol of van specifieke types: alleen nodes, @id's
 * en het snoeien van lege waarden.
 */
class SchemaGraph
{
    private const FLAGS = JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** @var array<array-key, array<string, mixed>> */
    private array $nodes = [];

    /**
     * @param  array<string, mixed>|null  $node
     */
    public function add(?array $node): static
    {
        if ($node === null) {
            return $this;
        }

        $node = self::prune($node);

        if ($node === []) {
            return $this;
        }

        $this->nodes[$node['@id'] ?? count($this->nodes)] = $node;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $nodes
     */
    public function addAll(array $nodes): static
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function toJson(): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => array_values($this->nodes),
        ], self::FLAGS);
    }

    /**
     * Verwijdert null, lege strings en lege arrays, recursief. Half ingevulde
     * markup is voor Google slechter dan afwezige markup.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function prune(array $node): array
    {
        $isList = array_is_list($node);
        $pruned = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $pruned[$key] = $value;
        }

        return $isList ? array_values($pruned) : $pruned;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/SchemaGraphTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Break it once**

Haal `JSON_HEX_TAG` uit `FLAGS` en draai opnieuw. Verwacht: `test_angle_brackets_are_hex_escaped_so_a_script_tag_cannot_close` faalt. Zet terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/SchemaGraph.php tests/Unit/Schema/SchemaGraphTest.php
git commit -m "feat: SchemaGraph verzamelt en encodeert de JSON-LD-graph"
```

---

### Task 4: OrganizationSchema

**Files:**
- Create: `app/Schema/OrganizationSchema.php`
- Test: `tests/Unit/Schema/OrganizationSchemaTest.php`

**Interfaces:**
- Consumes: `SiteUrl::absolute()` (Task 1)
- Produces:
  - `OrganizationSchema::id(): string` — `https://host/#organization`
  - `OrganizationSchema::node(): array`
  - `OrganizationSchema::sameAs(array $socials): array` — public, zodat de host-regel los te testen is

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Schema;

use App\Schema\OrganizationSchema;
use Tests\TestCase;

class OrganizationSchemaTest extends TestCase
{
    public function test_the_node_uses_the_site_name_and_the_shared_phone_number(): void
    {
        $node = OrganizationSchema::node();

        $this->assertSame('Organization', $node['@type']);
        $this->assertSame('Winsol Brebo', $node['name']);
        $this->assertSame('+32 2 308 02 26', $node['telephone']);
        $this->assertStringEndsWith('/#organization', $node['@id']);
    }

    /**
     * globals.socials staat op https://test.be. Placeholders in sameAs zijn
     * schadelijker dan een ontbrekend veld, dus ze mogen er niet in.
     */
    public function test_placeholder_socials_are_dropped(): void
    {
        $this->assertSame([], OrganizationSchema::sameAs([
            'facebook' => 'https://test.be',
            'instagram' => 'https://test.be',
            'linkedin' => 'https://test.be',
            'youtube' => 'https://test.be',
        ]));
    }

    public function test_a_real_platform_url_is_kept(): void
    {
        $this->assertSame(
            ['https://www.facebook.com/winsolbrebo'],
            OrganizationSchema::sameAs(['facebook' => 'https://www.facebook.com/winsolbrebo']),
        );
    }

    public function test_a_url_on_the_wrong_platform_host_is_dropped(): void
    {
        $this->assertSame([], OrganizationSchema::sameAs([
            'facebook' => 'https://instagram.com/winsolbrebo',
        ]));
    }

    public function test_youtu_be_counts_as_youtube(): void
    {
        $this->assertSame(
            ['https://youtu.be/abc123'],
            OrganizationSchema::sameAs(['youtube' => 'https://youtu.be/abc123']),
        );
    }

    public function test_empty_socials_yield_nothing(): void
    {
        $this->assertSame([], OrganizationSchema::sameAs(['facebook' => '', 'linkedin' => null]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/OrganizationSchemaTest.php`
Expected: FAIL — `Class "App\Schema\OrganizationSchema" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Schema;

use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class OrganizationSchema
{
    /**
     * Alleen een URL waarvan de host bij het platform past telt mee. Dat vangt
     * placeholders als https://test.be zonder een lijst met placeholder-hosts
     * bij te houden, die toch veroudert.
     *
     * @var array<string, list<string>>
     */
    private const SOCIAL_HOSTS = [
        'facebook' => ['facebook.com'],
        'instagram' => ['instagram.com'],
        'linkedin' => ['linkedin.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
    ];

    public static function id(): string
    {
        return SiteUrl::absolute('/').'#organization';
    }

    /**
     * @return array<string, mixed>
     */
    public static function node(): array
    {
        $globals = GlobalSet::findByHandle('globals')?->inCurrentSite();
        $contact = (array) ($globals?->get('contact') ?? []);
        $socials = (array) ($globals?->get('socials') ?? []);

        return [
            '@type' => 'Organization',
            '@id' => self::id(),
            'name' => Site::current()->name(),
            'url' => SiteUrl::absolute('/'),
            'telephone' => trim((string) ($contact['phone'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'sameAs' => self::sameAs($socials),
        ];
    }

    /**
     * @param  array<string, mixed>  $socials
     * @return list<string>
     */
    public static function sameAs(array $socials): array
    {
        $urls = [];

        foreach (self::SOCIAL_HOSTS as $platform => $allowedHosts) {
            $url = trim((string) ($socials[$platform] ?? ''));

            if ($url === '') {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);

            foreach ($allowedHosts as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    $urls[] = $url;
                    break;
                }
            }
        }

        return $urls;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Schema/OrganizationSchemaTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Break it once**

Vervang de host-controle door `$urls[] = $url;` zonder check en draai opnieuw. Verwacht: `test_placeholder_socials_are_dropped` faalt. Zet terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/OrganizationSchema.php tests/Unit/Schema/OrganizationSchemaTest.php
git commit -m "feat: Organization-node met host-gevalideerde sameAs"
```

---

### Task 5: LocationsSchema

**Files:**
- Create: `app/Schema/LocationsSchema.php`
- Test: `tests/Feature/Schema/LocationsSchemaTest.php`

**Interfaces:**
- Consumes: `OpeningHours::specifications()` (Task 2), `OrganizationSchema::id()` (Task 4), `SiteUrl::absolute()` (Task 1)
- Produces: `LocationsSchema::nodes(): array` — lijst van `LocalBusiness`-nodes, één per entry in de `locations`-collectie.

Deze test staat in `tests/Feature` omdat hij de Stache leest; de klasse zelf blijft dun.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Schema\LocationsSchema;
use App\Schema\OrganizationSchema;
use Tests\TestCase;

class LocationsSchemaTest extends TestCase
{
    public function test_it_builds_one_node_per_showroom(): void
    {
        $this->assertCount(3, LocationsSchema::nodes());
    }

    public function test_dilbeek_carries_its_full_address_and_coordinates(): void
    {
        $node = collect(LocationsSchema::nodes())
            ->firstWhere('name', 'Winsol Dilbeek');

        $this->assertNotNull($node, 'De vestiging Dilbeek hoort in de locations-collectie te staan.');
        $this->assertSame('LocalBusiness', $node['@type']);
        $this->assertSame('Ninoofsesteenweg 637', $node['address']['streetAddress']);
        $this->assertSame('1700', $node['address']['postalCode']);
        $this->assertSame('Dilbeek', $node['address']['addressLocality']);
        $this->assertSame('BE', $node['address']['addressCountry']);
        $this->assertSame(50.842047, $node['geo']['latitude']);
        $this->assertSame(4.237594, $node['geo']['longitude']);
    }

    public function test_every_node_points_at_the_organization(): void
    {
        foreach (LocationsSchema::nodes() as $node) {
            $this->assertSame(OrganizationSchema::id(), $node['parentOrganization']['@id']);
        }
    }

    public function test_opening_hours_are_translated(): void
    {
        $node = collect(LocationsSchema::nodes())->firstWhere('name', 'Winsol Dilbeek');

        $this->assertCount(3, $node['openingHoursSpecification']);
    }

    public function test_each_node_has_a_distinct_id(): void
    {
        $ids = array_column(LocationsSchema::nodes(), '@id');

        $this->assertSame($ids, array_unique($ids));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/LocationsSchemaTest.php`
Expected: FAIL — `Class "App\Schema\LocationsSchema" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

class LocationsSchema
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function nodes(): array
    {
        return Entry::query()
            ->where('collection', 'locations')
            ->orderBy('order')
            ->get()
            ->map(fn (EntryContract $entry) => self::node($entry))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function node(EntryContract $entry): ?array
    {
        $name = trim((string) $entry->get('name'));

        if ($name === '') {
            return null;
        }

        $street = trim((string) $entry->get('street'));
        $number = trim((string) $entry->get('number'));

        return [
            '@type' => 'LocalBusiness',
            '@id' => SiteUrl::absolute('/').'#'.$entry->slug(),
            'name' => $name,
            'parentOrganization' => ['@id' => OrganizationSchema::id()],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => trim($street.' '.$number),
                'postalCode' => trim((string) $entry->get('postal_code')),
                'addressLocality' => trim((string) $entry->get('city')),
                'addressCountry' => 'BE',
            ],
            'geo' => self::geo($entry),
            'openingHoursSpecification' => OpeningHours::specifications(
                (array) ($entry->get('opening_hours') ?? [])
            ),
        ];
    }

    /**
     * Zonder geldige coördinaten geen geo-blok: liever niets dan null.
     *
     * @return array<string, mixed>
     */
    private static function geo(EntryContract $entry): array
    {
        $latitude = $entry->get('latitude');
        $longitude = $entry->get('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return [];
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/LocationsSchemaTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Break it once**

Laat `geo()` altijd `['@type' => 'GeoCoordinates', 'latitude' => null, 'longitude' => null]` teruggeven en draai opnieuw. Verwacht: `test_dilbeek_carries_its_full_address_and_coordinates` faalt. Zet terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/LocationsSchema.php tests/Feature/Schema/LocationsSchemaTest.php
git commit -m "feat: LocalBusiness-nodes uit de locations-collectie"
```

---

### Task 6: BreadcrumbSchema

**Files:**
- Create: `app/Schema/BreadcrumbSchema.php`
- Test: `tests/Feature/Schema/BreadcrumbSchemaTest.php`

**Interfaces:**
- Consumes: `SiteUrl::absolute()` (Task 1)
- Produces: `BreadcrumbSchema::node(string $uri, string $currentTitle): ?array` — `null` op de homepage, anders een `BreadcrumbList`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Schema\BreadcrumbSchema;
use Tests\TestCase;

class BreadcrumbSchemaTest extends TestCase
{
    public function test_the_homepage_has_no_breadcrumb(): void
    {
        $this->assertNull(BreadcrumbSchema::node('/', 'Home'));
    }

    public function test_a_product_gets_four_levels(): void
    {
        $node = BreadcrumbSchema::node('/aanbod/rolluiken/inbouwrolluiken', 'Inbouwrolluiken');

        $this->assertSame('BreadcrumbList', $node['@type']);
        $this->assertCount(4, $node['itemListElement']);

        $this->assertSame('Home', $node['itemListElement'][0]['name']);
        $this->assertSame(1, $node['itemListElement'][0]['position']);
        $this->assertSame('Inbouwrolluiken', $node['itemListElement'][3]['name']);
        $this->assertSame(4, $node['itemListElement'][3]['position']);
    }

    public function test_intermediate_levels_use_the_real_entry_title(): void
    {
        $node = BreadcrumbSchema::node('/aanbod/rolluiken/inbouwrolluiken', 'Inbouwrolluiken');

        $this->assertSame('Aanbod', $node['itemListElement'][1]['name']);
    }

    public function test_items_are_absolute_urls(): void
    {
        $node = BreadcrumbSchema::node('/aanbod/rolluiken', 'Rolluiken');

        foreach ($node['itemListElement'] as $item) {
            $this->assertStringStartsWith('http', $item['item']);
        }
    }

    public function test_a_missing_intermediate_entry_falls_back_to_the_slug(): void
    {
        $node = BreadcrumbSchema::node('/bestaat-niet/dieper', 'Dieper');

        $this->assertSame('Bestaat niet', $node['itemListElement'][1]['name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/BreadcrumbSchemaTest.php`
Expected: FAIL — `Class "App\Schema\BreadcrumbSchema" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Schema;

use Statamic\Facades\Entry;

class BreadcrumbSchema
{
    /**
     * @return array<string, mixed>|null
     */
    public static function node(string $uri, string $currentTitle): ?array
    {
        $uri = '/'.trim($uri, '/');

        if ($uri === '/') {
            return null;
        }

        $items = [['name' => 'Home', 'item' => SiteUrl::absolute('/')]];

        $segments = explode('/', trim($uri, '/'));
        $last = count($segments) - 1;
        $path = '';

        foreach ($segments as $index => $segment) {
            $path .= '/'.$segment;

            $items[] = [
                'name' => $index === $last ? $currentTitle : self::titleFor($path, $segment),
                'item' => SiteUrl::absolute($path),
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => SiteUrl::absolute($uri).'#breadcrumb',
            'itemListElement' => array_map(
                fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['item'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * Een tussenliggend niveau heeft meestal een echte entry (`/aanbod`).
     * Zo niet, dan is de slug het beste dat we hebben.
     */
    private static function titleFor(string $path, string $segment): string
    {
        $entry = Entry::findByUri($path);

        if ($entry && trim((string) $entry->get('title')) !== '') {
            return (string) $entry->get('title');
        }

        return ucfirst(str_replace('-', ' ', $segment));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/BreadcrumbSchemaTest.php`
Expected: PASS (5 tests).

> Als `test_intermediate_levels_use_the_real_entry_title` faalt met een andere titel dan `Aanbod`, controleer dan met
> `php artisan tinker --execute 'echo Statamic\Facades\Entry::findByUri("/aanbod")?->get("title");'`
> wat de entry werkelijk heet, en pas de verwachting in de test aan op de echte titel. De code blijft dan ongewijzigd.

- [ ] **Step 5: Break it once**

Laat `titleFor()` altijd `ucfirst(str_replace('-', ' ', $segment))` teruggeven en draai opnieuw. Verwacht: `test_intermediate_levels_use_the_real_entry_title` faalt alleen als de echte titel afwijkt van de slug. Wijkt hij niet af, breek dan in plaats daarvan `position` naar `$index` en verwacht dat `test_a_product_gets_four_levels` faalt. Zet terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/BreadcrumbSchema.php tests/Feature/Schema/BreadcrumbSchemaTest.php
git commit -m "feat: BreadcrumbList uit de URL-hierarchie"
```

---

### Task 7: ServiceSchema en ArticleSchema

**Files:**
- Create: `app/Schema/ServiceSchema.php`
- Create: `app/Schema/ArticleSchema.php`
- Test: `tests/Feature/Schema/ServiceAndArticleSchemaTest.php`

**Interfaces:**
- Consumes: `SiteUrl::absolute()` (Task 1), `OrganizationSchema::id()` (Task 4), `LocationsSchema::nodes()` (Task 5)
- Produces:
  - `ServiceSchema::node(EntryContract $entry): ?array`
  - `ArticleSchema::node(EntryContract $entry): ?array`

Deze twee zitten in één taak omdat ze dezelfde vorm hebben en dezelfde test-fixture delen; ze los knippen zou een reviewer niets extra's laten afkeuren.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use App\Schema\ArticleSchema;
use App\Schema\OrganizationSchema;
use App\Schema\ServiceSchema;
use Statamic\Facades\Entry;
use Tests\TestCase;

class ServiceAndArticleSchemaTest extends TestCase
{
    public function test_a_product_becomes_a_service_that_names_its_area(): void
    {
        $node = ServiceSchema::node(Entry::findByUri('/aanbod/rolluiken/inbouwrolluiken'));

        $this->assertSame('Service', $node['@type']);
        $this->assertSame('Inbouwrolluiken', $node['name']);
        $this->assertSame('Plaatsing van Inbouwrolluiken', $node['serviceType']);
        $this->assertSame(OrganizationSchema::id(), $node['provider']['@id']);
        $this->assertContains('Dilbeek', $node['areaServed']);
        $this->assertContains('Aartselaar', $node['areaServed']);
        $this->assertContains('Sint-Pieters-Leeuw', $node['areaServed']);
    }

    public function test_a_range_becomes_a_service_too(): void
    {
        $node = ServiceSchema::node(Entry::findByUri('/aanbod/rolluiken'));

        $this->assertSame('Service', $node['@type']);
        $this->assertSame('Rolluiken', $node['name']);
    }

    public function test_an_article_carries_its_publication_date(): void
    {
        $entry = Entry::query()->where('collection', 'articles')->first();

        $node = ArticleSchema::node($entry);

        $this->assertSame('Article', $node['@type']);
        $this->assertSame((string) $entry->get('title'), $node['headline']);
        $this->assertNotEmpty($node['datePublished']);
        $this->assertSame(OrganizationSchema::id(), $node['publisher']['@id']);
    }

    public function test_a_null_entry_yields_no_node(): void
    {
        $this->assertNull(ServiceSchema::node(null));
        $this->assertNull(ArticleSchema::node(null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/ServiceAndArticleSchemaTest.php`
Expected: FAIL — `Class "App\Schema\ServiceSchema" not found`.

- [ ] **Step 3: Write minimal implementation**

`app/Schema/ServiceSchema.php`:

```php
<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;

/**
 * Product- en rangepagina's zijn maatwerk zonder prijs of SKU, dus Service
 * past en Product niet: zonder `offers` levert Product geen rich result en
 * wel een waarschuwing in Search Console. `areaServed` koppelt de pagina
 * bovendien aan het lokale bereik, en dat is waar deze site kan winnen.
 */
class ServiceSchema
{
    /**
     * @return array<string, mixed>|null
     */
    public static function node(?EntryContract $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        $title = trim((string) $entry->get('title'));

        if ($title === '') {
            return null;
        }

        $uri = (string) $entry->uri();

        return [
            '@type' => 'Service',
            '@id' => SiteUrl::absolute($uri).'#service',
            'name' => $title,
            'serviceType' => 'Plaatsing van '.$title,
            'provider' => ['@id' => OrganizationSchema::id()],
            'areaServed' => self::areaServed(),
            'url' => SiteUrl::absolute($uri),
        ];
    }

    /**
     * De gemeentes komen uit dezelfde bron als de LocalBusiness-nodes, zodat
     * er maar één plek is waar het werkgebied vandaan komt.
     *
     * @return list<string>
     */
    private static function areaServed(): array
    {
        $cities = [];

        foreach (LocationsSchema::nodes() as $node) {
            $city = $node['address']['addressLocality'] ?? '';

            if ($city !== '' && ! in_array($city, $cities, true)) {
                $cities[] = $city;
            }
        }

        return $cities;
    }
}
```

`app/Schema/ArticleSchema.php`:

```php
<?php

namespace App\Schema;

use Statamic\Contracts\Entries\Entry as EntryContract;

class ArticleSchema
{
    /**
     * @return array<string, mixed>|null
     */
    public static function node(?EntryContract $entry): ?array
    {
        if ($entry === null) {
            return null;
        }

        $title = trim((string) $entry->get('title'));

        if ($title === '') {
            return null;
        }

        $uri = (string) $entry->uri();

        return [
            '@type' => 'Article',
            '@id' => SiteUrl::absolute($uri).'#article',
            'headline' => $title,
            'datePublished' => $entry->date()?->toIso8601String(),
            'publisher' => ['@id' => OrganizationSchema::id()],
            'mainEntityOfPage' => SiteUrl::absolute($uri),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/ServiceAndArticleSchemaTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Break it once**

Laat `areaServed()` een lege array teruggeven en draai opnieuw. Verwacht: `test_a_product_becomes_a_service_that_names_its_area` faalt op de `assertContains`. Zet terug.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Schema/ServiceSchema.php app/Schema/ArticleSchema.php tests/Feature/Schema/ServiceAndArticleSchemaTest.php
git commit -m "feat: Service- en Article-nodes"
```

---

### Task 8: De tag en de bedrading

**Files:**
- Create: `app/Tags/Schema.php`
- Modify: `resources/views/layout.antlers.html` (regel 8, na `{{ partial:seo }}`)
- Test: `tests/Feature/Schema/SchemaMarkupTest.php`

**Interfaces:**
- Consumes: alles uit Task 1 t/m 7
- Produces: de Antlers-tag `{{ schema }}`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Schema;

use Tests\TestCase;

class SchemaMarkupTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function pages(): array
    {
        return [
            'homepage' => ['/'],
            'range' => ['/aanbod/rolluiken'],
            'product' => ['/aanbod/rolluiken/inbouwrolluiken'],
            'overzicht' => ['/aanbod'],
            'contact' => ['/contact'],
            'nieuws' => ['/nieuws'],
        ];
    }

    /**
     * De kern van dit ontwerp: op élk paginatype moet het blok geldige JSON
     * zijn. Dit is de enige test die een escapingfout kan betrappen.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_json_ld_block_parses_as_valid_json(string $path): void
    {
        $graph = $this->graphFrom($path);

        $this->assertNotSame([], $graph, "Geen @graph gevonden op {$path}.");
    }

    public function test_the_homepage_carries_the_organization_and_three_showrooms(): void
    {
        $types = array_column($this->graphFrom('/'), '@type');

        $this->assertContains('Organization', $types);
        $this->assertSame(3, count(array_filter($types, fn ($t) => $t === 'LocalBusiness')));
    }

    public function test_a_product_page_carries_a_service_and_a_breadcrumb(): void
    {
        $types = array_column($this->graphFrom('/aanbod/rolluiken/inbouwrolluiken'), '@type');

        $this->assertContains('Service', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    public function test_an_article_page_carries_an_article_with_a_date(): void
    {
        $graph = $this->graphFrom('/nieuws/winsol-wint-zijn-vijfde-red-dot-award');

        $article = collect($graph)->firstWhere('@type', 'Article');

        $this->assertNotNull($article, 'Een nieuwsartikel hoort een Article-node te hebben.');
        $this->assertNotEmpty($article['datePublished']);
    }

    /**
     * Een @id-verwijzing die nergens heen wijst maakt de graph waardeloos:
     * dan zijn het alsnog losse fragmenten in plaats van één entiteit.
     */
    public function test_every_id_reference_resolves_within_the_graph(): void
    {
        $graph = $this->graphFrom('/aanbod/rolluiken/inbouwrolluiken');
        $known = array_column($graph, '@id');

        foreach ($graph as $node) {
            foreach (['provider', 'parentOrganization', 'publisher'] as $key) {
                if (isset($node[$key]['@id'])) {
                    $this->assertContains(
                        $node[$key]['@id'],
                        $known,
                        "{$node['@type']}.{$key} wijst naar een @id dat niet in de graph staat.",
                    );
                }
            }
        }
    }

    public function test_the_placeholder_socials_never_reach_the_output(): void
    {
        $this->assertStringNotContainsString('test.be', $this->get('/')->getContent());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function graphFrom(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">#',
            $html,
            "Geen JSON-LD-blok op {$path}.",
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $decoded = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        return $decoded['@graph'] ?? [];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/SchemaMarkupTest.php`
Expected: FAIL — geen `<script type="application/ld+json">` in de HTML.

- [ ] **Step 3: Write the tag**

```php
<?php

namespace App\Tags;

use App\Schema\ArticleSchema;
use App\Schema\BreadcrumbSchema;
use App\Schema\LocationsSchema;
use App\Schema\OrganizationSchema;
use App\Schema\SchemaGraph;
use App\Schema\ServiceSchema;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

/**
 * Rendert één JSON-LD-graph in de <head>. Bepaalt alleen wélke bouwers bij
 * de huidige pagina horen; van schema.org zelf weet deze klasse niets.
 */
class Schema extends Tags
{
    protected static $handle = 'schema';

    private const SERVICE_COLLECTIONS = ['products', 'ranges'];

    public function index(): string
    {
        $graph = (new SchemaGraph)
            ->add(OrganizationSchema::node())
            ->addAll(LocationsSchema::nodes());

        $entry = $this->currentEntry();

        if ($entry !== null) {
            $graph->add(BreadcrumbSchema::node(
                (string) $entry->uri(),
                (string) $entry->get('title'),
            ));

            $collection = $entry->collection()?->handle();

            if (in_array($collection, self::SERVICE_COLLECTIONS, true)) {
                $graph->add(ServiceSchema::node($entry));
            } elseif ($collection === 'articles') {
                $graph->add(ArticleSchema::node($entry));
            }
        }

        if ($graph->isEmpty()) {
            return '';
        }

        return '<script type="application/ld+json">'.$graph->toJson().'</script>';
    }

    /**
     * De cascade draagt de id van de huidige entry. Op pagina's waar dat niet
     * zo is, valt hij terug op de URI, zodat de graph nooit stilvalt.
     */
    private function currentEntry(): ?EntryContract
    {
        $id = $this->context->get('id');

        if ($id && ($entry = Entry::find($id))) {
            return $entry;
        }

        return Entry::findByUri('/'.trim(request()->path(), '/'));
    }
}
```

- [ ] **Step 4: Wire it into the layout**

In `resources/views/layout.antlers.html`, direct na `{{ partial:seo }}`:

```antlers
        {{ partial:seo }}
        {{ schema }}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Schema/SchemaMarkupTest.php`
Expected: PASS (11 tests).

- [ ] **Step 6: Break it once**

Haal `{{ schema }}` tijdelijk uit de layout en draai opnieuw. Verwacht: alle tests falen op een ontbrekend JSON-LD-blok. Zet terug.

- [ ] **Step 7: Run the whole suite and compare against the baseline**

Run: `php -d memory_limit=1G vendor/bin/phpunit`
Expected: **15 failures + 1 error**, precies zoals op `main`. Meer betekent dat dit werk iets gebroken heeft; zoek dat uit voordat je commit.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Tags/Schema.php resources/views/layout.antlers.html tests/Feature/Schema/SchemaMarkupTest.php
git commit -m "feat: JSON-LD-graph in de head via de schema-tag"
```

---

### Task 9: Handmatige validatie

**Files:** geen

- [ ] **Step 1: Render één pagina van elk type en bekijk de JSON**

```bash
php artisan tinker --execute 'echo Statamic\Facades\Site::current()->absoluteUrl();'
```

Haal daarna via de browser of `curl` de JSON op en bekijk hem:

```bash
curl -s https://winsol-brebo.test/aanbod/rolluiken/inbouwrolluiken \
  | grep -o '<script type="application/ld+json">.*</script>' \
  | sed 's|<[^>]*>||g' | python3 -m json.tool
```

- [ ] **Step 2: Controleer op de Rich Results Test**

Plak de JSON in https://search.google.com/test/rich-results. Verwacht: `LocalBusiness` en `BreadcrumbList` worden herkend. `Service` levert geen rich result op (dat is bekend en akkoord) maar hoort ook geen fouten te geven.

- [ ] **Step 3: Controleer of de drie vestigingen apart herkend worden**

In de Rich Results Test horen drie losse `LocalBusiness`-items te staan, niet één samengevoegde. Zo niet, controleer of de `@id`'s werkelijk verschillen (`test_each_node_has_a_distinct_id` uit Task 5 dekt dit al af, maar de visuele bevestiging is de moeite waard).

- [ ] **Step 4: Noteer bevindingen**

Zet afwijkingen in `docs/superpowers/specs/2026-08-14-structured-data-followups.md`, volgens de conventie van de bestaande `*-followups.md`-bestanden.

---

## Zelfreview van dit plan

**Spec-dekking:**

| Spec-onderdeel | Taak |
|---|---|
| §1 Architectuur (7 klassen + tag + layout) | 1–8 |
| §2 De graph, alle vijf de nodetypes | 4, 5, 6, 7 |
| §2 BreadcrumbList uit de URL-hiërarchie | 6 |
| §3 Openingstijden, alle vier de invoervormen | 2 |
| §4 Invariant "nooit leeg" | 3 (prune), geverifieerd in 5 (geo) |
| §4 Socials via host-regel | 4 |
| §4 `JSON_HEX_TAG` tegen `</script>` | 3 |
| §4 Geen entry in de cascade | 8 (`currentEntry()` met fallback) |
| §5 Unit-tests | 1, 2, 3, 4 |
| §5 Feature-tests incl. dangling-ref-check | 8 |
| §5 Elke test één keer breken | Step 5 van elke taak |
| §6 Buiten scope | nergens geïmplementeerd — correct |

**Afwijking van de spec:** de spec noemde zeven klassen in `app/Schema/`; dit plan voegt `SiteUrl` toe als achtste. Reden: vier bouwers hebben dezelfde absolute-URL-logica nodig, en die vier keer herhalen is slechter dan één kleine klasse. Verder ongewijzigd.

**Typeconsistentie gecontroleerd:** `OrganizationSchema::id()` wordt aangeroepen in Task 5, 7 en de tests van 7 en 8, overal met dezelfde signatuur. `SiteUrl::absolute()` idem in 4, 5, 6, 7. `ServiceSchema::node()` en `ArticleSchema::node()` nemen allebei `?EntryContract` en geven `?array`, zoals de tag in Task 8 ze aanroept.
