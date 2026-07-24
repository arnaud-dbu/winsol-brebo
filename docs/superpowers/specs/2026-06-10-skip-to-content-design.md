# Skip-to-content link — design

## Goal

Give keyboard and screen-reader users a "skip to main content" link so they can
bypass the navigation and jump straight to the page's main content. This is the
WCAG-recommended bypass-blocks pattern (WCAG 2.4.1).

## Scope

- A **single** skip link targeting the page's `<main>` element.
- Visible only when focused (visually hidden otherwise), so it never affects the
  visual design for sighted mouse users.
- Label backed by a translation string so it adapts per locale later. The site
  is single-language (`en`) today.

Out of scope: multiple skip targets (skip to nav, skip to footer, etc.).

## Approach

Pure Tailwind utility classes, matching the project's existing all-utilities
idiom (no hand-written component CSS). The feature is extracted into its own
partial for clean separation.

## Components

### 1. New partial — `resources/views/partials/skipLink.antlers.html`

A single anchor containing the whole feature:

- `<a href="#main-content">` with the label `{{ trans key="strings.skip_to_content" }}`.
- Hidden by default with `sr-only`.
- Revealed on focus with `focus:not-sr-only` plus positioning utilities
  (`focus:absolute focus:top-2 focus:left-2 focus:z-50`).
- Themed with existing tokens: `focus:bg-accent focus:text-white`, padding,
  rounded corners, and a visible focus ring for clarity.

### 2. Layout change — `resources/views/layout.antlers.html`

- Add `{{ partial:skipLink }}` as the **first child of `<body>`**, before
  `{{ partial:navigation }}`. It must be the first focusable element so keyboard
  and screen-reader users reach it first.
- Add `id="main-content"` and `tabindex="-1"` to the existing `<main>` element.
  The `id` is the jump target; `tabindex="-1"` lets `<main>` receive programmatic
  focus when the link is activated, so subsequent Tab navigation continues from
  the content rather than restarting at the top.

### 3. Translation string — `lang/en/strings.php`

New file:

```php
<?php

return [
    'skip_to_content' => 'Skip to main content',
];
```

Accessed via `{{ trans key="strings.skip_to_content" }}`. Additional locales add
their own `lang/<locale>/strings.php`.

## Data flow

1. User loads any page and presses Tab.
2. The skip link becomes visible and receives focus (it is the first focusable
   element).
3. User presses Enter.
4. Browser moves focus to `<main id="main-content" tabindex="-1">`.
5. Subsequent Tab presses continue inside the main content, past the navigation.

## Error handling

None required — static template markup with no runtime logic.

## Testing

No automated test. This is static template markup with no PHP logic; the
existing test suite covers services and listeners. Verification is manual:

1. Load any page.
2. Press Tab — the skip link appears, focused, top-left.
3. Press Enter — focus moves into `<main>`; the next Tab continues from the
   content, not the navigation.
