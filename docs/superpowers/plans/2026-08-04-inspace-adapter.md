# Inspace/Nova adapter — implementatieplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een schrijf-API op `/api/inspace/v1/` waarmee Inspace' product Nova artikels in de `articles`-collectie kan lezen, aanmaken en bijwerken, inclusief media-upload.

**Architecture:** Losse, testbare eenheden onder `app/Inspace/` die niets van elkaars interne werking weten: een blokkenconverter tussen ProseMirror en de API-vorm, drie HTML-transformaties (sanitizer, links, afbeeldingen) en een schrijver die het geheel achter een lock zet. Drie dunne controllers erbovenop. Alles wat sitespecifiek is staat in `config/inspace.php`, zodat overname naar een tweede site een kopieerhandeling blijft.

**Tech Stack:** Statamic 6, Laravel 12, PHP 8.4, PHPUnit 11. Geen nieuwe Composer-afhankelijkheden.

**Spec:** `docs/superpowers/specs/2026-08-03-inspace-adapter-design.md`

## Global Constraints

- **Tests draaien met** `php -d memory_limit=1G vendor/bin/phpunit`. Nooit `php artisan test`, ook niet via `composer test` — dat script gebruikt het verboden commando.
- **Na elke PHP-wijziging** `vendor/bin/pint --dirty --format agent` draaien vóór de commit.
- **Geen nieuwe Composer-afhankelijkheden.** `ueberdosis/tiptap-php` zit al in de boom als dependency van `statamic/cms` en wordt via Statamic's eigen `Augmentor` aangesproken, nooit rechtstreeks.
- **Geen enkele hardgecodeerde verwijzing** naar `articles`, `redactor`, `themes` of veldnamen in klassen onder `app/Inspace/`. Alles via `config('inspace.…')`. Dit is wat overname naar een tweede site een kopieerhandeling houdt.
- **Commentaar** alleen voor wat je niet uit de code leest: een niet-evidente reden, een cruciale TODO, iets dat ontbreekt. Geen commentaar bij zelfverklarende code.
- **PHP-conventies:** expliciete return types en type hints overal, constructor property promotion, curly braces ook bij eenregelige bodies, PHPDoc met array shapes boven inline commentaar.
- **Tests gebruiken** `Tests\Concerns\CreatesTemporaryContent` voor entries (`temporaryEntry()`) en assets (`fakeAssetDisk()`). Nooit zelf `Entry::make()->save()` zonder opruiming — residu laat de contentbrede controles van latere tests falen.
- **Statamic's `Augmentor` wordt altijd uit het echte veld gebouwd**, nooit uit een handgemaakte `Field`: alleen dan dragen de buttons en de sets de echte configuratie.

---

### Task 1: Voorwerk — `intro` hernoemen naar `text`

De fieldset `page_header` declareert `intro`, maar alle zes artikels en elk header-partial gebruiken `text`. Het CP-veld schrijft vandaag naar een sleutel die niets rendert. De fieldset wordt alleen geïmporteerd door `legal` (gebruikt geen van beide) en `page_header_image` → `article`, dus de hernoeming raakt verder niets.

**Files:**
- Modify: `resources/fieldsets/page_header.yaml`
- Test: `tests/Feature/Content/ArticleIntroFieldTest.php`

**Interfaces:**
- Consumes: niets
- Produces: blueprint-handle `text` op de `article`-blueprint, waar Task 7 en Task 9 het API-veld `intro` naartoe mappen

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Collection;
use Tests\TestCase;

class ArticleIntroFieldTest extends TestCase
{
    public function test_article_blueprint_uses_text_and_not_intro(): void
    {
        $blueprint = Collection::findByHandle('articles')->entryBlueprint();

        $this->assertTrue(
            $blueprint->hasField('text'),
            'De article-blueprint moet het intro-veld onder handle `text` dragen, want alle content en elk header-partial leest `text`.'
        );

        $this->assertFalse(
            $blueprint->hasField('intro'),
            'Handle `intro` schrijft naar een sleutel die niets rendert.'
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=test_article_blueprint_uses_text_and_not_intro`
Expected: FAIL — `hasField('text')` is false, `hasField('intro')` is true.

- [ ] **Step 3: Rename the handle**

In `resources/fieldsets/page_header.yaml`, wijzig `handle: intro` naar `handle: text`. Laat `display: Intro` staan — dat is het CP-label, niet de opslagsleutel.

```yaml
title: 'Page Header'
fields:
  -
    handle: title
    field:
      type: text
      display: Title
  -
    handle: text
    field:
      type: textarea
      display: Intro
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php -d memory_limit=1G vendor/bin/phpunit --filter=test_article_blueprint_uses_text_and_not_intro`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php -d memory_limit=1G vendor/bin/phpunit`
Expected: geen nieuwe failures. De hernoeming raakt `legal` (dat het veld niet gebruikt) en `article`; als een sectietest op `intro` steunde komt dat hier boven.

- [ ] **Step 6: Commit**

```bash
git add resources/fieldsets/page_header.yaml tests/Feature/Content/ArticleIntroFieldTest.php
git commit -m "fix: page_header schrijft naar text, de sleutel die de templates lezen"
```

---

### Task 2: Config, routes, bearer-token en `GET /schema`

Het eerste endpoint, en meteen de volledige authenticatieketen. `/schema` is bewust de eerste: het is puur lezen, het heeft geen afhankelijkheden, en het is wat Inspace' "±30 minuten per klant" waarmaakt.

**Files:**
- Create: `config/inspace.php`
- Create: `routes/inspace.php`
- Create: `app/Http/Middleware/InspaceToken.php`
- Create: `app/Inspace/SchemaBuilder.php`
- Create: `app/Http/Controllers/Inspace/SchemaController.php`
- Modify: `bootstrap/app.php`
- Modify: `.env.example`
- Test: `tests/Feature/Inspace/AuthTest.php`, `tests/Feature/Inspace/SchemaTest.php`

**Interfaces:**
- Consumes: niets
- Produces:
  - `config('inspace.writable')` — `array<string, array{content_field: string, fields: array<string, string>}>`. `content_field` is de blueprint-handle van het Bard-veld; `fields` mapt API-veldnaam → blueprint-handle.
  - `config('inspace.readable')` — `list<string>|null`, `null` betekent alle collecties
  - `config('inspace.assets')` — `array{container: string, folder: string, max_kb: int}`
  - `config('inspace.tokens')` — `array<string, string>`, label → sha256-hash
  - middleware-alias `inspace.token`
  - `App\Inspace\SchemaBuilder::build(): array` — de volledige `/schema`-payload

- [ ] **Step 1: Write the config**

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Leesbare collecties
    |--------------------------------------------------------------------------
    |
    | Welke collecties GET /pages teruggeeft. `null` betekent alle collecties.
    | Lezen is bewust breder dan schrijven: Nova heeft de sitestructuur nodig
    | om interne links te kunnen leggen.
    |
    */

    'readable' => null,

    /*
    |--------------------------------------------------------------------------
    | Schrijfbare collecties en hun veldmapping
    |--------------------------------------------------------------------------
    |
    | Per collectie: de API-veldnaam links, de blueprint-handle rechts. Twee
    | namen wijken bewust af. `intro` heet in het blueprint `text`, want een
    | API-veld `text` naast `content` is voor de koppelende partij niet te
    | onderscheiden. En `theme` is enkelvoud omdat max_items 1 het al tot een
    | term beperkt, terwijl de handle `themes` heet.
    |
    */

    'writable' => [
        'articles' => [
            'content_field' => 'redactor',
            'fields' => [
                'title' => 'title',
                'intro' => 'text',
                'content' => 'redactor',
                'image' => 'image',
                'theme' => 'themes',
                'slug' => 'slug',
                'date' => 'date',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
                'meta_image' => 'meta_image',
                'seo_noindex' => 'seo_noindex',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'assets' => [
        'container' => 'assets',
        'folder' => 'inspace',
        'max_kb' => 8192,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    |
    | Label => sha256-hash van het token. Het token zelf staat nooit in code
    | of config, alleen de hash. Genereren:
    |
    |   php -r 'echo hash("sha256", "jouw-token");'
    |
    */

    'tokens' => array_filter([
        'nova' => env('INSPACE_TOKEN_NOVA'),
    ]),

    'rate_limit' => (int) env('INSPACE_RATE_LIMIT', 120),

];
```

- [ ] **Step 2: Write the failing auth test**

```php
<?php

namespace Tests\Feature\Inspace;

use Tests\TestCase;

class AuthTest extends TestCase
{
    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_missing_token_gives_401(): void
    {
        $this->getJson('/api/inspace/v1/schema')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Ontbrekend of ongeldig token.');
    }

    public function test_wrong_token_gives_401(): void
    {
        $this->withToken('niet-dit-token')
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(401);
    }

    public function test_valid_token_gives_200(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->assertStatus(200);
    }
}
```

- [ ] **Step 3: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/AuthTest.php`
Expected: FAIL met 404 — de route bestaat nog niet.

- [ ] **Step 4: Write the middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InspaceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $presented = $request->bearerToken();

        if ($presented === null || ($label = $this->match($presented)) === null) {
            return response()->json(['message' => 'Ontbrekend of ongeldig token.'], 401);
        }

        $request->attributes->set('inspace_token_label', $label);

        return $next($request);
    }

    /**
     * hash_equals en geen ===: die laatste breekt af op het eerste
     * afwijkende byte en lekt daarmee de lengte van het geldige prefix.
     */
    private function match(string $presented): ?string
    {
        $hash = hash('sha256', $presented);

        foreach (config('inspace.tokens', []) as $label => $known) {
            if (hash_equals((string) $known, $hash)) {
                return (string) $label;
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Write the SchemaBuilder**

```php
<?php

namespace App\Inspace;

use Statamic\Facades\Collection;
use Statamic\Fields\Field;

class SchemaBuilder
{
    /**
     * @return array{collections: array<string, array{writable: bool, route: ?string, fields: array<string, array<string, mixed>>}>}
     */
    public function build(): array
    {
        $collections = [];

        foreach (array_keys(config('inspace.writable', [])) as $handle) {
            $collection = Collection::findByHandle($handle);

            if ($collection === null) {
                continue;
            }

            $collections[$handle] = [
                'writable' => true,
                'route' => $collection->route($collection->sites()->first()),
                'fields' => $this->fields($handle),
            ];
        }

        return ['collections' => $collections];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fields(string $handle): array
    {
        $blueprint = Collection::findByHandle($handle)->entryBlueprint();
        $mapping = config("inspace.writable.{$handle}.fields", []);
        $out = [];

        foreach ($mapping as $apiName => $blueprintHandle) {
            $field = $blueprint->field($blueprintHandle);

            if ($field === null) {
                continue;
            }

            $out[$apiName] = $this->describe($field);
        }

        $out['status'] = ['type' => 'enum', 'required' => false, 'values' => ['draft', 'published']];
        $out['external_id'] = ['type' => 'string', 'required' => false];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Field $field): array
    {
        $required = $field->isRequired();

        return match ($field->type()) {
            'bard' => [
                'type' => 'blocks',
                'required' => $required,
                'writable_types' => ['text'],
                'opaque_types' => (new SetTypes)->of($field),
                'allowed_html' => (new HtmlWhitelist)->of($field),
            ],
            'assets' => ['type' => 'asset', 'required' => $required],
            'terms' => [
                'type' => 'enum',
                'required' => $required,
                'values' => (new TermValues)->of($field),
            ],
            'toggle' => ['type' => 'bool', 'required' => $required],
            'date' => ['type' => 'date', 'required' => $required],
            default => array_filter([
                'type' => 'string',
                'required' => $required,
                'max' => $field->get('character_limit'),
            ], fn ($v) => $v !== null),
        };
    }
}
```

- [ ] **Step 6: Write the three small collaborators**

`app/Inspace/SetTypes.php`:

```php
<?php

namespace App\Inspace;

use Statamic\Fields\Field;

class SetTypes
{
    /**
     * De set-handles van een Bard-veld. Bard nest ze onder een groep
     * (`sets.<groep>.sets.<handle>`), ook wanneer er maar een groep is.
     *
     * @return list<string>
     */
    public function of(Field $field): array
    {
        $groups = $field->get('sets', []);
        $handles = [];

        foreach ($groups as $group) {
            foreach (array_keys($group['sets'] ?? []) as $handle) {
                $handles[] = (string) $handle;
            }
        }

        return $handles;
    }
}
```

`app/Inspace/HtmlWhitelist.php`:

```php
<?php

namespace App\Inspace;

use Statamic\Fields\Field;

class HtmlWhitelist
{
    /**
     * Bard-button => de tags die hij toestaat. `p` en `br` staan altijd toe:
     * die horen bij de basisdoc en hebben geen button.
     */
    private const TAGS = [
        'h1' => ['h1'],
        'h2' => ['h2'],
        'h3' => ['h3'],
        'h4' => ['h4'],
        'h5' => ['h5'],
        'h6' => ['h6'],
        'bold' => ['strong', 'b'],
        'italic' => ['em', 'i'],
        'underline' => ['u'],
        'strikethrough' => ['s'],
        'unorderedlist' => ['ul', 'li'],
        'orderedlist' => ['ol', 'li'],
        'anchor' => ['a'],
        'table' => ['table', 'thead', 'tbody', 'tr', 'th', 'td'],
        'image' => ['img'],
        'quote' => ['blockquote'],
        'code' => ['code'],
        'codeblock' => ['pre', 'code'],
        'horizontalrule' => ['hr'],
    ];

    private const ALWAYS = ['p', 'br'];

    /**
     * @return list<string>
     */
    public function of(Field $field): array
    {
        $tags = self::ALWAYS;

        foreach ($field->get('buttons', []) as $button) {
            $tags = array_merge($tags, self::TAGS[$button] ?? []);
        }

        return array_values(array_unique($tags));
    }
}
```

`app/Inspace/TermValues.php`:

```php
<?php

namespace App\Inspace;

use Statamic\Facades\Taxonomy;
use Statamic\Fields\Field;

class TermValues
{
    /**
     * @return list<string>
     */
    public function of(Field $field): array
    {
        $slugs = [];

        foreach ($field->get('taxonomies', []) as $handle) {
            $taxonomy = Taxonomy::findByHandle($handle);

            if ($taxonomy === null) {
                continue;
            }

            foreach ($taxonomy->queryTerms()->get() as $term) {
                $slugs[] = $term->slug();
            }
        }

        return array_values(array_unique($slugs));
    }
}
```

- [ ] **Step 7: Write the controller and routes**

`app/Http/Controllers/Inspace/SchemaController.php`:

```php
<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use App\Inspace\SchemaBuilder;
use Illuminate\Http\JsonResponse;

class SchemaController extends Controller
{
    public function __invoke(SchemaBuilder $schema): JsonResponse
    {
        return response()->json($schema->build());
    }
}
```

`routes/inspace.php`:

```php
<?php

use App\Http\Controllers\Inspace\SchemaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/inspace/v1')
    ->middleware(['inspace.token', 'throttle:inspace'])
    ->group(function () {
        Route::get('schema', SchemaController::class);
    });
```

- [ ] **Step 8: Register routes, middleware and rate limiter**

In `bootstrap/app.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], __DIR__.'/../routes/inspace.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['inspace.token' => \App\Http\Middleware\InspaceToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

En in `AppServiceProvider::boot()`, bij de andere registraties:

```php
RateLimiter::for('inspace', function (Request $request) {
    return Limit::perMinute((int) config('inspace.rate_limit', 120))
        ->by((string) $request->attributes->get('inspace_token_label', $request->ip()));
});
```

Voeg in `.env.example` toe, onder een eigen kopje:

```
INSPACE_TOKEN_NOVA=
INSPACE_RATE_LIMIT=120
```

- [ ] **Step 9: Write the schema test**

```php
<?php

namespace Tests\Feature\Inspace;

use Tests\TestCase;

class SchemaTest extends TestCase
{
    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_schema_describes_articles_as_writable(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->assertOk()
            ->assertJsonPath('collections.articles.writable', true)
            ->assertJsonPath('collections.articles.route', '/nieuws/{slug}')
            ->assertJsonPath('collections.articles.fields.title.required', true)
            ->assertJsonPath('collections.articles.fields.content.type', 'blocks')
            ->assertJsonPath('collections.articles.fields.content.writable_types', ['text'])
            ->assertJsonPath('collections.articles.fields.content.opaque_types', ['video']);
    }

    public function test_theme_values_come_live_from_the_taxonomy(): void
    {
        $response = $this->withToken(self::TOKEN)->getJson('/api/inspace/v1/schema');

        $values = $response->json('collections.articles.fields.theme.values');

        sort($values);

        $this->assertSame(
            ['energie-en-comfort', 'ramen-en-deuren', 'terrasoverkapping', 'zonwering'],
            $values,
            'De themawaarden moeten uit de taxonomie komen, niet uit config.'
        );
        $this->assertTrue($response->json('collections.articles.fields.theme.required'));
    }

    public function test_allowed_html_is_derived_from_the_bard_buttons(): void
    {
        $allowed = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->json('collections.articles.fields.content.allowed_html');

        $this->assertContains('h2', $allowed);
        $this->assertContains('img', $allowed);
        $this->assertContains('table', $allowed);
        $this->assertNotContains('h1', $allowed, 'h1 staat niet in de buttonconfig.');
        $this->assertNotContains('blockquote', $allowed);
    }
}
```

- [ ] **Step 10: Run both test files**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/`
Expected: PASS, 6 tests.

- [ ] **Step 11: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/inspace.php routes/inspace.php app/Http/Middleware/InspaceToken.php app/Inspace/ app/Http/Controllers/Inspace/ bootstrap/app.php app/Providers/AppServiceProvider.php .env.example tests/Feature/Inspace/
git commit -m "feat(inspace): bearer-auth, routegroep en GET /schema"
```

---

### Task 3: `GET /pages` — de lijst

Lezen is breder dan schrijven: alle collecties komen terug, alleen `articles` is `editable`.

**Files:**
- Create: `app/Inspace/EntryLister.php`
- Create: `app/Http/Controllers/Inspace/PageController.php`
- Modify: `routes/inspace.php`
- Test: `tests/Feature/Inspace/PageIndexTest.php`

**Interfaces:**
- Consumes: `config('inspace.readable')`, `config('inspace.writable')`
- Produces:
  - `App\Inspace\EntryLister::list(array $filters): array{data: list<array>, meta: array{page: int, per_page: int, total: int}}`
  - `App\Inspace\SiteGuard::resolve(?string $requested): string` — Task 9 en 10 laten `site` via `PayloadValidator::EXTRA` door

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inspace;

use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageIndexTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_articles_are_listed_as_editable(): void
    {
        $entry = $this->temporaryEntry('articles', 'lijsttest-artikel', [
            'title' => 'Lijsttest artikel',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
        ]);

        $row = collect(
            $this->withToken(self::TOKEN)
                ->getJson('/api/inspace/v1/pages?per_page=200')
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $entry->id());

        $this->assertNotNull($row, 'Het aangemaakte artikel moet in de lijst staan.');
        $this->assertTrue($row['editable']);
        $this->assertSame('articles', $row['collection']);
        $this->assertStringContainsString('/nieuws/lijsttest-artikel', $row['url']);
    }

    public function test_other_collections_are_listed_but_not_editable(): void
    {
        $rows = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection=products&per_page=200')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($rows, 'Producten moeten leesbaar zijn: Nova legt er interne links naartoe.');

        foreach ($rows as $row) {
            $this->assertFalse($row['editable']);
        }
    }

    public function test_pagination_caps_at_two_hundred(): void
    {
        $meta = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?per_page=5000')
            ->assertOk()
            ->json('meta');

        $this->assertSame(200, $meta['per_page']);
    }

    public function test_quicklinks_have_no_url(): void
    {
        $rows = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages?collection=quicklinks&per_page=200')
            ->assertOk()
            ->json('data');

        foreach ($rows as $row) {
            $this->assertNull($row['url'], 'quicklinks heeft geen route en is dus geen bruikbaar linkdoel.');
        }
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageIndexTest.php`
Expected: FAIL met 404 — `/pages` bestaat nog niet.

- [ ] **Step 3: Write the EntryLister**

```php
<?php

namespace App\Inspace;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryLister
{
    private const MAX_PER_PAGE = 200;

    /**
     * @param  array{collection?: ?string, editable?: ?bool, status?: ?string, page?: ?int, per_page?: ?int}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function list(array $filters): array
    {
        // Ook een ondergrens: array_slice leest een negatieve lengte als
        // "stop N vóór het einde", wat een 200 met te veel rijen oplevert.
        $perPage = max(min((int) ($filters['per_page'] ?? 50), self::MAX_PER_PAGE), 1);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $entries = collect($this->handles($filters['collection'] ?? null))
            ->flatMap(fn (string $handle) => Entry::query()->where('collection', $handle)->get()->all())
            ->filter(fn (EntryContract $entry) => $this->visible($entry, $filters))
            ->values();

        $rows = $entries
            ->slice(($page - 1) * $perPage, $perPage)
            ->map($this->row(...))
            ->values()
            ->all();

        return [
            'data' => $rows,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $entries->count()],
        ];
    }

    /**
     * @return list<string>
     */
    private function handles(?string $requested): array
    {
        $readable = config('inspace.readable') ?? Collection::handles()->all();
        $readable = array_values($readable);

        if ($requested === null) {
            return $readable;
        }

        return in_array($requested, $readable, true) ? [$requested] : [];
    }

    /**
     * Drafts van schrijfbare collecties blijven zichtbaar: Nova moet een
     * artikel dat het zelf als draft aanmaakte kunnen terugvinden. Drafts van
     * de rest zijn intern werk van de klant.
     *
     * @param  array<string, mixed>  $filters
     */
    private function visible(EntryContract $entry, array $filters): bool
    {
        $editable = $this->editable($entry);

        if (! $editable && ! $entry->published()) {
            return false;
        }

        if (isset($filters['editable']) && $filters['editable'] !== null && $editable !== (bool) $filters['editable']) {
            return false;
        }

        $status = $filters['status'] ?? null;

        return $status === null || $this->status($entry) === $status;
    }

    private function editable(EntryContract $entry): bool
    {
        return array_key_exists($entry->collectionHandle(), config('inspace.writable', []));
    }

    private function status(EntryContract $entry): string
    {
        return $entry->published() ? 'published' : 'draft';
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EntryContract $entry): array
    {
        return [
            'id' => $entry->id(),
            'collection' => $entry->collectionHandle(),
            'title' => $entry->value('title'),
            'url' => $entry->url(),
            'status' => $this->status($entry),
            'updated_at' => $entry->lastModified()?->toIso8601String(),
            'editable' => $this->editable($entry),
        ];
    }
}
```

- [ ] **Step 4: Write the controller and route**

`app/Http/Controllers/Inspace/PageController.php`:

```php
<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use App\Inspace\EntryLister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request, EntryLister $lister): JsonResponse
    {
        return response()->json($lister->list([
            'collection' => $request->query('collection'),
            'editable' => $request->has('editable') ? $request->boolean('editable') : null,
            'status' => $request->query('status'),
            'page' => $request->query('page'),
            'per_page' => $request->query('per_page'),
        ]));
    }
}
```

In `routes/inspace.php`, binnen de bestaande groep:

```php
Route::get('pages', [PageController::class, 'index']);
```

- [ ] **Step 5: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageIndexTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 6: Add the `site` parameter**

De spec neemt `site` nu al op zodat er later geen tweede contractversie naar Inspace moet. Winsol-brebo draait single-site: `resources/sites.yaml` bevat één site met handle **`nl`** (geverifieerd via `Site::default()->handle()`; `Site::multiEnabled()` is `false`). De parameter wordt geaccepteerd en gevalideerd, niet genegeerd — stil slikken zou Nova laten geloven dat het naar een FR-site schrijft die niet bestaat.

Voeg toe aan `app/Inspace/SiteGuard.php`:

```php
<?php

namespace App\Inspace;

use Illuminate\Validation\ValidationException;
use Statamic\Facades\Site;

class SiteGuard
{
    /**
     * @throws ValidationException
     */
    public function resolve(?string $requested): string
    {
        $default = Site::default()->handle();

        if ($requested === null || $requested === $default) {
            return $default;
        }

        if (Site::get($requested) === null) {
            throw ValidationException::withMessages([
                'site' => 'Onbekende site. Beschikbaar: '.Site::all()->map->handle()->implode(', ').'.',
            ]);
        }

        return $requested;
    }
}
```

Roep hem aan in `PageController::index()`, vóór de `$lister->list()`:

```php
app(SiteGuard::class)->resolve($request->query('site'));
```

En voeg `site` toe aan `PayloadValidator::EXTRA` in Task 9, zodat een `site` in de body van een `POST` of `PATCH` niet als onbekend veld wordt afgewezen:

```php
private const EXTRA = ['status', 'external_id', 'site'];
```

Test in `tests/Feature/Inspace/PageIndexTest.php`:

```php
public function test_the_default_site_is_accepted_and_an_unknown_one_is_rejected(): void
{
    $this->withToken(self::TOKEN)
        ->getJson('/api/inspace/v1/pages?site=nl&per_page=1')
        ->assertOk();

    $this->withToken(self::TOKEN)
        ->getJson('/api/inspace/v1/pages?site=fr&per_page=1')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['site']]);
}
```

De handle is `nl`, niet `default` — geverifieerd tegen `resources/sites.yaml` en `Site::default()->handle()`.

- [ ] **Step 7: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageIndexTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Inspace/EntryLister.php app/Inspace/SiteGuard.php app/Http/Controllers/Inspace/PageController.php routes/inspace.php tests/Feature/Inspace/PageIndexTest.php
git commit -m "feat(inspace): GET /pages over alle leesbare collecties, met site-parameter"
```

---

### Task 4: `BlockConverter` — ProseMirror ↔ blokkenlijst

Het hart van het contract. Twee eigenschappen die de spec hard maakt en die deze klasse levert: een ongewijzigd text-blok laat de opslag byte-identiek (geen `textAlign`), en een opaque set wordt op `id` teruggezocht in plaats van uit de payload herbouwd.

**Files:**
- Create: `app/Inspace/BlockConverter.php`
- Create: `app/Inspace/UnknownBlockException.php`
- Test: `tests/Unit/Inspace/BlockConverterTest.php`

**Interfaces:**
- Consumes: niets uit eerdere taken
- Produces:
  - `App\Inspace\BlockConverter::__construct(Statamic\Fields\Field $field, ?callable $transformHtml = null)`
  - `->toBlocks(array $nodes): list<array{type: string, html?: string, id?: string, opaque?: bool}>`
  - `->toProsemirror(array $blocks, array $originalNodes): list<array>`
  - `App\Inspace\UnknownBlockException` — vertaalt in Task 10 naar een `422`
  - `$transformHtml` krijgt de HTML van een **gewijzigd** text-blok en geeft de bewerkte HTML terug. Task 5 en 6 vullen die haak. Ongewijzigde blokken gaan er niet doorheen — hun opslag wordt hergebruikt.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Inspace;

use App\Inspace\BlockConverter;
use App\Inspace\UnknownBlockException;
use Statamic\Facades\Collection;
use Tests\TestCase;

class BlockConverterTest extends TestCase
{
    private function converter(?callable $transform = null): BlockConverter
    {
        $field = Collection::findByHandle('articles')->entryBlueprint()->field('redactor');

        return new BlockConverter($field, $transform);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nodes(): array
    {
        return [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Kop']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eerste.']]],
            ['type' => 'set', 'attrs' => ['id' => 'vid01', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tweede.']]],
        ];
    }

    public function test_sets_split_the_stream_into_blocks(): void
    {
        $blocks = $this->converter()->toBlocks($this->nodes());

        $this->assertCount(3, $blocks);
        $this->assertSame('text', $blocks[0]['type']);
        $this->assertStringContainsString('<h2>Kop</h2>', $blocks[0]['html']);
        $this->assertStringContainsString('<p>Eerste.</p>', $blocks[0]['html']);

        $this->assertSame(['type' => 'video', 'id' => 'vid01', 'opaque' => true], $blocks[1]);

        $this->assertSame('text', $blocks[2]['type']);
        $this->assertStringContainsString('<p>Tweede.</p>', $blocks[2]['html']);
    }

    public function test_an_unchanged_round_trip_leaves_the_storage_byte_identical(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $back = $converter->toProsemirror($converter->toBlocks($nodes), $nodes);

        $this->assertSame(
            json_encode($nodes),
            json_encode($back),
            'Een ongewijzigde ronde mag geen textAlign toevoegen: hergebruik de opgeslagen nodes.'
        );
    }

    public function test_a_rewritten_block_is_parsed_and_the_rest_is_untouched(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();
        $blocks = $converter->toBlocks($nodes);

        $blocks[0]['html'] = '<h2>Nieuwe kop</h2><p>Herschreven.</p>';

        $back = $converter->toProsemirror($blocks, $nodes);

        $this->assertSame('Nieuwe kop', $back[0]['content'][0]['text']);
        $this->assertSame('left', $back[0]['attrs']['textAlign'], 'Een herschreven blok mag wel normaliseren.');
        $this->assertSame($nodes[2], $back[2], 'De set moet ongewijzigd terugkomen.');
        $this->assertSame($nodes[3], $back[3], 'Het niet-herschreven blok blijft byte-identiek.');
    }

    public function test_an_opaque_box_is_restored_from_storage_not_from_the_payload(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $back = $converter->toProsemirror([
            ['type' => 'video', 'id' => 'vid01', 'opaque' => true],
        ], $nodes);

        $this->assertSame([$nodes[2]], $back);
        $this->assertSame('https://youtu.be/x', $back[0]['attrs']['values']['video']);
    }

    public function test_an_omitted_box_is_deleted_and_a_reordered_one_moves(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $onlyText = $converter->toProsemirror([
            ['type' => 'text', 'html' => $converter->toBlocks($nodes)[2]['html']],
        ], $nodes);

        $this->assertSame([$nodes[3]], $onlyText, 'Een weggelaten doos verdwijnt.');

        $reordered = $converter->toProsemirror([
            ['type' => 'video', 'id' => 'vid01', 'opaque' => true],
            ['type' => 'text', 'html' => $converter->toBlocks($nodes)[2]['html']],
        ], $nodes);

        $this->assertSame($nodes[2], $reordered[0]);
        $this->assertSame($nodes[3], $reordered[1]);
    }

    public function test_an_unknown_box_id_throws(): void
    {
        $this->expectException(UnknownBlockException::class);

        $this->converter()->toProsemirror([
            ['type' => 'video', 'id' => 'verzonnen', 'opaque' => true],
        ], $this->nodes());
    }

    public function test_the_transform_hook_only_sees_changed_blocks(): void
    {
        $seen = [];
        $converter = $this->converter(function (string $html) use (&$seen): string {
            $seen[] = $html;

            return $html;
        });

        $nodes = $this->nodes();
        $blocks = $converter->toBlocks($nodes);
        $blocks[0]['html'] = '<p>Anders.</p>';

        $converter->toProsemirror($blocks, $nodes);

        $this->assertSame(['<p>Anders.</p>'], $seen);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/BlockConverterTest.php`
Expected: FAIL — `Class "App\Inspace\BlockConverter" not found`.

- [ ] **Step 3: Write the exception**

```php
<?php

namespace App\Inspace;

use RuntimeException;

class UnknownBlockException extends RuntimeException
{
    public function __construct(public readonly ?string $blockId)
    {
        parent::__construct(sprintf(
            'Onbekend blok-id: %s. Stuur opaque blokken ongewijzigd terug zoals je ze kreeg.',
            $blockId ?? '(ontbreekt)'
        ));
    }
}
```

- [ ] **Step 4: Write the converter**

```php
<?php

namespace App\Inspace;

use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor;

class BlockConverter
{
    private Augmentor $augmentor;

    /** @var ?callable(string): string */
    private $transformHtml;

    public function __construct(Field $field, ?callable $transformHtml = null)
    {
        // Uit het echte veld, niet uit een handgemaakte Field: alleen zo
        // dragen de buttons en de sets de configuratie van deze site.
        $this->augmentor = new Augmentor($field->fieldtype());
        $this->transformHtml = $transformHtml;
    }

    /**
     * Splitst de opgeslagen nodes op elke set. Aaneengesloten prozaknopen
     * worden een text-blok; een set wordt een dichte doos die alleen zijn
     * type en row-id draagt.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public function toBlocks(array $nodes): array
    {
        $blocks = [];
        $run = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'set') {
                $run[] = $node;

                continue;
            }

            if ($run !== []) {
                $blocks[] = ['type' => 'text', 'html' => $this->render($run)];
                $run = [];
            }

            $blocks[] = [
                'type' => (string) ($node['attrs']['values']['type'] ?? 'unknown'),
                'id' => (string) ($node['attrs']['id'] ?? ''),
                'opaque' => true,
            ];
        }

        if ($run !== []) {
            $blocks[] = ['type' => 'text', 'html' => $this->render($run)];
        }

        return $blocks;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $originalNodes
     * @return list<array<string, mixed>>
     *
     * @throws UnknownBlockException
     */
    public function toProsemirror(array $blocks, array $originalNodes): array
    {
        $sets = $this->setsById($originalNodes);
        $unchanged = $this->runsByHtml($originalNodes);
        $out = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $html = (string) ($block['html'] ?? '');

                // De vergelijking gebeurt op de binnenkomende HTML zoals die
                // is, vóór enige transformatie: alleen dan staat hij naast
                // wat een GET op dit moment zou teruggeven.
                $out = array_merge($out, $unchanged[$html] ?? $this->parse($html));

                continue;
            }

            $id = $block['id'] ?? null;

            if ($id === null || ! isset($sets[$id])) {
                throw new UnknownBlockException($id === null ? null : (string) $id);
            }

            $out[] = $sets[$id];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function setsById(array $nodes): array
    {
        $sets = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'set' && isset($node['attrs']['id'])) {
                $sets[(string) $node['attrs']['id']] = $node;
            }
        }

        return $sets;
    }

    /**
     * De HTML van elke prozaloop naar de nodes die hem opleverden, zodat een
     * ongewijzigd blok zijn opslag terugkrijgt in plaats van een geparste
     * kopie met textAlign erop.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, list<array<string, mixed>>>
     */
    private function runsByHtml(array $nodes): array
    {
        $runs = [];
        $run = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'set') {
                $run[] = $node;

                continue;
            }

            if ($run !== []) {
                $runs[$this->render($run)] = $run;
                $run = [];
            }
        }

        if ($run !== []) {
            $runs[$this->render($run)] = $run;
        }

        return $runs;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function render(array $nodes): string
    {
        return $this->augmentor->renderProsemirrorToHtml(['type' => 'doc', 'content' => $nodes]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(string $html): array
    {
        if ($this->transformHtml !== null) {
            $html = ($this->transformHtml)($html);
        }

        return $this->augmentor->renderHtmlToProsemirror($html)['content'] ?? [];
    }
}
```

- [ ] **Step 5: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/BlockConverterTest.php`
Expected: PASS, 7 tests.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Inspace/BlockConverter.php app/Inspace/UnknownBlockException.php tests/Unit/Inspace/BlockConverterTest.php
git commit -m "feat(inspace): blokkenconverter met hergebruik van ongewijzigde opslag"
```

---

### Task 5: `HtmlSanitizer` — whitelist uit de buttonconfig

Wat gestript wordt moet zichtbaar zijn, niet stil verdwijnen. Nova stuurt gegarandeerd ooit `<h1>` of `<blockquote>`.

**Files:**
- Create: `app/Inspace/HtmlSanitizer.php`
- Test: `tests/Unit/Inspace/HtmlSanitizerTest.php`

**Interfaces:**
- Consumes: `App\Inspace\HtmlWhitelist` uit Task 2
- Produces: `App\Inspace\HtmlSanitizer::__construct(array $allowedTags)`, `->clean(string $html): string`, `->warnings(): list<string>`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Inspace;

use App\Inspace\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private function sanitizer(): HtmlSanitizer
    {
        return new HtmlSanitizer(['p', 'br', 'h2', 'h3', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'img']);
    }

    public function test_allowed_markup_survives(): void
    {
        $html = '<h2>Kop</h2><p>Tekst met <strong>nadruk</strong> en <a href="/over-ons">link</a>.</p><ul><li>een</li></ul>';

        $sanitizer = $this->sanitizer();

        $this->assertSame($html, $sanitizer->clean($html));
        $this->assertSame([], $sanitizer->warnings());
    }

    public function test_a_disallowed_tag_is_unwrapped_and_reported(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean('<h1>Titel</h1><p>Blijft.</p>');

        $this->assertStringNotContainsString('<h1>', $out);
        $this->assertStringContainsString('Titel', $out, 'De tekst blijft, alleen de tag verdwijnt.');
        $this->assertStringContainsString('<p>Blijft.</p>', $out);
        $this->assertSame(['Tag <h1> is niet toegestaan en is verwijderd.'], $sanitizer->warnings());
    }

    public function test_each_disallowed_tag_is_reported_once(): void
    {
        $sanitizer = $this->sanitizer();

        $sanitizer->clean('<blockquote>een</blockquote><blockquote>twee</blockquote>');

        $this->assertSame(['Tag <blockquote> is niet toegestaan en is verwijderd.'], $sanitizer->warnings());
    }

    public function test_style_and_script_are_removed_with_their_content(): void
    {
        $sanitizer = $this->sanitizer();

        $out = $sanitizer->clean('<p>Blijft.</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('alert', $out, 'Script-inhoud mag niet als tekst overblijven.');
        $this->assertStringContainsString('<p>Blijft.</p>', $out);
    }

    public function test_utf8_survives(): void
    {
        $sanitizer = $this->sanitizer();

        $this->assertStringContainsString('één café', $sanitizer->clean('<p>één café</p>'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/HtmlSanitizerTest.php`
Expected: FAIL — `Class "App\Inspace\HtmlSanitizer" not found`.

- [ ] **Step 3: Write the sanitizer**

```php
<?php

namespace App\Inspace;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /**
     * Tags waarvan ook de inhoud weg moet. Bij de rest wordt alleen de tag
     * uitgepakt en blijft de tekst staan, zodat een herschreven alinea niet
     * stil leeg raakt.
     */
    private const DROP_CONTENT = ['script', 'style', 'iframe', 'object', 'embed'];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  list<string>  $allowedTags
     */
    public function __construct(private readonly array $allowedTags) {}

    public function clean(string $html): string
    {
        $this->warnings = [];

        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // Zonder de meta-tag leest DOMDocument de bytes als latin-1 en
        // verminkt hij elk accent. De flags houden de wrapper-html en het
        // doctype eruit.
        $document->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->walk($document);

        $out = '';

        foreach (iterator_to_array($document->childNodes) as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'meta') {
                continue;
            }

            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if ($tag === 'meta' || in_array($tag, $this->allowedTags, true)) {
                $this->walk($child);

                continue;
            }

            $this->warn($tag);

            if (in_array($tag, self::DROP_CONTENT, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->unwrap($child);
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);

        // De uitgepakte kinderen staan nu op het niveau van de ouder en zijn
        // door de lopende iteratie al gepasseerd, dus die moet opnieuw.
        $this->walk($parent);
    }

    private function warn(string $tag): void
    {
        $message = "Tag <{$tag}> is niet toegestaan en is verwijderd.";

        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/HtmlSanitizerTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Inspace/HtmlSanitizer.php tests/Unit/Inspace/HtmlSanitizerTest.php
git commit -m "feat(inspace): sanitizer met whitelist uit de buttonconfig"
```

---

### Task 6: `LinkResolver` en `ImageResolver`

Twee transformaties op binnenkomende HTML. Links moeten een slug-wijziging overleven; afbeeldingen moeten aan een asset hangen, want alleen dan resolvet Statamic de alt-tekst.

**Files:**
- Create: `app/Inspace/LinkResolver.php`
- Create: `app/Inspace/ImageResolver.php`
- Create: `app/Inspace/ExternalImageException.php`
- Test: `tests/Unit/Inspace/LinkResolverTest.php`, `tests/Unit/Inspace/ImageResolverTest.php`

**Interfaces:**
- Consumes: niets
- Produces:
  - `App\Inspace\LinkResolver::toStatamic(string $html): string`
  - `App\Inspace\ImageResolver::toAssetRefs(string $html): string` — gooit `ExternalImageException` bij een `src` die niet naar een asset in de container wijst
  - `App\Inspace\ImageResolver::warnings(): list<string>` — meldt genegeerde `alt`-attributen
  - `App\Inspace\ExternalImageException`

De uitgaande richting hoeft geen code: Statamic's `ImageNode::renderHTML()` zet `asset::<uuid>` zelf om naar een echte URL plus de alt van het asset, en `Augmentor` doet hetzelfde voor `statamic://`-links.

- [ ] **Step 1: Write the failing LinkResolver test**

```php
<?php

namespace Tests\Unit\Inspace;

use App\Inspace\LinkResolver;
use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class LinkResolverTest extends TestCase
{
    use CreatesTemporaryContent;

    public function test_a_url_that_points_at_an_entry_becomes_a_statamic_reference(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-artikel', [
            'title' => 'Linktest',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic('<p><a href="'.$entry->url().'">lees dit</a></p>');

        $this->assertStringContainsString('href="statamic://entry::'.$entry->id().'"', $out);
    }

    public function test_an_absolute_url_is_matched_on_its_path(): void
    {
        $entry = $this->temporaryEntry('articles', 'linktest-absoluut', [
            'title' => 'Absoluut',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
        ]);

        $out = (new LinkResolver)->toStatamic('<p><a href="'.$entry->absoluteUrl().'">x</a></p>');

        $this->assertStringContainsString('statamic://entry::'.$entry->id(), $out);
    }

    public function test_an_external_link_is_left_alone(): void
    {
        $html = '<p><a href="https://www.example.com/iets">extern</a></p>';

        $this->assertSame($html, (new LinkResolver)->toStatamic($html));
    }

    public function test_an_unknown_internal_path_is_left_alone(): void
    {
        $html = '<p><a href="/bestaat-niet-12345">x</a></p>';

        $this->assertSame($html, (new LinkResolver)->toStatamic($html));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/LinkResolverTest.php`
Expected: FAIL — `Class "App\Inspace\LinkResolver" not found`.

- [ ] **Step 3: Write the LinkResolver**

```php
<?php

namespace App\Inspace;

use Statamic\Facades\Entry;

class LinkResolver
{
    /**
     * Binnenkomend: een href die naar een bestaande entry wijst wordt een
     * statamic://-referentie, zodat de link een slug-wijziging overleeft.
     * De uitgaande richting doet Statamic's eigen Augmentor.
     */
    public function toStatamic(string $html): string
    {
        return preg_replace_callback(
            '/href="([^"]+)"/i',
            function (array $match): string {
                $id = $this->entryId($match[1]);

                return $id === null ? $match[0] : 'href="statamic://entry::'.$id.'"';
            },
            $html
        ) ?? $html;
    }

    private function entryId(string $href): ?string
    {
        $path = parse_url($href, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $host = parse_url($href, PHP_URL_HOST);

        if ($host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return null;
        }

        return Entry::findByUri('/'.ltrim($path, '/'))?->id();
    }
}
```

- [ ] **Step 4: Run the LinkResolver test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/LinkResolverTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Write the failing ImageResolver test**

```php
<?php

namespace Tests\Unit\Inspace;

use App\Inspace\ExternalImageException;
use App\Inspace\ImageResolver;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class ImageResolverTest extends TestCase
{
    private function anyAssetUrl(): string
    {
        $asset = AssetContainer::findByHandle('assets')->assets()->first();

        $this->assertNotNull($asset, 'De assets-container moet minstens een bestand bevatten.');

        return $asset->url();
    }

    public function test_a_known_asset_url_becomes_an_asset_reference(): void
    {
        $url = $this->anyAssetUrl();

        $out = (new ImageResolver)->toAssetRefs('<p><img src="'.$url.'"></p>');

        $this->assertStringContainsString('src="asset::', $out);
        $this->assertStringNotContainsString($url, $out);
    }

    public function test_an_external_url_is_rejected(): void
    {
        $this->expectException(ExternalImageException::class);

        (new ImageResolver)->toAssetRefs('<p><img src="https://cdn.example.com/foo.jpg"></p>');
    }

    public function test_an_alt_attribute_is_dropped_and_reported(): void
    {
        $resolver = new ImageResolver;

        $out = $resolver->toAssetRefs('<p><img src="'.$this->anyAssetUrl().'" alt="een beschrijving"></p>');

        $this->assertStringNotContainsString('alt=', $out);
        $this->assertSame(
            ['Het alt-attribuut op een <img> is genegeerd. Zet de alt-tekst bij de upload via POST /media.'],
            $resolver->warnings()
        );
    }

    public function test_html_without_images_is_untouched(): void
    {
        $html = '<p>Geen beeld.</p>';

        $this->assertSame($html, (new ImageResolver)->toAssetRefs($html));
    }
}
```

- [ ] **Step 6: Write the ImageResolver and its exception**

`app/Inspace/ExternalImageException.php`:

```php
<?php

namespace App\Inspace;

use RuntimeException;

class ExternalImageException extends RuntimeException
{
    public function __construct(public readonly string $src)
    {
        parent::__construct(sprintf(
            'De afbeelding %s hoort niet bij deze site. Upload hem eerst via POST /media en gebruik de teruggegeven id.',
            $src
        ));
    }
}
```

`app/Inspace/ImageResolver.php`:

```php
<?php

namespace App\Inspace;

use Statamic\Facades\Asset;

class ImageResolver
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * Binnenkomend: elke <img src> moet naar een asset in onze container
     * wijzen en wordt herschreven naar asset::<uuid>. Alleen in die vorm
     * haalt Statamic's ImageNode de alt-tekst van het asset op; een kale URL
     * blijft een kale URL en krijgt nooit een alt.
     *
     * @throws ExternalImageException
     */
    public function toAssetRefs(string $html): string
    {
        $this->warnings = [];

        return preg_replace_callback(
            '/<img\b([^>]*)>/i',
            fn (array $match): string => '<img src="asset::'.$this->assetId($match[1]).'">',
            $html
        ) ?? $html;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function assetId(string $attributes): string
    {
        if (preg_match('/\balt="[^"]*"/i', $attributes) === 1) {
            $this->warn();
        }

        if (preg_match('/\bsrc="([^"]+)"/i', $attributes, $match) !== 1) {
            throw new ExternalImageException('(zonder src)');
        }

        $src = $match[1];

        if (str_starts_with($src, 'asset::')) {
            return substr($src, strlen('asset::'));
        }

        $asset = Asset::findByUrl($src) ?? Asset::findByUrl((string) parse_url($src, PHP_URL_PATH));

        if ($asset === null) {
            throw new ExternalImageException($src);
        }

        return $asset->id();
    }

    private function warn(): void
    {
        $message = 'Het alt-attribuut op een <img> is genegeerd. Zet de alt-tekst bij de upload via POST /media.';

        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }
}
```

- [ ] **Step 7: Run both tests**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspace/`
Expected: PASS — 7 BlockConverter, 5 HtmlSanitizer, 4 LinkResolver, 4 ImageResolver.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Inspace/LinkResolver.php app/Inspace/ImageResolver.php app/Inspace/ExternalImageException.php tests/Unit/Inspace/LinkResolverTest.php tests/Unit/Inspace/ImageResolverTest.php
git commit -m "feat(inspace): link- en afbeeldingsresolvers voor binnenkomende HTML"
```

---

### Task 7: `GET /pages/{id}` — het detail

Bindt Task 4 tot 6 samen aan de leeskant en levert de `EntryMapper` waar Task 9 en 10 op steunen.

**Files:**
- Create: `app/Inspace/EntryMapper.php`
- Modify: `app/Http/Controllers/Inspace/PageController.php`
- Modify: `routes/inspace.php`
- Test: `tests/Feature/Inspace/PageShowTest.php`

**Interfaces:**
- Consumes: `BlockConverter` (Task 4), `config('inspace.writable')` (Task 2)
- Produces:
  - `App\Inspace\EntryMapper::toApi(Statamic\Contracts\Entries\Entry $entry): array<string, mixed>`
  - `App\Inspace\EntryMapper::converterFor(string $collection): BlockConverter` — Task 10 gebruikt deze om de schrijfrichting te bouwen

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inspace;

use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageShowTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_an_article_returns_its_mapped_fields(): void
    {
        $entry = $this->temporaryEntry('articles', 'detailtest-artikel', [
            'title' => 'Detailtest',
            'text' => 'De intro.',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'meta_title' => 'Meta',
            'redactor' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Body.']]],
            ],
        ]);

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->assertOk()
            ->assertJsonPath('id', $entry->id())
            ->assertJsonPath('collection', 'articles')
            ->assertJsonPath('editable', true)
            ->assertJsonPath('title', 'Detailtest')
            ->assertJsonPath('intro', 'De intro.')
            ->assertJsonPath('theme', 'zonwering')
            ->assertJsonPath('meta_title', 'Meta')
            ->assertJsonPath('content.0.type', 'text')
            ->assertJsonPath('content.0.html', '<p>Body.</p>');
    }

    public function test_a_video_set_comes_back_as_an_opaque_box(): void
    {
        $entry = $this->temporaryEntry('articles', 'detailtest-video', [
            'title' => 'Video',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'redactor' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Voor.']]],
                ['type' => 'set', 'attrs' => ['id' => 'vid99', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ],
        ]);

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->assertOk()
            ->assertJsonPath('content.1', ['type' => 'video', 'id' => 'vid99', 'opaque' => true]);
    }

    public function test_a_non_writable_entry_has_no_content(): void
    {
        $product = Entry::query()->where('collection', 'products')->first();

        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$product->id())
            ->assertOk()
            ->assertJsonPath('editable', false)
            ->assertJsonPath('content', null);
    }

    public function test_an_unknown_id_gives_404(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/bestaat-niet')
            ->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageShowTest.php`
Expected: FAIL met 404 op alle vier — de route bestaat nog niet.

- [ ] **Step 3: Write the EntryMapper**

```php
<?php

namespace App\Inspace;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;

class EntryMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toApi(EntryContract $entry): array
    {
        $handle = $entry->collectionHandle();
        $editable = $this->isWritable($handle);

        $base = [
            'id' => $entry->id(),
            'collection' => $handle,
            'editable' => $editable,
            'status' => $entry->published() ? 'published' : 'draft',
            'url' => $entry->url(),
            'title' => $entry->value('title'),
            'updated_at' => $entry->lastModified()?->toIso8601String(),
        ];

        if (! $editable) {
            return $base + [
                'content' => null,
                'meta_title' => $entry->value('meta_title'),
                'meta_description' => $entry->value('meta_description'),
                'meta_image' => $this->assetId($entry->value('meta_image')),
                'seo_noindex' => (bool) $entry->value('seo_noindex'),
            ];
        }

        $out = $base + ['external_id' => $entry->get('external_id')];

        foreach ($this->mapping($handle) as $apiName => $blueprintHandle) {
            $out[$apiName] = $this->read($entry, $handle, $apiName, $blueprintHandle);
        }

        return $out;
    }

    public function converterFor(string $collection): BlockConverter
    {
        return new BlockConverter($this->contentField($collection));
    }

    public function isWritable(string $collection): bool
    {
        return array_key_exists($collection, config('inspace.writable', []));
    }

    /**
     * @return array<string, string>
     */
    public function mapping(string $collection): array
    {
        return config("inspace.writable.{$collection}.fields", []);
    }

    public function contentApiName(string $collection): ?string
    {
        $contentField = config("inspace.writable.{$collection}.content_field");

        return array_search($contentField, $this->mapping($collection), true) ?: null;
    }

    private function contentField(string $collection): \Statamic\Fields\Field
    {
        return Collection::findByHandle($collection)
            ->entryBlueprint()
            ->field((string) config("inspace.writable.{$collection}.content_field"));
    }

    private function read(EntryContract $entry, string $collection, string $apiName, string $blueprintHandle): mixed
    {
        if ($apiName === $this->contentApiName($collection)) {
            return $this->converterFor($collection)->toBlocks((array) $entry->get($blueprintHandle, []));
        }

        $raw = $blueprintHandle === 'slug' ? $entry->slug() : $entry->get($blueprintHandle);

        return match (true) {
            $apiName === 'theme' => $this->firstOf($raw),
            $apiName === 'image' || $apiName === 'meta_image' => $this->assetId($raw),
            $apiName === 'seo_noindex' => (bool) $raw,
            $apiName === 'date' => $entry->date()?->toDateString(),
            default => $raw,
        };
    }

    private function firstOf(mixed $value): ?string
    {
        $first = is_array($value) ? ($value[0] ?? null) : $value;

        return $first === null ? null : (string) $first;
    }

    private function assetId(mixed $value): ?string
    {
        return $this->firstOf($value);
    }
}
```

- [ ] **Step 4: Add the controller method and route**

In `app/Http/Controllers/Inspace/PageController.php`:

```php
use App\Inspace\EntryMapper;
use Statamic\Facades\Entry;

public function show(string $id, EntryMapper $mapper): JsonResponse
{
    $entry = Entry::find($id);

    if ($entry === null) {
        return response()->json(['message' => 'Onbekende entry.'], 404);
    }

    return response()->json($mapper->toApi($entry));
}
```

In `routes/inspace.php`, binnen de groep:

```php
Route::get('pages/{id}', [PageController::class, 'show']);
```

- [ ] **Step 5: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageShowTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Inspace/EntryMapper.php app/Http/Controllers/Inspace/PageController.php routes/inspace.php tests/Feature/Inspace/PageShowTest.php
git commit -m "feat(inspace): GET /pages/{id} met blokkenlijst en opaque sets"
```

---

### Task 8: `POST /media`

De upload moet door Statamic's eigen pad, anders vuurt `AssetUploaded` niet en slaat `CompressUploadedAsset` over — precies het tegendeel van wat een SEO-dienst moet bereiken.

**Files:**
- Create: `app/Http/Controllers/Inspace/MediaController.php`
- Modify: `routes/inspace.php`
- Test: `tests/Feature/Inspace/MediaUploadTest.php`

**Interfaces:**
- Consumes: `config('inspace.assets')` (Task 2)
- Produces: `POST /api/inspace/v1/media` → `array{id: string, url: string, width: ?int, height: ?int, filename: string, alt: ?string}`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inspace;

use Illuminate\Http\UploadedFile;
use Statamic\Facades\Asset;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
        $this->fakeAssetDisk();
    }

    public function test_an_upload_returns_an_asset_id_and_stores_the_alt(): void
    {
        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => UploadedFile::fake()->image('nova-beeld.jpg', 1200, 800),
            'alt' => 'Een zip-screen op een zuidgevel',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'url', 'width', 'height', 'filename', 'alt'])
            ->assertJsonPath('alt', 'Een zip-screen op een zuidgevel');

        $asset = Asset::find($response->json('id'));

        $this->assertNotNull($asset);
        $this->assertSame('Een zip-screen op een zuidgevel', $asset->get('alt'));
        $this->assertStringStartsWith(config('inspace.assets.folder'), $asset->path());
    }

    public function test_a_pdf_is_rejected(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', [
                'file' => UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/inspace/v1/media', ['alt' => 'zonder bestand'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_the_upload_goes_through_the_compression_listener(): void
    {
        $id = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/media', [
            'file' => UploadedFile::fake()->image('groot.jpg', 4000, 3000),
        ])->assertStatus(201)->json('id');

        $asset = Asset::find($id);

        $this->assertLessThanOrEqual(
            (int) config('image-compression.max_width'),
            $asset->width(),
            'De upload moet door AssetUploaded gaan, anders slaat CompressUploadedAsset over.'
        );
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/MediaUploadTest.php`
Expected: FAIL met 404 — de route bestaat nog niet.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Inspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Facades\AssetContainer;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $config = config('inspace.assets');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $config['mimes']), 'max:'.$config['max_kb']],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $container = AssetContainer::findByHandle($config['container']);

        $asset = $container->makeAsset(
            trim($config['folder'], '/').'/'.$validated['file']->getClientOriginalName()
        );

        // upload() en geen eigen disk-write: alleen dit pad vuurt
        // AssetUploaded, waar CompressUploadedAsset aan hangt.
        $asset->upload($validated['file']);

        if (($validated['alt'] ?? null) !== null) {
            $asset->set('alt', $validated['alt'])->save();
        }

        return response()->json([
            'id' => $asset->id(),
            'url' => $asset->url(),
            'width' => $asset->width(),
            'height' => $asset->height(),
            'filename' => $asset->basename(),
            'alt' => $asset->get('alt'),
        ], 201);
    }
}
```

In `routes/inspace.php`, binnen de groep:

```php
Route::post('media', [MediaController::class, 'store']);
```

- [ ] **Step 4: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/MediaUploadTest.php`
Expected: PASS, 4 tests.

`Statamic\Assets\Asset::upload()` dispatcht `AssetUploaded` (`Asset.php:935`) — geverifieerd, dus dit pad is het juiste. Faalt de compressietest toch, dan zit het probleem in de listener of in `config('image-compression')`, niet in het uploadpad.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Inspace/MediaController.php routes/inspace.php tests/Feature/Inspace/MediaUploadTest.php
git commit -m "feat(inspace): POST /media met alt op het asset en compressie via AssetUploaded"
```

---

### Task 9: `POST /pages` — aanmaken

**Files:**
- Create: `app/Inspace/EntryWriter.php`
- Create: `app/Inspace/PayloadValidator.php`
- Modify: `app/Http/Controllers/Inspace/PageController.php`
- Modify: `routes/inspace.php`
- Test: `tests/Feature/Inspace/PageCreateTest.php`

**Interfaces:**
- Consumes: `EntryMapper` (Task 7), `BlockConverter` (Task 4), `HtmlSanitizer` (Task 5), `LinkResolver` + `ImageResolver` (Task 6)
- Produces:
  - `App\Inspace\PayloadValidator::validate(string $collection, array $payload, bool $creating): void` — gooit `Illuminate\Validation\ValidationException`
  - `App\Inspace\EntryWriter::create(string $collection, array $payload): array{entry: Statamic\Contracts\Entries\Entry, warnings: list<string>, existing: bool}`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inspace;

use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageCreateTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);

        $this->beforeApplicationDestroyed(function (): void {
            foreach ($this->created as $id) {
                Entry::find($id)?->delete();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Nova schrijft een artikel',
            'theme' => 'zonwering',
            'image' => $this->anyAssetId(),
            'content' => [['type' => 'text', 'html' => '<h2>Kop</h2><p>Body.</p>']],
            'status' => 'draft',
        ], $overrides);
    }

    private function anyAssetId(): string
    {
        return \Statamic\Facades\AssetContainer::findByHandle('assets')->assets()->first()->id();
    }

    private function post(array $payload): \Illuminate\Testing\TestResponse
    {
        $response = $this->withToken(self::TOKEN)->postJson('/api/inspace/v1/pages', $payload);

        if ($response->json('id') !== null) {
            $this->created[] = $response->json('id');
        }

        return $response;
    }

    public function test_a_minimal_payload_creates_a_draft_article(): void
    {
        $response = $this->post($this->payload())->assertStatus(201);

        $entry = Entry::find($response->json('id'));

        $this->assertNotNull($entry);
        $this->assertSame('Nova schrijft een artikel', $entry->value('title'));
        $this->assertSame(['zonwering'], $entry->get('themes'));
        $this->assertFalse($entry->published());
        $this->assertSame('nova-schrijft-een-artikel', $entry->slug());
        $this->assertNotNull($response->json('url'));
    }

    public function test_a_missing_theme_gives_422_with_the_valid_values(): void
    {
        $payload = $this->payload();
        unset($payload['theme']);

        $response = $this->post($payload)->assertStatus(422);

        $this->assertArrayHasKey('theme', $response->json('errors'));
    }

    public function test_an_unknown_theme_gives_422(): void
    {
        $this->post($this->payload(['theme' => 'bestaat-niet']))
            ->assertStatus(422)
            ->assertJsonPath('errors.theme.0', 'Onbekend thema. Geldige waarden: energie-en-comfort, ramen-en-deuren, terrasoverkapping, zonwering.');
    }

    public function test_a_missing_image_gives_422(): void
    {
        $payload = $this->payload();
        unset($payload['image']);

        $this->post($payload)->assertStatus(422)->assertJsonStructure(['errors' => ['image']]);
    }

    public function test_an_unknown_field_gives_422(): void
    {
        $this->post($this->payload(['categories' => ['nieuws']]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['categories']]);
    }

    public function test_an_external_image_gives_422(): void
    {
        $this->post($this->payload([
            'content' => [['type' => 'text', 'html' => '<p><img src="https://cdn.example.com/x.jpg"></p>']],
        ]))->assertStatus(422)->assertJsonStructure(['errors' => ['content']]);
    }

    public function test_a_slug_collision_gets_a_suffix_and_overwrites_nothing(): void
    {
        $first = $this->post($this->payload())->assertStatus(201)->json('id');
        $second = $this->post($this->payload(['external_id' => 'anders']))->assertStatus(201)->json('id');

        $this->assertNotSame($first, $second);
        $this->assertSame('nova-schrijft-een-artikel-2', Entry::find($second)->slug());
    }

    public function test_the_same_external_id_returns_the_existing_article(): void
    {
        $first = $this->post($this->payload(['external_id' => 'nova-4711']))->assertStatus(201)->json('id');

        $second = $this->post($this->payload(['external_id' => 'nova-4711']))->assertStatus(200);

        $this->assertSame($first, $second->json('id'));
        $this->assertCount(1, Entry::query()->where('collection', 'articles')->where('external_id', 'nova-4711')->get());
    }

    public function test_disallowed_html_is_stripped_and_reported(): void
    {
        $response = $this->post($this->payload([
            'content' => [['type' => 'text', 'html' => '<h1>Te groot</h1><p>Blijft.</p>']],
        ]))->assertStatus(201);

        $this->assertContains('Tag <h1> is niet toegestaan en is verwijderd.', $response->json('warnings'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageCreateTest.php`
Expected: FAIL met 404 — de route bestaat nog niet.

- [ ] **Step 3: Write the PayloadValidator**

```php
<?php

namespace App\Inspace;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Collection;

class PayloadValidator
{
    /** Velden die geen blueprint-handle hebben maar wel toegestaan zijn. */
    private const EXTRA = ['status', 'external_id', 'site'];

    public function __construct(private readonly EntryMapper $mapper) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function validate(string $collection, array $payload, bool $creating): void
    {
        $mapping = $this->mapper->mapping($collection);
        $known = array_merge(array_keys($mapping), self::EXTRA);

        $unknown = array_diff(array_keys($payload), $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages(
                array_fill_keys(
                    array_values($unknown),
                    'Onbekend veld. Geldige velden: '.implode(', ', $known).'.'
                )
            );
        }

        Validator::make($payload, $this->rules($collection, $creating))->validate();

        $this->validateTheme($collection, $payload);
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(string $collection, bool $creating): array
    {
        $required = $creating ? ['required'] : ['sometimes'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'theme' => [...$required, 'string'],
            'image' => [...$required, 'string'],
            'content' => [...$required, 'array'],
            'content.*.type' => ['required', 'string'],
            'intro' => ['sometimes', 'nullable', 'string'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200'],
            'date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,published'],
            'external_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'meta_image' => ['sometimes', 'nullable', 'string'],
            'seo_noindex' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Apart van de rules-array omdat de geldige waarden live uit de taxonomie
     * komen en in de foutmelding moeten staan: `create: false` betekent dat
     * Nova er geen mag bijmaken, dus zonder die lijst zit het vast.
     *
     * @param  array<string, mixed>  $payload
     */
    private function validateTheme(string $collection, array $payload): void
    {
        if (! array_key_exists('theme', $payload)) {
            return;
        }

        $handle = $this->mapper->mapping($collection)['theme'] ?? null;

        if ($handle === null) {
            return;
        }

        $field = Collection::findByHandle($collection)->entryBlueprint()->field($handle);
        $valid = (new TermValues)->of($field);

        sort($valid);

        if (! in_array($payload['theme'], $valid, true)) {
            throw ValidationException::withMessages([
                'theme' => 'Onbekend thema. Geldige waarden: '.implode(', ', $valid).'.',
            ]);
        }
    }
}
```

- [ ] **Step 4: Write the EntryWriter**

```php
<?php

namespace App\Inspace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryWriter
{
    public function __construct(private readonly EntryMapper $mapper) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{entry: EntryContract, warnings: list<string>, existing: bool}
     */
    public function create(string $collection, array $payload): array
    {
        return $this->locked($collection, function () use ($collection, $payload): array {
            $existing = $this->findByExternalId($collection, $payload['external_id'] ?? null);

            if ($existing !== null) {
                return ['entry' => $existing, 'warnings' => [], 'existing' => true];
            }

            $entry = Entry::make()
                ->collection($collection)
                ->locale(Collection::findByHandle($collection)->sites()->first())
                ->slug($this->uniqueSlug($collection, $payload['slug'] ?? Str::slug($payload['title'])))
                ->date($payload['date'] ?? now());

            $warnings = $this->apply($entry, $collection, $payload);

            $entry->published(($payload['status'] ?? 'draft') === 'published');
            $entry->save();

            return ['entry' => $entry, 'warnings' => $warnings, 'existing' => false];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{entry: EntryContract, warnings: list<string>, existing: bool}
     */
    public function update(EntryContract $entry, array $payload): array
    {
        $collection = $entry->collectionHandle();

        return $this->locked($collection, function () use ($entry, $collection, $payload): array {
            $warnings = $this->apply($entry, $collection, $payload);

            if (array_key_exists('slug', $payload) && $payload['slug'] !== null) {
                $entry->slug($this->uniqueSlug($collection, $payload['slug'], $entry->id()));
            }

            if (array_key_exists('date', $payload) && $payload['date'] !== null) {
                $entry->date($payload['date']);
            }

            if (array_key_exists('status', $payload)) {
                $entry->published($payload['status'] === 'published');
            }

            $entry->save();

            return ['entry' => $entry, 'warnings' => $warnings, 'existing' => false];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function apply(EntryContract $entry, string $collection, array $payload): array
    {
        $mapping = $this->mapper->mapping($collection);
        $contentApiName = $this->mapper->contentApiName($collection);
        $warnings = [];

        foreach ($payload as $apiName => $value) {
            if ($apiName === 'status' || $apiName === 'slug' || $apiName === 'date') {
                continue;
            }

            if ($apiName === 'external_id') {
                $entry->set('external_id', $value);

                continue;
            }

            $handle = $mapping[$apiName] ?? null;

            if ($handle === null) {
                continue;
            }

            if ($apiName === $contentApiName) {
                [$nodes, $blockWarnings] = $this->content($entry, $collection, (array) $value);
                $entry->set($handle, $nodes);
                $warnings = array_merge($warnings, $blockWarnings);

                continue;
            }

            $entry->set($handle, match ($apiName) {
                'theme', 'image', 'meta_image' => $value === null ? null : [$value],
                'seo_noindex' => (bool) $value,
                default => $value,
            });
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function content(EntryContract $entry, string $collection, array $blocks): array
    {
        $field = Collection::findByHandle($collection)
            ->entryBlueprint()
            ->field((string) config("inspace.writable.{$collection}.content_field"));

        $sanitizer = new HtmlSanitizer((new HtmlWhitelist)->of($field));
        $images = new ImageResolver;
        $links = new LinkResolver;

        $converter = new BlockConverter($field, function (string $html) use ($sanitizer, $images, $links): string {
            return $images->toAssetRefs($links->toStatamic($sanitizer->clean($html)));
        });

        $contentHandle = (string) config("inspace.writable.{$collection}.content_field");
        $nodes = $converter->toProsemirror($blocks, (array) $entry->get($contentHandle, []));

        return [$nodes, array_merge($sanitizer->warnings(), $images->warnings())];
    }

    private function findByExternalId(string $collection, ?string $externalId): ?EntryContract
    {
        if ($externalId === null) {
            return null;
        }

        return Entry::query()
            ->where('collection', $collection)
            ->where('external_id', $externalId)
            ->first();
    }

    private function uniqueSlug(string $collection, string $desired, ?string $ignoreId = null): string
    {
        $base = Str::slug($desired);
        $slug = $base;
        $suffix = 1;

        while (($found = Entry::query()->where('collection', $collection)->where('slug', $slug)->first()) !== null
            && $found->id() !== $ignoreId) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Statamic is flat-file met een Stache-index; parallelle schrijfacties
     * kunnen die in de knoop leggen. Tien seconden wachten en dan opgeven is
     * beter dan een half geschreven index.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(string $collection, callable $callback): mixed
    {
        return Cache::lock("inspace:write:{$collection}", 30)->block(10, $callback);
    }
}
```

- [ ] **Step 5: Add the controller method and route**

In `app/Http/Controllers/Inspace/PageController.php`:

```php
use App\Inspace\EntryWriter;
use App\Inspace\ExternalImageException;
use App\Inspace\PayloadValidator;
use App\Inspace\UnknownBlockException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

public function store(Request $request, PayloadValidator $validator, EntryWriter $writer, EntryMapper $mapper): JsonResponse
{
    $collection = 'articles';

    if (! $mapper->isWritable($collection)) {
        return response()->json(['message' => 'Deze collectie is niet schrijfbaar.'], 403);
    }

    $validator->validate($collection, $request->all(), creating: true);

    try {
        $result = $writer->create($collection, $request->all());
    } catch (ExternalImageException|UnknownBlockException $e) {
        throw ValidationException::withMessages(['content' => $e->getMessage()]);
    }

    $this->logWrite($request, $result['entry']->id());

    return response()->json(
        $mapper->toApi($result['entry']) + ['warnings' => $result['warnings']],
        $result['existing'] ? 200 : 201
    );
}

private function logWrite(Request $request, string $entryId): void
{
    Log::info('inspace.write', [
        'token' => $request->attributes->get('inspace_token_label'),
        'ip' => $request->ip(),
        'method' => $request->method(),
        'entry' => $entryId,
    ]);
}
```

In `routes/inspace.php`, binnen de groep:

```php
Route::post('pages', [PageController::class, 'store']);
```

- [ ] **Step 6: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageCreateTest.php`
Expected: PASS, 9 tests.

`Entry::query()->where('external_id', …)` werkt op een frontmatter-sleutel die niet in het blueprint staat — geverifieerd tegen deze codebase, zonder eigen Stache-index. De spec noemde dat nog een implementatiecheck; die is hiermee beantwoord en er hoeft niets aan `config/statamic/stache.php` te gebeuren.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Inspace/EntryWriter.php app/Inspace/PayloadValidator.php app/Http/Controllers/Inspace/PageController.php routes/inspace.php tests/Feature/Inspace/PageCreateTest.php
git commit -m "feat(inspace): POST /pages met themavalidatie, slug-collisies en external_id"
```

---

### Task 10: `PATCH /pages/{id}` en de revisions-guard

De eigenschap die de spec hard maakt: een `PATCH` die de body niet aanraakt laat de opslag byte-identiek. Task 4 levert het mechanisme; hier wordt het over HTTP bewezen.

**Files:**
- Create: `app/Providers/InspaceServiceProvider.php`
- Modify: `app/Http/Controllers/Inspace/PageController.php`
- Modify: `routes/inspace.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Feature/Inspace/PageUpdateTest.php`

**Interfaces:**
- Consumes: `EntryWriter::update()` (Task 9), `EntryMapper` (Task 7)
- Produces: `PATCH /api/inspace/v1/pages/{id}`; `App\Providers\InspaceServiceProvider` die bij het opstarten weigert als een schrijfbare collectie revisions aan heeft

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inspace;

use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class PageUpdateTest extends TestCase
{
    use CreatesTemporaryContent;

    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    /**
     * @return array{0: \Statamic\Contracts\Entries\Entry, 1: list<array<string, mixed>>}
     */
    private function article(): array
    {
        $nodes = [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Kop']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eerste.']]],
            ['type' => 'set', 'attrs' => ['id' => 'vid77', 'enabled' => false, 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tweede.']]],
        ];

        $entry = $this->temporaryEntry('articles', 'updatetest-artikel', [
            'title' => 'Updatetest',
            'themes' => ['zonwering'],
            'date' => '2026-08-04',
            'redactor' => $nodes,
        ]);

        return [$entry, $nodes];
    }

    public function test_an_unchanged_patch_leaves_the_storage_byte_identical(): void
    {
        [$entry, $nodes] = $this->article();

        $content = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->json('content');

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['content' => $content])
            ->assertOk();

        $stored = Entry::find($entry->id())->get('redactor');

        $this->assertSame(json_encode($nodes), json_encode($stored));
        $this->assertStringNotContainsString('textAlign', json_encode($stored));
    }

    public function test_a_metadata_only_patch_does_not_touch_the_body(): void
    {
        [$entry, $nodes] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['meta_title' => 'Nieuw'])
            ->assertOk();

        $fresh = Entry::find($entry->id());

        $this->assertSame('Nieuw', $fresh->value('meta_title'));
        $this->assertSame(json_encode($nodes), json_encode($fresh->get('redactor')));
    }

    public function test_the_disabled_video_set_survives_a_rewrite(): void
    {
        [$entry, $nodes] = $this->article();

        $content = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/pages/'.$entry->id())
            ->json('content');

        $content[0]['html'] = '<h2>Herschreven</h2><p>Anders.</p>';

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), ['content' => $content])
            ->assertOk();

        $stored = Entry::find($entry->id())->get('redactor');
        $set = collect($stored)->firstWhere('type', 'set');

        $this->assertSame($nodes[2], $set, 'De uitgeschakelde set moet met row-id en enabled overleven.');
    }

    public function test_an_unknown_block_id_gives_422(): void
    {
        [$entry] = $this->article();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$entry->id(), [
                'content' => [['type' => 'video', 'id' => 'verzonnen', 'opaque' => true]],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content']]);
    }

    public function test_patching_a_non_writable_entry_gives_403(): void
    {
        $product = Entry::query()->where('collection', 'products')->first();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/inspace/v1/pages/'.$product->id(), ['title' => 'Nee'])
            ->assertStatus(403)
            ->assertJsonPath('writable_collections', ['articles']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageUpdateTest.php`
Expected: FAIL met 404/405 — de route bestaat nog niet.

- [ ] **Step 3: Add the controller method and route**

In `app/Http/Controllers/Inspace/PageController.php`:

```php
public function update(
    string $id,
    Request $request,
    PayloadValidator $validator,
    EntryWriter $writer,
    EntryMapper $mapper
): JsonResponse {
    $entry = Entry::find($id);

    if ($entry === null) {
        return response()->json(['message' => 'Onbekende entry.'], 404);
    }

    if (! $mapper->isWritable($entry->collectionHandle())) {
        return response()->json([
            'message' => 'Deze collectie is niet schrijfbaar.',
            'writable_collections' => array_keys(config('inspace.writable', [])),
        ], 403);
    }

    $validator->validate($entry->collectionHandle(), $request->all(), creating: false);

    try {
        $result = $writer->update($entry, $request->all());
    } catch (ExternalImageException|UnknownBlockException $e) {
        throw ValidationException::withMessages(['content' => $e->getMessage()]);
    }

    $this->logWrite($request, $result['entry']->id());

    return response()->json($mapper->toApi($result['entry']) + ['warnings' => $result['warnings']]);
}
```

In `routes/inspace.php`, binnen de groep:

```php
Route::patch('pages/{id}', [PageController::class, 'update']);
```

- [ ] **Step 4: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/PageUpdateTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 5: Write the revisions guard**

`app/Providers/InspaceServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Statamic\Facades\Collection;

class InspaceServiceProvider extends ServiceProvider
{
    /**
     * Met revisions aan maakt save() een working copy in plaats van te
     * publiceren. Nova zou dan denken dat het publiceerde terwijl er niets
     * live staat — stil falen op precies de plek waar het het duurst is.
     */
    public function boot(): void
    {
        foreach (array_keys(config('inspace.writable', [])) as $handle) {
            if (Collection::findByHandle($handle)?->revisionsEnabled()) {
                throw new RuntimeException(
                    "De Inspace-adapter kan niet schrijven op `{$handle}`: revisions staan aan. ".
                    'Zet `revisions: false` in de collectie, of haal hem uit config/inspace.php.'
                );
            }
        }
    }
}
```

Voeg hem toe aan `bootstrap/providers.php`:

```php
App\Providers\InspaceServiceProvider::class,
```

- [ ] **Step 6: Write the guard test**

Voeg toe aan `tests/Feature/Inspace/PageUpdateTest.php`:

```php
public function test_the_guard_refuses_when_revisions_are_enabled(): void
{
    config()->set('statamic.revisions.enabled', true);

    $collection = \Statamic\Facades\Collection::findByHandle('articles');

    if (! $collection->revisionsEnabled()) {
        $this->markTestSkipped('articles.yaml zet revisions expliciet uit; de guard is dan niet te triggeren zonder het bestand te wijzigen.');
    }

    $this->expectException(\RuntimeException::class);

    (new \App\Providers\InspaceServiceProvider($this->app))->boot();
}
```

- [ ] **Step 7: Run the whole suite**

Run: `php -d memory_limit=1G vendor/bin/phpunit`
Expected: alles groen. De nieuwe provider draait nu bij élke test, dus een fout in de guard komt hier meteen boven.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Inspace/PageController.php app/Providers/InspaceServiceProvider.php bootstrap/providers.php routes/inspace.php tests/Feature/Inspace/PageUpdateTest.php
git commit -m "feat(inspace): PATCH /pages/{id} met ongewijzigde opslag en revisions-guard"
```

---

### Task 11: OpenAPI-spec en begeleidende markdown

Het pakket dat naar Inspace gaat. Gaat parallel met de bouw de deur uit, niet erna.

**Files:**
- Create: `docs/inspace/openapi.yaml`
- Create: `docs/inspace/README.md`
- Test: `tests/Feature/Inspace/OpenApiTest.php`

**Interfaces:**
- Consumes: alle endpoints uit Task 2, 3, 7, 8, 9, 10
- Produces: `docs/inspace/openapi.yaml` — de OpenAPI 3.1-spec die Inspace als contract krijgt

- [ ] **Step 1: Write the failing test**

De spec mag niet stilletjes uit de pas lopen met de routes. Deze test is geen formaliteit: hij is de reden dat de OpenAPI-spec betrouwbaar blijft nadat er endpoints bijkomen.

```php
<?php

namespace Tests\Feature\Inspace;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_every_route_appears_in_the_openapi_spec(): void
    {
        $spec = Yaml::parseFile(base_path('docs/inspace/openapi.yaml'));

        $documented = [];

        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }

        sort($documented);

        $actual = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/inspace/v1/'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn (string $m) => in_array($m, ['HEAD', 'OPTIONS'], true))
                ->map(fn (string $m) => $m.' /'.str_replace('api/inspace/v1', '', $route->uri())))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($actual, $documented, 'De OpenAPI-spec loopt uit de pas met de routes.');
    }

    public function test_the_theme_enum_matches_the_taxonomy(): void
    {
        $spec = Yaml::parseFile(base_path('docs/inspace/openapi.yaml'));

        $inSpec = $spec['components']['schemas']['ArticleWrite']['properties']['theme']['enum'];
        $live = (new \App\Inspace\TermValues)->of(
            \Statamic\Facades\Collection::findByHandle('articles')->entryBlueprint()->field('themes')
        );

        sort($inSpec);
        sort($live);

        $this->assertSame($live, $inSpec);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/OpenApiTest.php`
Expected: FAIL — `docs/inspace/openapi.yaml` bestaat niet.

- [ ] **Step 3: Write the OpenAPI spec**

```yaml
openapi: 3.1.0
info:
  title: Inspace adapter — Winsol Brebo
  version: 1.0.0
  description: |
    Schrijf-API voor SEO-content op een Statamic-site. Fase 1 dekt de
    blogcollectie `articles`. Aanbod- en productpagina's zijn wel leesbaar
    (`GET /pages`) maar nog niet schrijfbaar; die volgen in fase 2.

    Vraag `GET /schema` op vóór je begint: dat endpoint geeft per collectie
    de schrijfbare velden, welke verplicht zijn, en de toegestane
    themawaarden. Die lijst is per site verschillend en verandert zonder dat
    dit document meebeweegt.
servers:
  - url: https://staging.winsol-brebo.be/api/inspace/v1
    description: Staging — wegwerpbaar, niet indexeerbaar
security:
  - bearerAuth: []
paths:
  /schema:
    get:
      summary: Schrijfbare collecties, velden en toegestane waarden
      responses:
        '200':
          description: OK
  /pages:
    get:
      summary: Entries over alle leesbare collecties
      parameters:
        - { name: collection, in: query, schema: { type: string } }
        - { name: editable, in: query, schema: { type: boolean } }
        - { name: status, in: query, schema: { type: string, enum: [draft, published] } }
        - { name: page, in: query, schema: { type: integer, default: 1 } }
        - { name: per_page, in: query, schema: { type: integer, default: 50, maximum: 200 } }
        - name: site
          in: query
          schema: { type: string }
          description: |
            Deze site is eentalig, dus laat hem weg. De parameter staat er
            voor sites met meerdere talen; een onbekende waarde geeft 422 in
            plaats van stil de standaardtaal te gebruiken.
      responses:
        '200':
          description: OK
    post:
      summary: Nieuw artikel
      requestBody:
        required: true
        content:
          application/json:
            schema: { $ref: '#/components/schemas/ArticleWrite' }
      responses:
        '201': { description: Aangemaakt }
        '200': { description: Bestond al, gevonden op external_id }
        '422': { description: Validatiefout }
  /pages/{id}:
    get:
      summary: Detail van een entry
      parameters:
        - { name: id, in: path, required: true, schema: { type: string } }
      responses:
        '200': { description: OK }
        '404': { description: Onbekende entry }
    patch:
      summary: Artikel bijwerken, partieel
      parameters:
        - { name: id, in: path, required: true, schema: { type: string } }
      requestBody:
        required: true
        content:
          application/json:
            schema: { $ref: '#/components/schemas/ArticleWrite' }
      responses:
        '200': { description: OK }
        '403': { description: Collectie is niet schrijfbaar }
        '404': { description: Onbekende entry }
        '422': { description: Validatiefout }
  /media:
    post:
      summary: Afbeelding uploaden
      requestBody:
        required: true
        content:
          multipart/form-data:
            schema:
              type: object
              required: [file]
              properties:
                file: { type: string, format: binary }
                alt:
                  type: string
                  description: |
                    De alt-tekst hoort bij het bestand, niet bij de plaatsing.
                    Een alt-attribuut op een <img> in `content` wordt genegeerd.
      responses:
        '201': { description: Geüpload }
        '422': { description: Afgewezen bestandstype of te groot }
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
  schemas:
    Block:
      oneOf:
        - $ref: '#/components/schemas/TextBlock'
        - $ref: '#/components/schemas/OpaqueBlock'
    TextBlock:
      type: object
      required: [type, html]
      properties:
        type: { type: string, const: text }
        html:
          type: string
          description: |
            Gewone HTML. Alle opmaak — koppen, vet, lijsten, links, tabellen
            en inline afbeeldingen — zit hierin. Wat buiten de toegestane
            tags valt wordt gestript en gemeld in `warnings`. Elke <img src>
            moet naar een asset uit POST /media wijzen; een externe URL geeft
            422.
    OpaqueBlock:
      type: object
      required: [type, id, opaque]
      description: |
        Een blok dat deze site kent maar jij niet. Stuur hem ongewijzigd
        terug. Herordenen mag, weglaten betekent verwijderen, en een
        verzonnen id geeft 422.
      properties:
        type: { type: string }
        id: { type: string }
        opaque: { type: boolean, const: true }
    ArticleWrite:
      type: object
      properties:
        title: { type: string, maxLength: 255 }
        intro: { type: string }
        theme:
          type: string
          enum: [energie-en-comfort, ramen-en-deuren, terrasoverkapping, zonwering]
          description: Vraag de actuele lijst op via GET /schema.
        image: { type: string, description: Asset-id uit POST /media }
        content:
          type: array
          items: { $ref: '#/components/schemas/Block' }
        slug: { type: string, maxLength: 200 }
        date: { type: string, format: date }
        status: { type: string, enum: [draft, published], default: draft }
        external_id:
          type: string
          description: |
            Jullie eigen identifier. Stuur hem mee: een tweede POST met
            dezelfde waarde geeft 200 en het bestaande artikel in plaats van
            een duplicaat na een timeout.
        meta_title: { type: string, maxLength: 60 }
        meta_description: { type: string, maxLength: 160 }
        meta_image: { type: string }
        seo_noindex: { type: boolean }
```

- [ ] **Step 4: Write the accompanying markdown**

`docs/inspace/README.md`:

```markdown
# Inspace-adapter — Winsol Brebo

Statamic heeft geen schrijf-API uit de doos: de ingebouwde REST- en
GraphQL-endpoints zijn read-only. Deze adapter is er specifiek voor gebouwd.
`openapi.yaml` in deze map is het contract.

## Wat fase 1 dekt

Schrijfbaar is één collectie: `articles`, de blog. Aanmaken en volledig
bijwerken, inclusief media-upload.

Leesbaar is alles. `GET /pages` geeft ook aanbod- en productpagina's terug met
hun titel en URL, zodat jullie er interne links naartoe kunnen leggen. Die
pagina's zijn nog **niet** schrijfbaar.

Dat is een bewuste fasering. Aanbod- en productpagina's zijn opgebouwd uit
losse contentblokken die per site verschillen; wat Nova daarop moet kunnen
— tekst herschrijven, of ook secties toevoegen en herordenen — bepaalt hoe dat
contract eruitziet. Die vraag staat nog open.

## Begin hier

1. `GET /schema` — welke velden schrijfbaar zijn, welke verplicht, en welke
   themawaarden bestaan. Die lijst verschilt per site.
2. `POST /media` — upload je afbeelding, bewaar de `id`.
3. `POST /pages` — het artikel zelf.

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

## Drie dingen die afwijken van WordPress

**`content` is een lijst, geen veld.** Voor een gewoon artikel is die lijst één
element lang en zit al je HTML in `html` — koppen, vet, lijsten, links,
tabellen en inline afbeeldingen. De lijst bestaat omdat een artikel ook blokken
kan bevatten die geen HTML zijn, bijvoorbeeld een video. Die krijg je terug als
dichte doos met alleen een `type` en een `id`. Stuur ze ongewijzigd mee terug;
herordenen mag, weglaten betekent verwijderen.

**Alt-teksten horen bij het bestand.** Statamic bewaart de alt op het asset,
niet op de plaatsing. Zet hem dus bij `POST /media`. Een `alt`-attribuut op een
`<img>` in `content` wordt genegeerd, met een melding in `warnings`.

**Afbeeldingen moeten geüpload zijn.** Een `<img src>` die naar een ander domein
wijst geeft `422`. Upload eerst via `/media`.

## Wat je terugkrijgt

Naast het object zelf kan elke geslaagde schrijfactie een `warnings`-array
dragen: gestripte HTML-tags en genegeerde alt-attributen. Dat zijn geen fouten,
maar het is wel het enige signaal dat er iets níét is overgekomen zoals
bedoeld. Log ze.

## Testomgeving

De staging-URL in `openapi.yaml` is wegwerpbaar en niet geïndexeerd. Je mag er
echte artikels aanmaken. CMS-toegang krijg je apart, zodat je kunt zien waar je
content landt.
```

- [ ] **Step 5: Run the test**

Run: `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Inspace/OpenApiTest.php`
Expected: PASS, 2 tests.

- [ ] **Step 6: Run the whole suite one last time**

Run: `php -d memory_limit=1G vendor/bin/phpunit`
Expected: alles groen.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add docs/inspace/ tests/Feature/Inspace/OpenApiTest.php
git commit -m "docs(inspace): OpenAPI-spec en begeleidende markdown voor Inspace"
```

---

## Wat dit plan bewust niet doet

- **Geen `DELETE`.** Niet gevraagd; terugtrekken gaat via `status: draft`.
- **Geen page-builder-schrijfrechten.** Fase 2, en pas te ontwerpen als Inspace
  gezegd heeft wat Nova op zulke pagina's moet kunnen.
- **Geen extractie naar `statamic-base`.** Komt op tafel zodra een tweede site
  koppelt. De code is er wel op voorbereid: alles wat sitespecifiek is staat in
  `config/inspace.php`.
- **Geen eigen Bard-image-node met een `alt`-attribuut.** Dat zou alt per
  plaatsing mogelijk maken in plaats van per bestand, maar het overschrijft een
  Statamic-core-node en kost onderhoud bij elke upgrade. Herzien als Nova hier
  tegenaan loopt.
- **Geen invalidatie van statische caching.** Die staat vandaag uit
  (`.env.example` zet hem op `null`). Zet een klant hem aan, dan moet dit
  opnieuw bekeken worden.
