# Page Builder Sections — Follow-ups

**Date:** 2026-07-26
**Status:** Open
**Context:** Surfaced by the final whole-branch review of `2026-07-26-pagebuilder-sections`. None of these gated that work; all are recorded so they are not forgotten.

## Needs a decision from the client

- **Contact details.** The footer's Contact column is hidden because the globals held the starter kit's demo data (another agency's phone and email), which has been nulled out. The column reappears as soon as real values are filled in.
- **Bedrijf column.** The footer design shows links (Realisaties, Service) for which no navigation structure or collection exists.
- **"Algemene voorwaarden"** appears in the footer design but has no `legal` entry.
- **Mobile bottom navigation bar.** It is not in any Figma frame. It now renders in `--color-black` instead of the starter kit's placeholder indigo, but whether it should exist at all is unanswered.
- **Ranges featured card.** The desktop design shows a fourth, dark "featured" card variant. The `ranges` blueprint has no field that can express it.
- **Image alt text.** Assets carry no alt text, so images render with an empty `alt`.
- **Desktop nav extras.** The design's "Gratis offerte" CTA button and "NL" language switcher were not built — no content or locale source exists for either.
- **The nav does not float.** In Figma the nav overlays the home hero, range, and product headers (hence the overflows and a white logo on the photo headers). In code `navigation.antlers.html` sits in the flow with dark text and `logo.svg`. The headers are built so they work in both cases; the nav change itself was not made.
- **The range overview page is missing.** `range_overview.yaml` exists as a blueprint, but there is no entry and `/aanbod` is not a route. The hero button `"Ontdek ons aanbod"` has no destination as a result and does not render.
- **Copy alternates between `je` and `uw`.** The home hero says `"je woning"`, range and product headers say `"uw terras"`. This is as shown in Figma.
- **The mobile range frame disappeared during readout** (`457:6977`). The dimensions in the spec come from an earlier readout and have not been re-verified against the file.
- **Header ↔ first section coupling** on the range page: the PNG extends below the header and relies on the section below having no opaque background.

## Code follow-ups

- **Button variants.** `.btn--accent`, `.btn--cta` and `.btn--dark` repeat the same shape declarations verbatim; extract a `.btn--pill` base. `.btn--inverse` is unused, and `link.antlers.html` emits a bare `inverse` class that has no CSS rule — wire one to the other or delete both.
- **Per-section rules in shared CSS.** `slider.css` carries `[data-section='ranges']` and `[data-section='image_gallery']` overrides, `card.css` carries a `[data-section='cards']` one. Promote these to options on the `slider` partial, or move them to `resources/css/sections/`, before a fourth section repeats the pattern.
- **Shared partial surface area.** `sectionHeader` and `overline` define twenty variant classes across three responsive axes; five are reachable and `is_inverse` is passed by no section. Trim to the breakpoints actually in use.
- **Section shell duplication.** The `section > container > section-y-gap` wrapper with its `data-section` attribute is repeated verbatim in ten partials. A slot-based `sectionShell` partial would remove roughly forty lines and make the attribute impossible to mistype.
- **Overline typography.** The `clamp()` formula plus weight and tracking is duplicated in `overline.css` and `project-card.css`; promote it to a `--text-overline` theme token.
- **Root routing.** `pages` and `legal` both route off the root with no collision guard. No clash today, but a future page sharing a legal slug would silently shadow it.
- **Hover affordances.** The three link cards differ: `range-card` lifts with a shadow, `project-card` translates an arrow, `card` does nothing. Pick one.
- **Decorative icons.** The check icon in `featureList.antlers.html` and the icon in `features.antlers.html` lack `aria-hidden`, unlike the ones in `projects`, `gridCta` and `ranges`.
- **`text-*` utilities on `h1`–`h4` and `p` do nothing.** `base/typography.css` and component CSS are unlayered; Tailwind utilities sit in `@layer utilities`, and unlayered CSS always wins, regardless of specificity. Concretely dead: `<p class="text-lg">` in `headers/default.antlers.html` and `<h2 class="... text-base">` in `cookieConsent.antlers.html`. `.overline` and the new `header.css` classes sidestep this with a direct `font-size` on a class. Decide whether the two existing cases should get their intended size — that is a visible change on articles, cases, legal and contact.
- **`range` field name collides with Antlers' built-in `range`/`loop` tag** (`Statamic\Tags\Range`). If the variable is missing from context, a parameterless `{{ range }}...{{ /range }}` falls back to that tag and renders its body once with the parent scope. `headers/project.antlers.html` guards against this with a surrounding `{{ if range }}`. The same construct appears in `sections/ranges.antlers.html`, where `range` is always present in the section context and doesn't trap — but the pitfall is there.
- **`.btn--pill` base.** `.btn--outline` is the fourth button with identical shape declarations alongside `.btn--accent`, `.btn--cta` and `.btn--dark`.
- **Tailwind's own `.overline` utility** (`text-decoration-line: overline`) clashes by name with the project component. The unlayered project rule wins, so there is no visible problem today, but the name is a pitfall.

## Files that can probably go

Verified unreferenced, left in place rather than deleted unilaterally:

- Fieldsets: `cards.yaml`, `call_to_action.yaml`, `text.yaml`, `links.yaml`, `bg_color.yaml`, `rich_text.yaml`
- `resources/css/base/site.css` (empty, not imported) and `resources/css/components/badge.css` (empty, but imported)
- `resources/svg/test-img.png` (1 MB, untracked, unreferenced, and not an SVG)
- The `text-image--background` class, which has no CSS rule behind it
- `@alpinejs/collapse`, still imported in `site.js` after `collapses.js` was removed — confirm the cookie consent component does not need it

## Environment notes

- The full PHPUnit suite cannot complete on a default PHP memory limit: `intervention/image` exhausts 128 MB in the asset-compression test. This predates the page builder work. Use `--filter` runs, or raise `memory_limit`.
