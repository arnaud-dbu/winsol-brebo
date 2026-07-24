# Cookie consent — design

## Goal

Give the base install a GDPR-conscious cookie consent banner that gates all
non-essential third-party scripts behind explicit user consent, supports Google
Consent Mode v2, and remembers the visitor's choice. Ported from the existing
`stuw` implementation with base-specific corrections.

## Scope

- A consent banner with **Accept / Deny** and an expandable preferences panel
  carrying **three optional categories**: marketing, personalization, analytics.
- Strictly-necessary cookies (Laravel session) are never gated and never shown.
- Script-gating contract so any project can defer a pixel/SDK until consent:
  `<script type="text/plain" data-cookie-category="marketing|personalization|analytics">`.
- Analytics (GTM) is **off by default**, opt-in per project via an env var.
- All UI text + feature config lives in a locale JSON. Dutch ships now; French
  and English can be added later by dropping in a sibling JSON file.
- Switch the app default locale to `nl`.

Out of scope (separate, optional follow-up):

- Rewriting the `content/collections/legal/cookie-policy.md` content to match
  what each project actually loads and to fill its `[Bedrijfsnaam]` placeholders.
  This depends on per-project tags, so it is intentionally not part of this work.

## Approach

Port the proven `stuw` components verbatim where they are sound (Alpine
component, CSS, banner markup, accessibility), and fix the three things that
make the `stuw` version unsuitable as a reusable base:

1. The hardcoded `GTM-KXKHHGD5` container ID moves to `config/analytics.php`
   backed by `GTM_CONTAINER_ID`. An empty ID means the analytics partial renders
   nothing.
2. The Astro-only `is:inline` attribute on the GTM script is removed.
3. The cookie SVG is referenced by its real base path (`icons/regular/cookie`).

## Components

### New files

#### 1. `resources/views/partials/cookieConsent.antlers.html`

The banner. Wrapped in `{{ if cookie_consent:enabled }}`. An `<aside
role="dialog" aria-modal="false">` driven by the `cookieConsent` Alpine
component. Header has intro text, an inline "preferences" toggle button, and
Deny/Accept buttons. The expandable panel (`x-collapse`) lists the three
categories, each with a `role="switch"` toggle bound to `choices.<category>`,
plus a "confirm preferences" button. All text comes from `cookie_consent:*`
shared view data. The cookie icon uses `{{ svg src="icons/regular/cookie" }}`.

#### 2. `resources/views/partials/analytics.antlers.html`

Rendered in `<head>`. Two parts, both guarded:

- If `cookie_consent:consent_mode_v2` is true, emit the Consent Mode v2
  `gtag('consent', 'default', …)` block with every ad/analytics/personalization
  signal set to `denied` (functionality + security `granted`). Must run before
  GTM.
- If `config:analytics:gtm_container_id` is set, emit the GTM loader using that
  ID. Empty ID ⇒ nothing renders. The `is:inline` attribute is removed.

#### 3. `resources/js/components/cookie-consent.js`

The Alpine component, ported as-is. Responsibilities:

- Read/write a versioned JSON cookie `cookie_consent` (180-day Max-Age,
  `SameSite=Lax`, `Path=/`).
- On `init`: if a stored choice matches the current `version`, re-apply it
  silently; otherwise show the banner.
- `accept` / `deny` / `confirm` persist choices and close.
- `applyConsent`: activate gated `<script type="text/plain"
  data-cookie-category="…">` nodes for granted categories (clone into a live
  `<script>`), and if Consent Mode v2 is on, push a `gtag('consent', 'update',
  …)` mapping categories → signals.
- Expose `window.openCookiePreferences()` and a `cookie-consent:open` event so
  the footer link can reopen the panel.

#### 4. `resources/css/components/cookie-consent.css`

Ported as-is: the `.cookie-switch` toggle (appearance:none + `::before` knob,
`aria-checked` drives the on state via `var(--color-accent)`) and the
`.footer-link-button` reset. `--color-accent` already exists in the base theme.

#### 5. `lang/nl/cookie-consent.json`

Text + config in one file: `enabled`, `consent_mode_v2`, `cookie_version`, the
title/intro/labels, and per-category title + description. Adding a locale later
= add `lang/<locale>/cookie-consent.json`.

#### 6. `config/analytics.php`

```php
<?php

return [
    'gtm_container_id' => env('GTM_CONTAINER_ID'),
];
```

### Changed files

#### `app/Providers/AppServiceProvider.php`

In `boot()`, add `View::share('cookie_consent', $this->loadCookieConsent())`.
The loader reads `lang/{app_locale}/cookie-consent.json`, falling back to
`lang/nl/cookie-consent.json`, returning `[]` if neither exists. Added without
disturbing the existing icon-set, blueprint-tab, and asset-compression wiring.

#### `resources/views/layout.antlers.html`

- Add `{{ partial:analytics }}` inside `<head>` (before the Vite assets, so
  Consent Mode defaults are set as early as possible).
- Add `{{ partial:cookieConsent }}` just before `</body>`.

#### `resources/js/site.js`

Import the component and register it:
`import { cookieConsent } from './components/cookie-consent'` then
`Alpine.data('cookieConsent', cookieConsent)` — registered **before**
`Alpine.start()`.

#### `resources/css/site.css`

Add `@import './components/cookie-consent.css';` alongside the other component
imports.

#### `resources/views/partials/footer.antlers.html`

Add a "cookie settings" button near the legal links that dispatches
`new CustomEvent('cookie-consent:open')`, labelled `{{ cookie_consent:revoke_label }}`.

#### Locale → `nl`

- `.env` and `.env.example`: set `APP_LOCALE=nl` (fallback stays `en`).
- Add `GTM_CONTAINER_ID=` (empty) to `.env` and `.env.example`.
- Add `lang/nl/strings.php` with a Dutch `skip_to_content`, so the existing
  skip-link partial does not fall back to English under the new default locale.

## Data flow

1. `<head>`: the analytics partial sets Consent Mode defaults to **denied**,
   then loads GTM only if a container ID is configured.
2. First visit, no cookie → banner is shown.
3. Visitor chooses (Accept / Deny / Confirm preferences) → choices written to the
   versioned `cookie_consent` cookie.
4. The component activates gated scripts for granted categories and pushes a
   Consent Mode `update`.
5. Returning visit with a matching `version` → choice re-applied silently, no
   banner.
6. Footer "cookie settings" button reopens the panel. Bumping `cookie_version`
   re-prompts every visitor after a policy change.

## Error handling

- Corrupt/unparseable cookie → treated as no consent (banner shown).
- Missing locale JSON → fall back to `nl`, then to `[]` (banner disabled via the
  `{{ if cookie_consent:enabled }}` guard).
- Empty `GTM_CONTAINER_ID` → analytics partial renders nothing; consent still
  works for any manually gated scripts.

## Testing

No automated test — this is template markup plus a DOM-bound Alpine component,
outside the existing PHPUnit suite's scope (services/listeners). Verification is
manual plus a build check:

1. `npm run build` succeeds with no Vite errors.
2. First load shows the banner; Deny/Accept/Confirm each set the cookie and
   close it.
3. A reload does not re-show the banner; the footer button reopens it.
4. With `GTM_CONTAINER_ID` empty, no GTM request fires; with it set, GTM loads
   and `gtag consent update` reflects the chosen categories.
5. A `<script type="text/plain" data-cookie-category="analytics">` only executes
   after analytics is granted.
