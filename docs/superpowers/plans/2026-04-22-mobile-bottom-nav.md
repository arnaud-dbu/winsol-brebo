# Mobile Bottom Navigation Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hamburger slide-in menu with a fixed bottom navigation bar on mobile (hidden at md breakpoint), including an Alpine.js-powered language switcher dropdown.

**Architecture:** A new `mobileNav.antlers.html` partial renders a `<nav>` fixed to the bottom of the viewport, styled entirely with Tailwind utility classes. Alpine.js drives the language dropdown toggle. The existing hamburger/slide-panel partial is decoupled from the header.

**Tech Stack:** Statamic Antlers, Tailwind CSS v4, Alpine.js (new dep), Vite

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `resources/views/partials/mobileNav.antlers.html` | Fixed bottom nav markup + all Tailwind styling |
| Modify | `resources/css/site.css` | Add `--color-nav-bar` token to `@theme` |
| Modify | `resources/css/base/global.css` | Add `[x-cloak]` rule |
| Modify | `resources/js/site.js` | Boot Alpine.js |
| Modify | `resources/views/layout.antlers.html` | Include mobileNav + add `pb-14 md:pb-0` to `<main>` |
| Modify | `resources/views/partials/navigation.antlers.html` | Remove `{{ partial:mobileNavigation }}` |
| Run | `npm install alpinejs` | Add Alpine.js dependency |

---

## Task 1: Install Alpine.js

**Files:**
- Modify: `package.json` (via npm install)
- Modify: `resources/js/site.js`

- [ ] **Step 1: Install alpinejs**

```bash
cd /Users/arnaud/Documents/github/statamic-base
npm install alpinejs
```

Expected output: `added 1 package` (or similar), no errors.

- [ ] **Step 2: Boot Alpine in `resources/js/site.js`**

Full file after edit:

```js
import Alpine from 'alpinejs'
window.Alpine = Alpine
Alpine.start()

import "./components/hamburger";
import "./components/mobile-navigation";
import "./components/collapses";

if (import.meta.hot) {
    import.meta.hot.on("vite:beforeFullReload", () => {
        sessionStorage.setItem(
            "__vite_scroll",
            JSON.stringify({ x: window.scrollX, y: window.scrollY }),
        );
    });

    const saved = sessionStorage.getItem("__vite_scroll");
    if (saved) {
        const { x, y } = JSON.parse(saved);
        sessionStorage.removeItem("__vite_scroll");
        requestAnimationFrame(() => window.scrollTo(x, y));
    }
}
```

- [ ] **Step 3: Verify build**

```bash
npm run build 2>&1 | tail -20
```

Expected: clean build, no errors.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/site.js
git commit -m "feat: add Alpine.js for interactive UI components"
```

---

## Task 2: Add design token and Alpine cloak rule

**Files:**
- Modify: `resources/css/site.css`
- Modify: `resources/css/base/global.css`

- [ ] **Step 1: Add `--color-nav-bar` to the `@theme` block in `resources/css/site.css`**

Inside the existing `@theme { ... }` block, add after `--color-black: #000;`:

```css
--color-nav-bar: #3b3bf5;
```

This makes `bg-(--color-nav-bar)` available as a Tailwind utility.

- [ ] **Step 2: Add `[x-cloak]` rule to `resources/css/base/global.css`**

Append at the end of the file:

```css
[x-cloak] { display: none !important; }
```

This prevents a flash-of-content when Alpine hides elements on page load.

- [ ] **Step 3: Verify build**

```bash
npm run build 2>&1 | tail -10
```

Expected: clean build.

- [ ] **Step 4: Commit**

```bash
git add resources/css/site.css resources/css/base/global.css
git commit -m "feat: add nav-bar color token and Alpine cloak rule"
```

---

## Task 3: Create the `mobileNav.antlers.html` partial

**Files:**
- Create: `resources/views/partials/mobileNav.antlers.html`

All styling is Tailwind utility classes. Notes on specific patterns used:
- `pb-[env(safe-area-inset-bottom)]` — iOS home bar safe area
- `aria-[current=page]:opacity-65` — Tailwind v4 aria variant targets `[aria-current="page"]`
- `aria-[selected=true]:text-(--color-nav-bar)` — same for dropdown active locale
- Alpine `:class="{'rotate-180': open}"` — chevron flip animation
- `@keydown.escape.window` — closes dropdown on Escape key globally
- `{{ locales }}` — Statamic tag that yields `url`, `locale`, `is_current` for each site

- [ ] **Step 1: Create `resources/views/partials/mobileNav.antlers.html`**

```html
<nav
    class="fixed bottom-0 left-0 right-0 z-[100] md:hidden bg-(--color-nav-bar) pb-[env(safe-area-inset-bottom)]"
    aria-label="Mobile main navigation"
>
    <div class="flex items-stretch h-14">

        {{# Nav links #}}
        <ul class="flex flex-1 items-stretch list-none m-0 p-0" role="list">
            {{ nav:main }}
                <li class="contents">
                    <a
                        href="{{ entry.url }}"
                        class="flex flex-1 items-center justify-center text-white text-xs font-semibold tracking-[0.08em] uppercase no-underline transition-opacity hover:opacity-75 focus-visible:opacity-75 focus-visible:outline-2 focus-visible:outline-white focus-visible:-outline-offset-2 aria-[current=page]:opacity-60"
                        {{ if entry.url == current_url }}aria-current="page"{{ /if }}
                    >
                        {{ title }}
                    </a>
                </li>
            {{ /nav:main }}
        </ul>

        {{# Language switcher #}}
        <div
            class="relative flex items-stretch"
            x-data="{ open: false }"
            @keydown.escape.window="open = false"
        >
            <button
                type="button"
                class="flex items-center gap-1 px-4 text-white text-xs font-semibold tracking-[0.08em] uppercase bg-transparent border-0 cursor-pointer transition-opacity hover:opacity-75 focus-visible:opacity-75 focus-visible:outline-2 focus-visible:outline-white focus-visible:-outline-offset-2"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-haspopup="listbox"
                aria-label="Select language, current: {{ site:short_locale | upper }}"
            >
                {{ site:short_locale | upper }}
                <svg
                    class="w-2.5 h-2.5 transition-transform"
                    :class="{ 'rotate-180': open }"
                    aria-hidden="true"
                    focusable="false"
                    viewBox="0 0 10 10"
                    fill="currentColor"
                >
                    <path d="M5 7L1 3h8L5 7z"/>
                </svg>
            </button>

            <ul
                x-show="open"
                x-cloak
                @click.outside="open = false"
                role="listbox"
                aria-label="Language options"
                class="absolute bottom-[calc(100%+0.5rem)] right-0 min-w-24 bg-white rounded-md shadow-lg overflow-hidden list-none m-0 p-0"
            >
                {{ locales }}
                    <li role="option" aria-selected="{{ is_current | bool_string }}">
                        <a
                            href="{{ url }}"
                            hreflang="{{ locale }}"
                            lang="{{ locale }}"
                            class="block px-4 py-2.5 text-xs font-semibold tracking-[0.08em] uppercase text-gray-900 no-underline hover:bg-gray-100 focus-visible:bg-gray-100 focus-visible:outline-none aria-[selected=true]:font-bold aria-[selected=true]:text-(--color-nav-bar)"
                            {{ if is_current }}aria-current="true"{{ /if }}
                            @click="open = false"
                        >
                            {{ locale | upper }}
                        </a>
                    </li>
                {{ /locales }}
            </ul>
        </div>

    </div>
</nav>
```

- [ ] **Step 2: Rebuild to ensure Tailwind picks up the new classes**

```bash
npm run build 2>&1 | tail -10
```

Expected: clean build.

- [ ] **Step 3: Commit**

```bash
git add resources/views/partials/mobileNav.antlers.html
git commit -m "feat: add mobileNav partial with Tailwind classes and Alpine language switcher"
```

---

## Task 4: Wire partial into layout and clean up header

**Files:**
- Modify: `resources/views/layout.antlers.html`
- Modify: `resources/views/partials/navigation.antlers.html`

- [ ] **Step 1: Update `resources/views/layout.antlers.html`**

Full file after edit:

```html
<!doctype html>
<html lang="{{ site:short_locale }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{ partial:seo }}
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{ vite src="resources/js/site.js|resources/css/site.css" }}
</head>
<body>
    {{ partial:navigation }}
    <main class="pb-14 md:pb-0">
        {{ template_content }}
    </main>
    {{ partial:footer }}
    {{ partial:mobileNav }}
    {{ partial:editPage }}
    <script src="//instant.page/5.2.0" type="module"
        integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z"></script>
    {{ yield:scripts }}
</body>
</html>
```

- [ ] **Step 2: Remove hamburger from `resources/views/partials/navigation.antlers.html`**

Full file after edit (remove the `{{ partial:mobileNavigation }}` line):

```html
<header class="py-4">
    <div class="container">
        <div class="flex justify-between items-center">
            <a href="/">
                <span class="sr-only">Home Link</span>
                {{ svg src="logo" }}
            </a>
            <nav class="hidden md:block">
                <ul class="flex gap-3">
                    {{ nav:main }}
                        <li>
                            <a href="{{ entry.url }}">{{ title }}</a>
                        </li>
                    {{ /nav:main }}
                </ul>
            </nav>
        </div>
    </div>
</header>
```

- [ ] **Step 3: Rebuild and verify**

```bash
npm run build 2>&1 | tail -10
```

Expected: clean build.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layout.antlers.html resources/views/partials/navigation.antlers.html
git commit -m "feat: wire mobileNav into layout, remove hamburger from header"
```

---

## Task 5: Manual verification checklist

Run `npm run dev`, open the site at a mobile viewport (< 768px):

- [ ] Bottom bar is visible on mobile
- [ ] Bottom bar is hidden at md breakpoint (>= 768px); desktop nav shows
- [ ] Nav links navigate to the correct pages
- [ ] Active page link renders with reduced opacity (`aria-current="page"` present in DevTools)
- [ ] Language button shows current locale (e.g. `EN`)
- [ ] Clicking language button opens dropdown above the bar
- [ ] Clicking outside the dropdown closes it
- [ ] Pressing Escape closes the dropdown
- [ ] All available locales are listed in the dropdown
- [ ] Clicking a locale navigates to that locale's URL
- [ ] No page content is obscured behind the fixed bar (main has `pb-14` on mobile)
- [ ] Keyboard: Tab reaches nav links and language button; dropdown items are focusable; Escape works from anywhere on page
- [ ] Screen reader: `nav[aria-label]`, `aria-haspopup`, `aria-expanded`, `role="listbox"`, `role="option"`, `aria-current` all present in markup

---

## Spec Coverage Self-Review

| Requirement | Covered in |
|---|---|
| Remove hamburger menu | Task 4, Step 2 |
| Fixed bottom navigation | Task 3 (`fixed bottom-0 left-0 right-0`) |
| New partial named `mobileNav.antlers.html` | Task 3 |
| Hidden at md breakpoint | Task 3 (`md:hidden` on `<nav>`) |
| Language switcher dropdown | Task 3 (Alpine `x-data` + `{{ locales }}`) |
| Alpine.js for interactivity | Task 1 |
| Fully Tailwind (no extra CSS file) | Tasks 2–3 (only token + x-cloak rule added to existing files) |
| A11Y accessible | Task 3 (`aria-haspopup`, `aria-expanded`, `role="listbox"`, `aria-current`, Escape key) |
