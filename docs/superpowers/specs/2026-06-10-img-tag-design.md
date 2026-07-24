# `{{ img }}` tag — responsive images via R2 (design)

**Datum:** 2026-06-10
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan
**Vervangt:** `resources/views/partials/img.antlers.html`

## Doel

Responsive afbeeldingen die snel laden in de hoogst mogelijke kwaliteit, geserveerd
vanaf Cloudflare R2 in plaats van de app-server, met server-side crops per
breakpoint die het Statamic-focuspunt respecteren.

## Probleemanalyse van de huidige partial

1. **Afgeleiden worden door de app geserveerd.** Originelen staan op R2
   (`content/assets/*.yaml`, `disk: r2`), maar Glide draait met `cache: false`:
   elke srcset-variant wordt door Laravel gegenereerd én geserveerd via de
   `/img`-route. Bezoekers downloaden beelden van de app-origin, niet van
   Cloudflare.
2. **Lege-string-bug.** Call sites geven `max_width=""` en `sizes=""` mee.
   Antlers' `??` vangt alleen `null`, geen lege string, dus condities als
   `320 <= ""` falen en `sizes` rendert leeg. De srcset is op die plekken
   (vermoedelijk) leeg of corrupt.
3. **CSS-croppen verspilt bytes.** Aspect ratio's worden afgedwongen met
   `aspect-*`-classes + `object-cover` + `object-position: focus_css`. Visueel
   correct, maar de browser downloadt de volledige ongecropte afbeelding en de
   srcset-breedtes kloppen niet met de gerenderde crop.
4. **Geen LCP-ondersteuning.** Geen `priority`/`fetchpriority`-mechanisme
   (bestaat al wel in de stuw-versie van de partial).
5. **Inconsistente class-plaatsing.** `class` landt op `<picture>` bij foto's
   maar op `<img>` bij SVG/GIF; de img heeft hardcoded `w-full h-full
   object-cover`.
6. **Antlers tegen zijn grenzen.** 7 identieke conditieblokken × 2 formaten;
   ratio-parsing en breakpoint-logica zijn in templates niet netjes te doen.

## Besluiten

| Vraag | Besluit |
|---|---|
| Delivery | Glide-cache naar R2 pushen; HTML verwijst direct naar R2/CDN-URL's |
| Croppen | Server-side via Glide `crop_focal` met ratio-params per breakpoint |
| Vorm | Custom PHP-tag `{{ img }}`, hybride: logica in PHP, markup in een view |
| AVIF | Nee — WebP + jpg-fallback zoals nu |
| LQIP-placeholder | Nee (kan later) |
| `fill`-modus | Ja — formaliseert de background-image-aanpak |
| `priority`-param | Ja — geport uit stuw |

## 1. Delivery: Glide-cache op R2

- Nieuwe `glide`-disk in `config/filesystems.php`, wijzend naar de bestaande
  R2-bucket met root `img/` (afgeleiden gescheiden van originelen) en `url`
  op `R2_URL/img`.
- `config/statamic/assets.php`: `image_manipulation.cache` wordt `'glide'`
  (disknaam). Statamic genereert varianten eenmalig bij de eerste render,
  schrijft ze naar R2 en rendert directe R2-URL's in de HTML.
- Samenspel met static caching: de eenmalig trage generatie valt samen met de
  eerste page render; daarna zijn HTML én beelden statisch aan de edge.
- Cache-invalidatie: bij het vervangen van een asset ruimt Statamic de
  Glide-cache voor dat asset op (standaardgedrag); geen extra werk.

## 2. Tag-API

Eén class `app/Tags/Img.php` (auto-discovered). De tag bereidt data voor en
rendert `resources/views/components/img.antlers.html`.

```antlers
{{ img
    :src="image"                 {{# asset, asset-veld of URL (verplicht) #}}
    :alt="image.alt"             {{# override; valt terug op alt-veld van het asset, anders "" #}}
    ratio="1/1"                  {{# basisratio, mobile-first (optioneel) #}}
    md:ratio="4/3"               {{# overrides per Tailwind-breakpoint #}}
    lg:ratio="16/9"
    sizes="(min-width: 1024px) 50vw, 100vw"   {{# default "100vw" #}}
    max_width="1680"             {{# default 1680 #}}
    quality="85"                 {{# default 85 #}}
    priority="true"              {{# default false; LCP-afbeeldingen #}}
    fill="true"                  {{# default false; vult de (relative) container #}}
    class="rounded-lg"           {{# landt op de <img> #}}
    data_speed="0.9"             {{# optioneel, parallax #}}
}}
```

- **Breakpoint-prefixen** volgen Tailwind-defaults: `sm:` 640, `md:` 768,
  `lg:` 1024, `xl:` 1280, `2xl:` 1536. Een PHP-tag leest deze rauw uit
  `$this->params`; dit is de reden dat een Antlers-partial deze syntax niet kan
  bieden (dubbele punt = scope-separator).
- **Ratio's** zijn mobile-first: `ratio` geldt vanaf 0, elke prefix overschrijft
  vanaf dat breakpoint. Alleen `ratio` mag; helemaal geen ratio = geen crop:
  Glide krijgt enkel een breedte en resizet op de intrinsieke verhouding.
- **Focal point:** elke crop gebruikt Glide `fit: crop_focal` met breedte én
  hoogte afgeleid van de ratio. Het focuspunt uit Statamic stuurt dus de
  servercrop — geen CSS-bijsnijden meer in de standaardmodus.
- **Input-normalisatie:** lege strings, ontbrekende params en src als asset of
  URL worden in PHP op één plek genormaliseerd (`$params->int()`,
  `$params->bool()`); de lege-string-bugklasse verdwijnt structureel.

## 3. Output-markup

```html
<picture>
  <!-- per afwijkende breakpoint-ratio één source, grootste breakpoint eerst -->
  <source type="image/webp" media="(min-width: 1024px)"
          width="1680" height="945" sizes="..." srcset="...16/9-crops...">
  <source type="image/webp" media="(min-width: 768px)"
          width="1680" height="1260" sizes="..." srcset="...4/3-crops...">
  <source type="image/webp"
          width="1680" height="1680" sizes="..." srcset="...1/1-crops...">
  <img src="...jpg-fallback (max_width)..." srcset="...jpg-crops basisratio..."
       sizes="..." alt="..." width="1680" height="1680"
       loading="lazy" decoding="async" class="rounded-lg">
</picture>
```

- **Srcset-ladder:** 320 / 480 / 640 / 960 / 1280 / 1680 / 2560, afgekapt op
  `max_width` én op de intrinsieke assetbreedte (nooit upscalen).
- **CLS per breakpoint:** `width`/`height` staan óók op elk `<source>`-element
  (browsersupport sinds 2021), zodat de browser op elk breakpoint de juiste
  ruimte reserveert — één paar op de `<img>` alleen zou enkel voor de basisratio
  kloppen. Waarden: `width = min(max_width, intrinsieke breedte)`,
  `height = width ÷ ratio` van dat breakpoint.
- **Classes:** gebruikers-`class` landt op de `<img>`; `<picture>` blijft kaal.
  Server-side croppen maakt `aspect-*` + `object-cover` + hardcoded
  `w-full h-full` overbodig. Consistent met de SVG/GIF-branch die alleen een
  `<img>` rendert. (Een `picture_class`-param kan later, pas bij echte behoefte.)
- **`priority="true"`** → `loading="eager" fetchpriority="high"`;
  anders `loading="lazy" decoding="async"`.
- **`fill="true"`** → tag voegt `absolute inset-0 w-full h-full object-cover`
  toe aan de `<img>` (gemerged met gebruikers-classes) plus `object-position`
  uit het focuspunt. Container moet `relative` zijn. Een `<img>` als background
  behoudt srcset, lazy loading en priority — wat CSS `background-image` niet kan.
- **SVG/GIF:** passthrough als simpele `<img>` zonder Glide, zelfde
  class/alt/priority-afhandeling.

## 4. Foutafhandeling

- Asset niet gevonden: niets renderen; lokaal (`app.debug`) een duidelijke
  exception zodat het opvalt.
- Ongeldige ratio-string (`"16:9"`, `"abc"`): lokaal exception, in productie
  ratio negeren en intrinsieke verhouding gebruiken.
- `src` is een externe URL die niet naar een asset herleidt: renderen zonder
  width/height en zonder crops (geen focuspunt bekend), zoals de stuw-variant.

## 5. Migratie

- Alle 8 call sites omzetten naar `{{ img }}`:
  `articles/show`, `partials/article`, `partials/case`,
  `partials/sections/text`, `partials/sections/imageGallery` (4×).
- `aspect-video`-classes op article/case worden `ratio="16/9"`.
- Lege `max_width=""`/`sizes=""`-params verdwijnen (defaults in de tag).
- `resources/views/partials/img.antlers.html` wordt verwijderd.

## 6. Tests

Unit tests op de tag (Pest/PHPUnit, met fake asset container):

- ratio-parsing: geldig (`"16/9"`, `"1/1"`), ongeldig (negeren + ratio-loos),
  breakpoint-prefixen → juiste `media`-queries in juiste volgorde;
- srcset-capping op `max_width` en op intrinsieke breedte (geen upscaling);
- `width`/`height` per source en op de img, per ratio correct berekend;
- `priority`/`fill`/`class`-attributen in de output;
- normalisatie van lege-string-params;
- SVG/GIF-passthrough.

Let op de bekende pre-existing testfailure (image-compression, 128M-limiet) op
`main` — geen regressie van dit werk. Frontend verifiëren met `npm run build`
en een echte render.

## Buiten scope

- AVIF-sources (bewust afgewezen, later toevoegbaar als extra `<source>`).
- LQIP/blur-placeholders.
- Cloudflare Image Transformations (Glide blijft de transformatie-engine).
- Pre-warmen van Glide-varianten bij upload (presets) — kan later als de
  eerste-render-vertraging stoort.
