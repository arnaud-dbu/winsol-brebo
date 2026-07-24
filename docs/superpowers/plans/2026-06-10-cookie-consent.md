# Cookie Consent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a GDPR-conscious cookie consent banner to the base install that gates third-party scripts behind explicit consent, supports Google Consent Mode v2, and is configurable per project.

**Architecture:** Port the proven `stuw` consent components (Alpine component, CSS, banner markup, locale JSON, `View::share`) into `statamic-base`, fixing three base-unsuitable details: the hardcoded GTM ID moves to `config/analytics.php`/env, the Astro-only `is:inline` attribute is dropped, and the cookie SVG is referenced by its real base path. Analytics ships off by default. App default locale switches to `nl`.

**Tech Stack:** Statamic 6 / Laravel 12, Antlers templates, Alpine.js 3, Tailwind CSS 4, Vite 7.

---

## Notes on testing approach

This feature is template markup plus a DOM-bound Alpine component. It sits outside the existing PHPUnit suite's scope (services/listeners only), so there is no TDD loop here. Each task ends with a concrete verification — usually a build check (`npm run build`) and/or a file-content check — followed by a commit. The final task is an end-to-end manual verification in the browser.

All commands run from the project root: `/Users/arnaud/Documents/github/statamic-base`.

---

## File Structure

**Create:**
- `config/analytics.php` — GTM container ID from env.
- `lang/nl/cookie-consent.json` — consent UI text + feature config.
- `lang/nl/strings.php` — Dutch translation of existing `strings.*` keys.
- `resources/css/components/cookie-consent.css` — toggle switch + footer button styles.
- `resources/js/components/cookie-consent.js` — Alpine consent component.
- `resources/views/partials/analytics.antlers.html` — Consent Mode v2 + GTM loader.
- `resources/views/partials/cookieConsent.antlers.html` — the banner.

**Modify:**
- `.env`, `.env.example` — `APP_LOCALE=nl`, `GTM_CONTAINER_ID=`.
- `app/Providers/AppServiceProvider.php` — share `cookie_consent` view data.
- `resources/css/site.css` — import the consent CSS.
- `resources/js/site.js` — register the Alpine component.
- `resources/views/layout.antlers.html` — wire both partials.
- `resources/views/partials/footer.antlers.html` — add the "cookie settings" button.

---

### Task 1: Analytics config + env

**Files:**
- Create: `config/analytics.php`
- Modify: `.env`, `.env.example`

- [ ] **Step 1: Create the config file**

Create `config/analytics.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager container ID
    |--------------------------------------------------------------------------
    |
    | Set GTM_CONTAINER_ID in .env to enable Google Tag Manager. When empty,
    | the analytics partial renders nothing and no tags are loaded. Tags must
    | still respect the visitor's cookie consent choice.
    |
    */

    'gtm_container_id' => env('GTM_CONTAINER_ID'),

];
```

- [ ] **Step 2: Add the env var to both env files**

In `.env` and `.env.example`, add this line directly below the existing `APP_LOCALE`/`APP_FALLBACK_LOCALE` block (any stable location is fine, but keep both files identical except for real values):

```
GTM_CONTAINER_ID=
```

- [ ] **Step 3: Verify config resolves**

Run: `php artisan tinker --execute="echo var_export(config('analytics.gtm_container_id'), true);"`
Expected: prints `NULL` (env var present but empty).

- [ ] **Step 4: Commit**

```bash
git add config/analytics.php .env.example
git commit -m "feat: add analytics config with env-driven GTM id"
```

(`.env` is gitignored; only `.env.example` is committed.)

---

### Task 2: Switch default locale to Dutch

**Files:**
- Modify: `.env`, `.env.example`
- Create: `lang/nl/strings.php`

- [ ] **Step 1: Set the locale in both env files**

In `.env` and `.env.example`, change:

```
APP_LOCALE=en
```

to:

```
APP_LOCALE=nl
```

Leave `APP_FALLBACK_LOCALE=en` unchanged.

- [ ] **Step 2: Add the Dutch strings file**

The existing skip-link partial reads `{{ trans key="strings.skip_to_content" }}`. Under the new `nl` locale it would otherwise fall back to English. Create `lang/nl/strings.php`:

```php
<?php

return [
    'skip_to_content' => 'Ga naar hoofdinhoud',
];
```

- [ ] **Step 3: Verify the translation resolves under nl**

Run: `php artisan tinker --execute="app()->setLocale('nl'); echo __('strings.skip_to_content');"`
Expected: prints `Ga naar hoofdinhoud`.

- [ ] **Step 4: Commit**

```bash
git add .env.example lang/nl/strings.php
git commit -m "feat: switch default locale to nl"
```

---

### Task 3: Consent text + config JSON

**Files:**
- Create: `lang/nl/cookie-consent.json`

- [ ] **Step 1: Create the locale JSON**

Create `lang/nl/cookie-consent.json`:

```json
{
    "enabled": true,
    "consent_mode_v2": true,
    "cookie_version": "1",

    "title": "Mogen we cookies gebruiken?",
    "intro_before": "Ze helpen ons de site te verbeteren. Kies zelf wat je toelaat in de ",
    "intro_link": "voorkeuren",
    "intro_after": ".",

    "accept_label": "Accepteren",
    "deny_label": "Weigeren",
    "confirm_label": "Voorkeuren bevestigen",
    "revoke_label": "Cookie Instellingen",

    "marketing_title": "Marketing",
    "marketing_description": "Gebruikt om advertenties relevanter te maken voor jou en de prestaties ervan te meten.",

    "personalization_title": "Personalisatie",
    "personalization_description": "Onthoudt je voorkeuren zodat de site beter aansluit op jouw bezoek.",

    "analytics_title": "Analytics",
    "analytics_description": "Helpt ons begrijpen hoe bezoekers de site gebruiken, zodat we hem kunnen verbeteren."
}
```

- [ ] **Step 2: Verify it is valid JSON**

Run: `php -r "var_dump(json_decode(file_get_contents('lang/nl/cookie-consent.json'), true)['enabled']);"`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add lang/nl/cookie-consent.json
git commit -m "feat: add nl cookie consent text and config"
```

---

### Task 4: Share consent data with all views

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the View import**

At the top of `app/Providers/AppServiceProvider.php`, add this import alongside the existing `use` statements:

```php
use Illuminate\Support\Facades\View;
```

- [ ] **Step 2: Share the data in boot()**

At the end of the existing `boot()` method body (after the `Event::listen(...)` calls), add:

```php
        View::share('cookie_consent', $this->loadCookieConsent());
```

- [ ] **Step 3: Add the loader method**

Add this private method to the class (e.g. directly after `boot()`):

```php
    private function loadCookieConsent(): array
    {
        $locale = app()->getLocale();
        $path = base_path("lang/{$locale}/cookie-consent.json");

        if (! file_exists($path)) {
            $path = base_path('lang/nl/cookie-consent.json');
        }

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
```

- [ ] **Step 4: Verify the data is shared**

Run: `php artisan tinker --execute="echo view('errors.404')->render() ? 'ok' : 'fail';"`
Expected: renders without throwing (prints `ok` or the rendered HTML). This confirms `View::share` did not break view rendering.

Then confirm the value is present:

Run: `php artisan tinker --execute="echo \Illuminate\Support\Facades\View::shared('cookie_consent')['title'];"`
Expected: prints `Mogen we cookies gebruiken?`.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: share cookie consent config with views"
```

---

### Task 5: Consent CSS

**Files:**
- Create: `resources/css/components/cookie-consent.css`
- Modify: `resources/css/site.css:82`

- [ ] **Step 1: Create the component CSS**

Create `resources/css/components/cookie-consent.css`:

```css
.footer-link-button {
    appearance: none;
    background: transparent;
    border: 0;
    padding: 0;
    color: inherit;
    font: inherit;
    cursor: pointer;
    text-align: left;
}

.footer-link-button:hover {
    text-decoration: underline;
}

/* Toggle switch — appearance:none + ::before knob, lastig in pure Tailwind. */
.cookie-switch {
    --switch-w: 2.25rem;
    --switch-h: 1.25rem;
    appearance: none;
    width: var(--switch-w);
    height: var(--switch-h);
    border-radius: 999px;
    background: rgb(255 255 255 / 0.2);
    border: 0;
    padding: 0;
    position: relative;
    cursor: pointer;
    transition: background-color 0.15s ease;
    flex-shrink: 0;
}

.cookie-switch::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 2px;
    width: calc(var(--switch-h) - 4px);
    height: calc(var(--switch-h) - 4px);
    background: currentColor;
    border-radius: 50%;
    transform: translateY(-50%);
    transition: left 0.15s ease;
}

.cookie-switch[aria-checked='true'] {
    background: var(--color-accent);
}

.cookie-switch[aria-checked='true']::before {
    left: calc(var(--switch-w) - var(--switch-h) + 2px);
}
```

- [ ] **Step 2: Import it in site.css**

In `resources/css/site.css`, add a line directly after the last component import (line 82, `@import './components/mobile-navigation.css';`):

```css
@import './components/cookie-consent.css';
```

- [ ] **Step 3: Commit**

```bash
git add resources/css/components/cookie-consent.css resources/css/site.css
git commit -m "feat: add cookie consent styles"
```

---

### Task 6: Consent Alpine component

**Files:**
- Create: `resources/js/components/cookie-consent.js`
- Modify: `resources/js/site.js`

- [ ] **Step 1: Create the Alpine component**

Create `resources/js/components/cookie-consent.js`:

```javascript
/**
 * Cookie consent — Alpine component.
 *
 * Gates third-party scripts behind user consent and (optionally) updates
 * Google Consent Mode v2 signals after a choice is made.
 *
 * Script-gating contract for project developers:
 *   <script type="text/plain" data-cookie-category="marketing"> ... </script>
 * Scripts with `type="text/plain"` are inert by default; this component
 * replaces them with executable <script> elements once the matching
 * category is granted.
 */

const COOKIE_NAME = 'cookie_consent';
const COOKIE_MAX_AGE_DAYS = 180;
const OPTIONAL_CATEGORIES = ['marketing', 'personalization', 'analytics'];

const CONSENT_MODE_MAP = {
    marketing: ['ad_storage', 'ad_user_data', 'ad_personalization'],
    personalization: ['personalization_storage'],
    analytics: ['analytics_storage'],
};

function readCookie() {
    const raw = document.cookie.split('; ').find((row) => row.startsWith(`${COOKIE_NAME}=`));
    if (!raw) return null;
    try {
        return JSON.parse(decodeURIComponent(raw.slice(COOKIE_NAME.length + 1)));
    } catch {
        return null;
    }
}

function writeCookie(payload) {
    const value = encodeURIComponent(JSON.stringify(payload));
    const maxAge = COOKIE_MAX_AGE_DAYS * 24 * 60 * 60;
    document.cookie = `${COOKIE_NAME}=${value}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
}

function gtag() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(arguments);
}

export function cookieConsent(config = {}) {
    return {
        version: String(config.version ?? '1'),
        consentModeV2: Boolean(config.consentModeV2),

        visible: false,
        expanded: false,
        choices: {
            marketing: false,
            personalization: false,
            analytics: false,
        },

        init() {
            const stored = readCookie();
            if (stored && String(stored.version) === this.version) {
                OPTIONAL_CATEGORIES.forEach((c) => {
                    this.choices[c] = Boolean(stored[c]);
                });
                this.applyConsent({ updateConsentMode: this.consentModeV2 });
            } else {
                this.visible = true;
            }

            window.addEventListener('cookie-consent:open', () => this.open());
            window.openCookiePreferences = () => this.open();
        },

        open() {
            this.visible = true;
            this.expanded = true;
            this.$nextTick(() => this.focusPanel());
        },

        toggleExpanded() {
            this.expanded = !this.expanded;
            if (this.expanded) {
                this.$nextTick(() => this.focusPanel());
            }
        },

        focusPanel() {
            const panel = this.$refs.panel;
            if (!panel) return;
            const scroller = panel.querySelector('[data-scroll]');
            if (scroller) scroller.scrollTop = 0;
            panel.focus({ preventScroll: true });
        },

        toggle(category) {
            this.choices[category] = !this.choices[category];
        },

        accept() {
            OPTIONAL_CATEGORIES.forEach((c) => (this.choices[c] = true));
            this.persistAndClose();
        },

        deny() {
            OPTIONAL_CATEGORIES.forEach((c) => (this.choices[c] = false));
            this.persistAndClose();
        },

        confirm() {
            this.persistAndClose();
        },

        persistAndClose() {
            writeCookie({
                version: this.version,
                timestamp: Date.now(),
                ...this.choices,
            });
            this.applyConsent({ updateConsentMode: this.consentModeV2 });
            this.visible = false;
            this.expanded = false;
        },

        applyConsent({ updateConsentMode }) {
            OPTIONAL_CATEGORIES.forEach((category) => {
                if (!this.choices[category]) return;
                document
                    .querySelectorAll(`script[type="text/plain"][data-cookie-category="${category}"]`)
                    .forEach((node) => activateScript(node));
            });

            if (updateConsentMode) {
                const update = {};
                OPTIONAL_CATEGORIES.forEach((category) => {
                    const value = this.choices[category] ? 'granted' : 'denied';
                    CONSENT_MODE_MAP[category].forEach((signal) => {
                        update[signal] = value;
                    });
                });
                gtag('consent', 'update', update);
            }
        },
    };
}

function activateScript(node) {
    const replacement = document.createElement('script');
    for (const attr of node.attributes) {
        if (attr.name === 'type') continue;
        replacement.setAttribute(attr.name, attr.value);
    }
    if (node.src) {
        replacement.src = node.src;
    } else {
        replacement.text = node.textContent;
    }
    node.parentNode.replaceChild(replacement, node);
}
```

- [ ] **Step 2: Register the component in site.js**

The component must be registered with `Alpine.data` **before** `Alpine.start()`. Edit `resources/js/site.js` so the top of the file reads:

```javascript
import Alpine from 'alpinejs'
import { cookieConsent } from './components/cookie-consent'

window.Alpine = Alpine
Alpine.data('cookieConsent', cookieConsent)
Alpine.start()

import "./components/hamburger";
import "./components/mobile-navigation";
import "./components/collapses";
```

Leave the rest of the file (the `import.meta.hot` block) unchanged.

- [ ] **Step 3: Verify the bundle builds**

Run: `npm run build`
Expected: completes with no errors; output lists a built `site.js` asset.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/cookie-consent.js resources/js/site.js
git commit -m "feat: add cookie consent alpine component"
```

---

### Task 7: Analytics partial

**Files:**
- Create: `resources/views/partials/analytics.antlers.html`

- [ ] **Step 1: Create the partial**

Create `resources/views/partials/analytics.antlers.html`. Note the two differences from the `stuw` original: the GTM ID comes from `{{ config:analytics:gtm_container_id }}` and the whole GTM block is guarded by it; the `is:inline` attribute is gone.

```antlers
{{ if cookie_consent:consent_mode_v2 }}
    <!-- Google Consent Mode v2 defaults — set BEFORE GTM loads -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
            personalization_storage: 'denied',
            functionality_storage: 'granted',
            security_storage: 'granted'
        });
    </script>
{{ /if }}
{{ if config:analytics:gtm_container_id }}
    <!-- Google Tag Manager -->
    <script>
        (function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js',
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', '{{ config:analytics:gtm_container_id }}');
    </script>
    <!-- End Google Tag Manager -->
{{ /if }}
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/partials/analytics.antlers.html
git commit -m "feat: add analytics partial with env-driven gtm"
```

---

### Task 8: Consent banner partial

**Files:**
- Create: `resources/views/partials/cookieConsent.antlers.html`

- [ ] **Step 1: Create the partial**

Create `resources/views/partials/cookieConsent.antlers.html`. This is the `stuw` banner with one change: the cookie icon uses `{{ svg src="icons/regular/cookie" }}` (the real path in this base) instead of `{{ svg src="cookie" }}`.

```antlers
{{#
    Cookie consent banner. All texts and config come from lang/{locale}/cookie-consent.json, shared as $cookie_consent via
    app/Providers/AppServiceProvider.php. The Alpine component lives in resources/js/components/cookie-consent.js.
    Script-gating contract for non-essential scripts:
    <script type="text/plain" data-cookie-category="marketing|personalization|analytics">
        // pixel / sdk code
    </script>
#}}
{{ if cookie_consent:enabled }}
    <aside
        role="dialog"
        aria-modal="false"
        aria-labelledby="cookieConsentTitle"
        x-data='cookieConsent({ version: "{{ cookie_consent:cookie_version or "1" }}", consentModeV2: {{ if cookie_consent:consent_mode_v2 }}true{{ else }}false{{ /if }} })'
        x-show="visible"
        x-transition.opacity.duration.200ms
        x-cloak
        class="fixed bottom-6 left-1/2 z-100 max-h-[calc(100dvh-3rem)] w-[min(26rem,calc(100vw-2rem))] -translate-x-1/2 overflow-hidden rounded-2xl border border-white/25 bg-black text-sm text-white shadow-2xl">
        {{# Header #}}
        <div class="space-y-4 p-5">
            <h2 id="cookieConsentTitle" class="flex items-center gap-2 font-display text-base">
                <span aria-hidden="true" class="contents">{{ svg src="icons/regular/cookie" class="shrink-0 size-6" }}</span>
                {{ cookie_consent:title }}
            </h2>
            <p class="text-white/70">
                {{ cookie_consent:intro_before }}<button
                    type="button"
                    class="underline underline-offset-3 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent"
                    @click="toggleExpanded()"
                    :aria-expanded="expanded.toString()"
                    aria-controls="cookieConsentPanel">{{ cookie_consent:intro_link }}</button>{{ cookie_consent:intro_after }}
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-white/20 px-3 py-2.5 font-display hover:bg-white/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    @click="deny()">
                    {{ cookie_consent:deny_label }}
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-white px-3 py-2.5 font-display text-black hover:bg-white/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    @click="accept()">
                    {{ cookie_consent:accept_label }}
                </button>
            </div>
        </div>

        {{# Expanded panel #}}
        <div
            id="cookieConsentPanel"
            x-ref="panel"
            tabindex="-1"
            x-show="expanded"
            x-collapse
            class="border-t border-white/15 focus:outline-none">
            <div data-scroll class="max-h-[40dvh] overflow-y-auto px-5">
                {{# Marketing #}}
                <div class="flex items-start justify-between gap-4 border-b border-white/15 py-3.5">
                    <div>
                        <h3 class="font-semibold" id="cookieConsentMarketing">{{ cookie_consent:marketing_title }}</h3>
                        <p class="mt-1 text-xs text-white/70">{{ cookie_consent:marketing_description }}</p>
                    </div>
                    <button
                        type="button"
                        class="cookie-switch"
                        role="switch"
                        :aria-checked="choices.marketing.toString()"
                        aria-labelledby="cookieConsentMarketing"
                        @click="toggle('marketing')"></button>
                </div>

                {{# Personalization #}}
                <div class="flex items-start justify-between gap-4 border-b border-white/15 py-3.5">
                    <div>
                        <h3 class="font-semibold" id="cookieConsentPersonalization">
                            {{ cookie_consent:personalization_title }}
                        </h3>
                        <p class="mt-1 text-xs text-white/70">{{ cookie_consent:personalization_description }}</p>
                    </div>
                    <button
                        type="button"
                        class="cookie-switch"
                        role="switch"
                        :aria-checked="choices.personalization.toString()"
                        aria-labelledby="cookieConsentPersonalization"
                        @click="toggle('personalization')"></button>
                </div>

                {{# Analytics #}}
                <div class="flex items-start justify-between gap-4 py-3.5">
                    <div>
                        <h3 class="font-semibold" id="cookieConsentAnalytics">{{ cookie_consent:analytics_title }}</h3>
                        <p class="mt-1 text-xs text-white/70">{{ cookie_consent:analytics_description }}</p>
                    </div>
                    <button
                        type="button"
                        class="cookie-switch"
                        role="switch"
                        :aria-checked="choices.analytics.toString()"
                        aria-labelledby="cookieConsentAnalytics"
                        @click="toggle('analytics')"></button>
                </div>
            </div>

            <div class="border-t border-white/15 p-5">
                <button
                    type="button"
                    class="w-full rounded-lg bg-white px-3 py-2.5 font-display text-black hover:bg-white/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    @click="confirm()">
                    {{ cookie_consent:confirm_label }}
                </button>
            </div>
        </div>
    </aside>
{{ /if }}
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/partials/cookieConsent.antlers.html
git commit -m "feat: add cookie consent banner partial"
```

---

### Task 9: Wire partials into the layout

**Files:**
- Modify: `resources/views/layout.antlers.html`

- [ ] **Step 1: Add the analytics partial to <head>**

In `resources/views/layout.antlers.html`, inside `<head>`, add `{{ partial:analytics }}` immediately before the `{{ vite ... }}` line so Consent Mode defaults are set as early as possible:

```antlers
    {{ partial:analytics }}
    {{ vite src="resources/js/site.js|resources/css/site.css" }}
```

- [ ] **Step 2: Add the banner partial before </body>**

Still in `resources/views/layout.antlers.html`, add `{{ partial:cookieConsent }}` just before the closing `</body>` tag (after `{{ yield:scripts }}`):

```antlers
    {{ yield:scripts }}
    {{ partial:cookieConsent }}
</body>
```

- [ ] **Step 3: Verify the layout renders**

Run: `npm run build` then `php artisan view:clear`
Expected: both complete without error.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layout.antlers.html
git commit -m "feat: wire analytics and cookie consent into layout"
```

---

### Task 10: Footer "cookie settings" button

**Files:**
- Modify: `resources/views/partials/footer.antlers.html`

- [ ] **Step 1: Add the reopen button**

In `resources/views/partials/footer.antlers.html`, find the legal-links block:

```antlers
        <div>
            {{ collection:legal }}
                <a href="{{ entry:url }}">{{ title }}</a>
            {{ /collection:legal }}
        </div>
```

Replace it with a version that adds the cookie-settings button after the legal links:

```antlers
        <div>
            {{ collection:legal }}
                <a href="{{ entry:url }}">{{ title }}</a>
            {{ /collection:legal }}
            <button
                type="button"
                class="footer-link-button"
                onclick="window.dispatchEvent(new CustomEvent('cookie-consent:open'))">
                {{ cookie_consent:revoke_label }}
            </button>
        </div>
```

- [ ] **Step 2: Verify the build is clean**

Run: `php artisan view:clear`
Expected: completes without error.

- [ ] **Step 3: Commit**

```bash
git add resources/views/partials/footer.antlers.html
git commit -m "feat: add cookie settings reopen button to footer"
```

---

### Task 11: End-to-end manual verification

**Files:** none (verification only)

- [ ] **Step 1: Build assets**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 2: Serve the site**

Run (in a separate terminal): `php artisan serve`
Then open `http://127.0.0.1:8000` in a browser with a fresh session (or an incognito window).

- [ ] **Step 3: Verify first-visit banner**

Expected: the consent banner appears bottom-center with the Dutch title "Mogen we cookies gebruiken?", a "Weigeren" and "Accepteren" button, and a "voorkeuren" link.

- [ ] **Step 4: Verify the preferences panel**

Click "voorkeuren". Expected: the panel expands showing three toggles (Marketing, Personalisatie, Analytics), all off, plus a "Voorkeuren bevestigen" button. Toggling a switch flips its on/off state (accent-coloured when on).

- [ ] **Step 5: Verify persistence**

Click "Accepteren". Expected: the banner closes. In DevTools → Application → Cookies, a `cookie_consent` cookie exists with `version`, `timestamp`, and `marketing/personalization/analytics: true`. Reload the page — the banner does **not** reappear.

- [ ] **Step 6: Verify reopen**

Click the "Cookie Instellingen" button in the footer. Expected: the banner reopens with the panel expanded, reflecting the previously saved choices.

- [ ] **Step 7: Verify analytics is off by default**

With `GTM_CONTAINER_ID` empty, view page source / the Network tab. Expected: no request to `googletagmanager.com`. The Consent Mode `gtag('consent', 'default', …)` block is still present in the `<head>` (harmless without GTM).

- [ ] **Step 8: (Optional) Verify gated script activation**

Temporarily add to any template, inside `<body>`:

```html
<script type="text/plain" data-cookie-category="analytics">
    window.__analyticsRan = true;
</script>
```

Clear the `cookie_consent` cookie, reload, and accept analytics. Expected: in the console, `window.__analyticsRan` is `true` only after analytics is granted (not before). Remove the test script afterward.

- [ ] **Step 9: Final commit (if any tracked changes remain)**

If steps produced no file changes, nothing to commit. Otherwise:

```bash
git add -A
git commit -m "chore: cookie consent verification tweaks"
```

---

## Self-Review

**Spec coverage:**
- Banner with Accept/Deny + 3-category panel → Tasks 7, 8.
- Script-gating contract → Task 6 (component) + Task 11 step 8 (verification).
- Consent Mode v2 default-denied before GTM → Task 7.
- Analytics off by default, opt-in via env → Tasks 1, 7.
- Locale JSON, Dutch now, extensible → Tasks 3, 4.
- App default locale → `nl` (+ nl strings to avoid skip-link fallback) → Task 2.
- `config/analytics.php` + `GTM_CONTAINER_ID` → Task 1.
- Versioned 180-day cookie, footer reopen, silent re-apply → Tasks 6, 10, 11.
- Out-of-scope cookie-policy content rewrite → intentionally excluded, per spec.

**Placeholder scan:** No TBD/TODO. Every code step contains complete content.

**Type/name consistency:** `cookieConsent` factory name matches `Alpine.data('cookieConsent', …)` (Task 6) and the `x-data='cookieConsent({...})'` call (Task 8). Cookie name `cookie_consent` consistent across component (Task 6) and verification (Task 11). Config key `analytics.gtm_container_id` consistent across Task 1, Task 7. Shared view key `cookie_consent` consistent across Task 4, Tasks 7/8/10. `revoke_label` defined in JSON (Task 3) and used in footer (Task 10).
