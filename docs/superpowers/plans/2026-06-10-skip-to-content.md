# Skip-to-content Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a keyboard/screen-reader "skip to main content" link that bypasses the navigation and jumps to the page's `<main>` element.

**Architecture:** A self-contained Antlers partial holds the link, styled with pure Tailwind utilities (`sr-only` until focused). The layout includes the partial as the first body element and gives `<main>` a focus target. The label comes from a new translation string.

**Tech Stack:** Statamic (Antlers templates), Tailwind CSS v4, Laravel translation files.

---

## File Structure

- `resources/views/partials/skipLink.antlers.html` — **Create.** The entire skip-link markup and styling.
- `lang/en/strings.php` — **Create.** Translation string for the link label.
- `resources/views/layout.antlers.html` — **Modify.** Include the partial; add `id`/`tabindex` to `<main>`.

There is no automated test: this is static template markup with no PHP logic. Verification is manual keyboard testing, captured in the final task.

---

### Task 1: Translation string

**Files:**
- Create: `lang/en/strings.php`

- [ ] **Step 1: Create the lang file**

Create `lang/en/strings.php`:

```php
<?php

return [
    'skip_to_content' => 'Skip to main content',
];
```

- [ ] **Step 2: Commit**

```bash
git add lang/en/strings.php
git commit -m "feat: add skip-to-content translation string"
```

---

### Task 2: Skip link partial

**Files:**
- Create: `resources/views/partials/skipLink.antlers.html`

- [ ] **Step 1: Create the partial**

Create `resources/views/partials/skipLink.antlers.html`:

```antlers
<a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-accent focus:px-4 focus:py-2 focus:text-white focus:outline-2 focus:outline-offset-2 focus:outline-white"
>{{ trans key="strings.skip_to_content" }}</a>
```

Notes:
- `sr-only` hides the link visually and from layout flow until focused.
- `focus:not-sr-only` + the positioning utilities reveal it at the top-left on focus.
- `focus:bg-accent` uses the existing `--color-accent` theme token defined in `resources/css/site.css`.

- [ ] **Step 2: Commit**

```bash
git add resources/views/partials/skipLink.antlers.html
git commit -m "feat: add skip-to-content link partial"
```

---

### Task 3: Wire into layout

**Files:**
- Modify: `resources/views/layout.antlers.html`

- [ ] **Step 1: Add the partial as the first body element**

In `resources/views/layout.antlers.html`, change:

```antlers
<body>
    {{ partial:navigation }}
```

to:

```antlers
<body>
    {{ partial:skipLink }}
    {{ partial:navigation }}
```

- [ ] **Step 2: Add focus target to `<main>`**

In the same file, change:

```antlers
    <main class="pb-14 md:pb-0">
```

to:

```antlers
    <main id="main-content" tabindex="-1" class="pb-14 md:pb-0">
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/layout.antlers.html
git commit -m "feat: wire skip-to-content link into layout"
```

---

### Task 4: Manual verification

**Files:** none (verification only)

- [ ] **Step 1: Serve the site and load any page**

Run the local dev server (e.g. `php please` / `php artisan serve` plus `npm run dev`, per the project's usual workflow) and open any page in a browser.

- [ ] **Step 2: Verify the link appears on focus**

Press `Tab` once on a freshly loaded page.
Expected: a "Skip to main content" link becomes visible at the top-left, with a visible focus outline. It should be the FIRST focusable element (before any nav link).

- [ ] **Step 3: Verify the jump works**

With the skip link focused, press `Enter`.
Expected: focus moves into `<main>`. Pressing `Tab` again continues inside the main content, NOT back at the top of the navigation.

- [ ] **Step 4: Verify it is hidden otherwise**

Reload and use the mouse only.
Expected: the link is not visible anywhere on the page.
