# Font preloading in de base install — Design

**Datum:** 2026-06-10
**Status:** Approved (design), klaar voor implementatieplan

## Probleem

Self-hosted fonts moeten **gepreload** worden zodat tekst niet verspringt (layout shift / FOUT) bij het laden van een pagina. De base install moet deze preload-conventie standaard bevatten, terwijl de échte fontbestanden er nog niet zijn: fonts zijn projectgebonden en worden pas per project toegevoegd.

De base mag dus geen hardgecodeerde preload-links of `@font-face`-regels bevatten die naar niet-bestaande bestanden wijzen (dat geeft 404's). De structuur moet aanwezig zijn, maar bij een lege configuratie niets renderen.

## Doelen

- Preloading van self-hosted `.woff2` fonts is standaard ingebakken in de base.
- Bij een lege configuratie rendert er **niets** (geen 404's, schone base install).
- Fonts toevoegen per project gebeurt op **één plek** (config), zonder CSS of partials aan te raken.
- Strategie: **preload + `font-display: swap`** als robuuste default; later per project upgradebaar naar fallback-metrics zonder herontwerp.

## Niet-doelen

- Google Fonts / Adobe Typekit / externe CDN-loaders (alleen self-hosted woff2).
- Automatisch genereren van fallback-metrics (`size-adjust`, `ascent-override`). Dit blijft een handmatige, gedocumenteerde upgrade per project.
- Ondersteuning voor andere formaten dan woff2 (woff/ttf/eot fallbacks zijn niet nodig voor de doelbrowsers).

## Gekozen aanpak (A): config-gedreven, één `fonts` partial

Eén bron van waarheid (`config/fonts.php`) voedt een head-partial die zowel de
preload-links als de `@font-face`-regels genereert. Sluit aan op het bestaande
patroon van de base (`View::share` in `AppServiceProvider`, losse `config/*.php`
zoals `cookie-consent` en `analytics`).

### Componenten

1. **`config/fonts.php`** — retourneert een array fontdefinities. **Leeg by default.**
   ```php
   return [
       'fonts' => [
           // [
           //     'family'  => 'Acme',
           //     'src'     => '/fonts/acme-regular.woff2',
           //     'weight'  => 400,          // of een range voor variabele fonts: '100 900'
           //     'style'   => 'normal',
           //     'display' => 'swap',
           //     'preload' => true,         // ook een <link rel=preload> genereren
           // ],
       ],
   ];
   ```

2. **`AppServiceProvider::boot()`** — deelt de config met de views, in lijn met de
   bestaande `View::share('cookie_consent', …)`:
   ```php
   View::share('font_faces', config('fonts.fonts', []));
   ```

3. **`resources/views/partials/fonts.antlers.html`** — loopt over `{{ font_faces }}` en rendert:
   - één `<link rel="preload" as="font" type="font/woff2" href="{src}" crossorigin>`
     per face met `preload => true`;
   - één inline `<style>`-blok met een `@font-face`-regel per face.

   Bij een lege array rendert de partial niets.

4. **`layout.antlers.html`** — `{{ partial:fonts }}` wordt hoog in de `<head>`
   geplaatst, vóór `{{ vite … }}`, zodat de preload zo vroeg mogelijk wordt ontdekt.

5. **`resources/css/site.css`** — de bestaande `@theme`-tokens `--font-base` en
   `--font-display` (nu `''`) wijzen per project naar de family-naam:
   ```css
   --font-base:    'Acme', sans-serif;
   --font-display: 'Acme', sans-serif;
   ```

### Render-output (voorbeeld bij ingevulde config)

```html
<link rel="preload" as="font" type="font/woff2" href="/fonts/acme-regular.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="/fonts/acme-bold.woff2" crossorigin>
<style>
@font-face {
    font-family: 'Acme';
    src: url('/fonts/acme-regular.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}
@font-face {
    font-family: 'Acme';
    src: url('/fonts/acme-bold.woff2') format('woff2');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}
</style>
```

## Data flow

```
config/fonts.php
   → AppServiceProvider::boot()  (View::share('font_faces', …))
      → partials/fonts.antlers.html  (loopt over {{ font_faces }})
         → <link rel=preload …>     (alleen faces met preload => true)
         → <style> @font-face …</style>  (alle faces)
```

## Ontwerpkeuzes & redenen

- **Inline `@font-face` in de `<head>`** i.p.v. in de CSS-pipeline: de font-face
  wordt direct ontdekt zonder te wachten op externe CSS, wat FOUT minimaliseert en
  optimaal samenwerkt met de preload. Co-locatie met de preload-link houdt één bron.
- **`preload` per face** als losse vlag: elke face krijgt altijd een `@font-face`,
  maar alleen gemarkeerde faces krijgen een preload-link. Voorkomt over-preloading
  (vuistregel: preload alleen above-the-fold gewichten, meestal body-regular +
  heading-gewicht).
- **`crossorigin` altijd aanwezig**: fonts worden in CORS-mode opgehaald; zonder
  `crossorigin` op de preload-link laadt de browser het bestand dubbel.
- **`public/fonts/`** als opslaglocatie: stabiele, voorspelbare URL zonder
  Vite-hashing, zodat het preload-`href` deterministisch is.
- **`font-display: swap`** als default (per face overschrijfbaar via config).

## Werkwijze per project (na implementatie)

1. `.woff2`-bestanden in `public/fonts/` plaatsen.
2. Faces registreren in `config/fonts.php` (family, src, weight, style, display, preload).
3. `--font-base` / `--font-display` in `resources/css/site.css` naar de family laten wijzen.

Optionele latere upgrade naar near-zero layout shift: `font-display: optional` +
handmatig ingemeten fallback-metrics (`size-adjust`, `ascent-override`,
`descent-override`) toevoegen aan de `@font-face`. Buiten scope van deze base-feature.

## Edge cases

- **Lege config** → partial rendert niets. Base install blijft schoon, geen 404's.
- **`preload` weggelaten** → behandeld als `false` (wel `@font-face`, geen preload-link).
- **`weight` als range** (`'100 900'`) → ondersteund voor variabele fonts.
- **Bestand ontbreekt op opgegeven `src`** → browser-404; valt buiten de partial,
  maar wordt in de werkwijze-documentatie benadrukt (pad moet exact matchen).

## Testaanpak

Feature-test (PHPUnit, in lijn met `tests/Feature/AssetUploadCompressionTest.php`)
die de `fonts`-partial rendert:

- **Lege config** → output bevat geen `<link rel="preload"` en geen `@font-face`.
- **Eén face met `preload => true`** → output bevat de preload-link mét `crossorigin`
  én een `@font-face` met de juiste `family`, `src`, `weight`, `style` en `font-display`.
- **Face met `preload => false`/weggelaten** → wél `@font-face`, géén preload-link.

## Bestanden (raakvlak)

- Nieuw: `config/fonts.php`
- Nieuw: `resources/views/partials/fonts.antlers.html`
- Wijzigen: `app/Providers/AppServiceProvider.php` (`View::share('font_faces', …)`)
- Wijzigen: `resources/views/layout.antlers.html` (`{{ partial:fonts }}` in `<head>`)
- Wijzigen (per project, gedocumenteerd): `resources/css/site.css` (`@theme`-tokens)
- Nieuw: `tests/Feature/FontPreloadingTest.php`
