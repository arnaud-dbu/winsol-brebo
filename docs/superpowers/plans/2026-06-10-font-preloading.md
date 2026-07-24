# Font Preloading Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bake a config-driven font preloading convention into the base install so self-hosted woff2 fonts are preloaded (no layout shift), while rendering nothing until a project fills in `config/fonts.php`.

**Architecture:** A single `config/fonts.php` array is the source of truth. `AppServiceProvider::boot()` shares it with all views via `View::share('font_faces', …)` (the view variable is namespaced as `font_faces` to avoid collision with the Statamic cascade). A new head partial `partials/fonts.antlers.html` loops over it and renders both `<link rel="preload">` tags (only for faces flagged `preload`) and an inline `<style>` block with matching `@font-face` rules. Empty config renders nothing.

**Tech Stack:** Statamic CMS, Antlers templates, Laravel `View::share`, Tailwind v4 `@theme` tokens, PHPUnit.

---

## File Structure

- **Create** `resources/views/partials/fonts.antlers.html` — renders preload links + inline `@font-face` from the shared `fonts` array. Single responsibility: turn font config into head markup.
- **Create** `config/fonts.php` — the source of truth (empty by default, with commented example).
- **Modify** `app/Providers/AppServiceProvider.php` — share the config with views (mirrors existing `cookie_consent` share).
- **Modify** `resources/views/layout.antlers.html` — include `{{ partial:fonts }}` high in `<head>`.
- **Modify** `resources/css/site.css` — add a guidance comment near the `--font-base` / `--font-display` tokens.
- **Modify** `resources/css/base/fonts.css` — add a pointer comment to the new mechanism.
- **Create** `tests/Feature/FontPreloadingTest.php` — covers partial rendering and config wiring.

---

## Task 1: The `fonts` partial

**Files:**
- Create: `resources/views/partials/fonts.antlers.html`
- Test: `tests/Feature/FontPreloadingTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FontPreloadingTest.php`:

```php
<?php

namespace Tests\Feature;

use Statamic\Facades\Antlers;
use Tests\TestCase;

class FontPreloadingTest extends TestCase
{
    private function renderPartial(array $fonts): string
    {
        return (string) Antlers::parse(
            file_get_contents(resource_path('views/partials/fonts.antlers.html')),
            ['font_faces' => $fonts]
        );
    }

    public function test_empty_config_renders_nothing(): void
    {
        $html = trim($this->renderPartial([]));

        $this->assertStringNotContainsString('rel="preload"', $html);
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertSame('', $html);
    }

    public function test_face_with_preload_renders_link_and_font_face(): void
    {
        $html = $this->renderPartial([
            [
                'family'  => 'Acme',
                'src'     => '/fonts/acme-regular.woff2',
                'weight'  => 400,
                'style'   => 'normal',
                'display' => 'swap',
                'preload' => true,
            ],
        ]);

        $this->assertStringContainsString(
            '<link rel="preload" as="font" type="font/woff2" href="/fonts/acme-regular.woff2" crossorigin>',
            $html
        );
        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString("font-family: 'Acme';", $html);
        $this->assertStringContainsString("src: url('/fonts/acme-regular.woff2') format('woff2');", $html);
        $this->assertStringContainsString('font-weight: 400;', $html);
        $this->assertStringContainsString('font-style: normal;', $html);
        $this->assertStringContainsString('font-display: swap;', $html);
    }

    public function test_face_without_preload_renders_font_face_but_no_link(): void
    {
        $html = $this->renderPartial([
            [
                'family'  => 'Acme',
                'src'     => '/fonts/acme-bold.woff2',
                'weight'  => 700,
                'style'   => 'normal',
                'display' => 'swap',
                'preload' => false,
            ],
        ]);

        $this->assertStringNotContainsString('rel="preload"', $html);
        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('font-weight: 700;', $html);
    }

    public function test_variable_font_weight_range_is_supported(): void
    {
        $html = $this->renderPartial([
            [
                'family'  => 'Acme',
                'src'     => '/fonts/acme-variable.woff2',
                'weight'  => '100 900',
                'style'   => 'normal',
                'display' => 'swap',
                'preload' => true,
            ],
        ]);

        $this->assertStringContainsString('font-weight: 100 900;', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FontPreloadingTest`
Expected: FAIL — `file_get_contents(...)` warning / empty because `resources/views/partials/fonts.antlers.html` does not exist yet.

- [ ] **Step 3: Create the partial**

Create `resources/views/partials/fonts.antlers.html`:

```antlers
{{ if font_faces }}
{{ font_faces }}
{{ if preload }}
<link rel="preload" as="font" type="font/woff2" href="{{ src }}" crossorigin>
{{ /if }}
{{ /font_faces }}
<style>
{{ font_faces }}
@font-face {
    font-family: '{{ family }}';
    src: url('{{ src }}') format('woff2');
    font-weight: {{ weight }};
    font-style: {{ style }};
    font-display: {{ display }};
}
{{ /font_faces }}
</style>
{{ /if }}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FontPreloadingTest`
Expected: PASS (4 tests).

Note: if `test_empty_config_renders_nothing` fails only on the `assertSame('', $html)` line due to stray whitespace, the `{{ if font_faces }}` guard is what keeps output empty — confirm the partial's outer `{{ if font_faces }}` wraps the entire body and that the test already `trim()`s the result. Do not add markup outside the guard.

- [ ] **Step 5: Commit**

```bash
git add resources/views/partials/fonts.antlers.html tests/Feature/FontPreloadingTest.php
git commit -m "feat: add config-driven font preloading partial

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 2: Config file + view sharing + layout include

**Files:**
- Create: `config/fonts.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layout.antlers.html`
- Test: `tests/Feature/FontPreloadingTest.php` (add cases)

- [ ] **Step 1: Write the failing tests**

Append these methods inside the `FontPreloadingTest` class in `tests/Feature/FontPreloadingTest.php`:

```php
    public function test_config_defaults_to_empty_array(): void
    {
        $this->assertSame([], config('fonts.fonts'));
    }

    public function test_fonts_are_shared_with_views(): void
    {
        $this->assertSame(config('fonts.fonts', []), view()->shared('font_faces'));
    }

    public function test_layout_includes_fonts_partial_before_vite(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $this->assertStringContainsString('{{ partial:fonts }}', $layout);
        $this->assertLessThan(
            strpos($layout, '{{ vite'),
            strpos($layout, '{{ partial:fonts }}'),
            'The fonts partial must appear before the Vite tag so preloads are discovered early.'
        );
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FontPreloadingTest`
Expected: FAIL — `config('fonts.fonts')` is null (no config file), `view()->shared('font_faces')` is null, and `{{ partial:fonts }}` is absent from the layout.

- [ ] **Step 3: Create the config file**

Create `config/fonts.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preloaded fonts
    |--------------------------------------------------------------------------
    |
    | Self-hosted woff2 fonts for this project. Each entry generates an
    | @font-face rule, and (when "preload" is true) a <link rel="preload">
    | so the font loads early and text does not shift.
    |
    | Per project:
    |   1. Drop your .woff2 files in public/fonts/
    |   2. Add an entry per face below
    |   3. Point --font-base / --font-display in resources/css/site.css
    |      at the family name
    |
    | Only preload above-the-fold faces (usually body-regular + heading weight)
    | to avoid hurting performance. "weight" may be a range for variable fonts,
    | e.g. '100 900'.
    |
    */

    'fonts' => [
        // [
        //     'family'  => 'Acme',
        //     'src'     => '/fonts/acme-regular.woff2',
        //     'weight'  => 400,
        //     'style'   => 'normal',
        //     'display' => 'swap',
        //     'preload' => true,
        // ],
    ],

];
```

- [ ] **Step 4: Share the config with views**

In `app/Providers/AppServiceProvider.php`, inside `boot()`, add the share next to the existing cookie consent share. Locate this line:

```php
        View::share('cookie_consent', $this->loadCookieConsent());
```

Add immediately after it:

```php
        View::share('font_faces', config('fonts.fonts', []));
```

- [ ] **Step 5: Include the partial in the layout**

In `resources/views/layout.antlers.html`, add the partial in the `<head>` right after the viewport meta tag and before `{{ partial:seo }}`. Locate:

```antlers
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{ partial:seo }}
```

Replace with:

```antlers
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{ partial:fonts }}
    {{ partial:seo }}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=FontPreloadingTest`
Expected: PASS (7 tests total).

- [ ] **Step 7: Commit**

```bash
git add config/fonts.php app/Providers/AppServiceProvider.php resources/views/layout.antlers.html tests/Feature/FontPreloadingTest.php
git commit -m "feat: wire font config into views and layout head

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 3: Document the per-project workflow

**Files:**
- Modify: `resources/css/site.css`
- Modify: `resources/css/base/fonts.css`

- [ ] **Step 1: Add guidance comment to the theme tokens**

In `resources/css/site.css`, locate:

```css
    --font-base: '', sans-serif;
    --font-display: '', sans-serif;
```

Replace with:

```css
    /* Set these to your project's font family (registered in config/fonts.php). */
    --font-base: '', sans-serif;
    --font-display: '', sans-serif;
```

- [ ] **Step 2: Add a pointer comment to fonts.css**

`resources/css/base/fonts.css` is currently empty. Font faces are now generated
from `config/fonts.php` via the `fonts` partial. Write this content to
`resources/css/base/fonts.css`:

```css
/*
 * @font-face declarations are generated automatically from config/fonts.php
 * via resources/views/partials/fonts.antlers.html (preload + inline @font-face).
 *
 * Use this file only for manual per-project overrides, e.g. fallback-metric
 * tuning for near-zero layout shift (size-adjust / ascent-override).
 */
```

- [ ] **Step 3: Verify the build still compiles**

Run: `npm run build`
Expected: build completes without errors.

- [ ] **Step 4: Run the full test suite**

Run: `php artisan test`
Expected: PASS for `FontPreloadingTest`. (Note: a pre-existing image-test memory failure may occur and is unrelated to this work.)

- [ ] **Step 5: Commit**

```bash
git add resources/css/site.css resources/css/base/fonts.css
git commit -m "docs: document per-project font setup in css

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Self-Review notes

- **Spec coverage:** config-driven single source (Task 2) ✓; partial renders preload + inline @font-face (Task 1) ✓; empty config renders nothing (Task 1) ✓; `preload` per face (Task 1) ✓; `crossorigin` always present (Task 1) ✓; variable-font weight range (Task 1) ✓; partial high in head before Vite (Task 2) ✓; theme-token + fonts.css documentation (Task 3) ✓; PHPUnit feature test mirroring existing conventions ✓.
- **Type consistency:** the `fonts` array key shape (`family`, `src`, `weight`, `style`, `display`, `preload`) is identical across config, partial, and tests.
- **No placeholders:** all steps contain concrete code and exact commands.
