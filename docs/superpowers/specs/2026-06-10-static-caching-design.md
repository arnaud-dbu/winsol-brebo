# Static caching — design

**Date:** 2026-06-10
**Status:** Approved (brainstorm)
**Scope:** statamic-base (Statamic 6 / Laravel 12)

## Doel

Static caching kant-en-klaar in de base zetten zodat het per project met één
env-var aan/uit kan. Default veilig (uit), zodat development en verse installs
ongecachet draaien. Op prod aan met de `half` strategie. De dynamische
uitzonderingen (wat niet gecachet mag worden) staan helder gedocumenteerd zodat
de klant geen stale content of kapotte formulieren ervaart bij content-edits.

## Achtergrond / context

- `config/statamic/static_caching.php` bestaat al (Statamic-default) met de
  `half` (application driver) en `full` (file driver) strategieën gedefinieerd.
- `.env.example` bevat al `STATAMIC_STATIC_CACHING_STRATEGY=null` → de schakelaar
  bestaat, caching staat uit.
- Prod draait redis als cache-store en een queue worker
  (`QUEUE_CONNECTION=redis`). De `half` strategie (application driver) gebruikt de
  Laravel cache-store, dus op prod komt de gecachte HTML in redis terecht.
- Dynamische content in de base: een contactformulier
  (`resources/views/contact.antlers.html`) met recaptcha en CSRF. De
  `CsrfTokenReplacer` staat al actief in de config.

## Strategiekeuze: `half` measure

Gekozen: **`half` measure** (application driver → redis op prod).

Bij `half` boot PHP/Laravel nog steeds per request, maar slaat het zware werk
(Stache + Antlers-rendering) over en serveert de bewaarde HTML uit redis.
Resultaat: enkele milliseconden, niet merkbaar trager dan `full` voor de
eindgebruiker op brochure-sites.

Voordelen die aansluiten bij de prioriteiten (vlot opzetten, geen klachten,
herbruikbaar in de base):

- Geen per-project nginx-config nodig — enkel een env-var.
- CSRF-tokens en `{{ nocache }}`-blokken worden per request correct ingevuld,
  dus formulieren werken zonder extra JS-rehydratie.
- Invalidatie bij content-edit is direct en simpel.

`full` measure (file driver, nginx serveert `.html` rechtstreeks zonder PHP)
is sneller maar vraagt per-project nginx rewrite-regels én JS-rehydratie voor
elk dynamisch stukje — precies de complexiteit en het klachtenrisico dat we
willen vermijden. `full` blijft bewust ongebruikt in de base, maar wordt
gedocumenteerd als bewuste per-project optie.

## Lokaal vs live

- `.env.example`: `STATAMIC_STATIC_CACHING_STRATEGY=null` (uit). Dev en verse
  installs draaien ongecachet → wijzigingen meteen zichtbaar, geen verwarring
  tijdens development.
- Prod `.env`: `STATAMIC_STATIC_CACHING_STRATEGY=half`.

## Stale listings — aanpak

Standaard ververst Statamic bij een save de bewerkte pagina + direct
gerelateerde URLs. Overzicht-/listing-pagina's (bv. blog-index, "laatste
nieuws"-blok op de homepage) worden daarbij niet automatisch geflusht → risico
op stale content na publicatie.

**Gekozen aanpak: `{{ nocache }}` rond dynamische listing-blokken.**

Enkel het dynamische blok (de lijst zelf) wordt in `{{ nocache }}` gewikkeld; de
rest van de pagina blijft gecachet. Omdat PHP bij `half` toch al opstart, kost
dat verwaarloosbaar veel. Het kan nooit stale zijn en vraagt geen onderhoud van
relaties.

**Alternatief (gedocumenteerd, niet default): invalidation rules.** In
`static_caching.php` definieer je per content-type welke URLs mee geflusht
worden (bv. een `blog`-entry wijzigt → flush `/blog` en `/`). De listing blijft
dan volledig gecachet (tikje sneller), in ruil voor onderhoud van die relaties.
Gebruik dit enkel wanneer een specifieke listing maximaal gecachet moet zijn.

## Wat hoort niet in de cache

- **Forms / CSRF:** automatisch afgehandeld door `CsrfTokenReplacer` (al actief).
  Geen actie nodig.
- **Per-bezoeker content** (ingelogde status, winkelmandje, gepersonaliseerde
  tekst, live timestamps): in `{{ nocache }}` wikkelen. Op brochure-sites
  meestal niet aanwezig.
- **Dynamische listings:** zie hierboven — `{{ nocache }}` rond het blok.

## Wat de base meedraagt

1. **`config/statamic/static_caching.php`** — een toegevoegd commentaarblok dat
   het verschil tussen `half` en `full` uitlegt en de gekozen aanpak voor deze
   base vermeldt. Plus een uitgecommentarieerd voorbeeld van een invalidation
   rule (als vertrekpunt voor wie aanpak A wil op een specifiek project). De
   strategie-definities zelf staan er al en blijven ongewijzigd.
2. **`.env.example`** — de var staat er al op `null`. Toevoegen: een korte
   comment-regel die de prod-waarde (`half`) vermeldt en naar de doc verwijst.
3. **`docs/static-caching.md`** — beknopte gids met:
   - de strategie (half op prod, uit lokaal) en het waarom;
   - hoe aanzetten op prod (env-var);
   - de `{{ nocache }}`-aanpak voor listings, met een codevoorbeeld;
   - de checklist "wat hoort niet in de cache";
   - wanneer naar `full` / invalidation rules grijpen, en wat dat per project
     vraagt.

## Per-project workflow

De relaties/listings verschillen per project, dus de base kan ze niet vooraf
kennen. De base draagt de conventie + het vertrekpunt; het project levert de
details. Aan het einde van een project wordt gevraagd om het concreet te maken:
inspecteer de echte collections en templates en plaats de `{{ nocache }}`-
wrappers (of, indien gewenst, invalidation rules) op de juiste plekken.

## Scope / YAGNI

Bewust **niet** in deze wijziging:

- Geen custom invalidator-class.
- Geen warm-queue configuratie of warming-setup.
- Geen `full`-measure nginx-config in de base.

Allemaal optioneel en per project toe te voegen wanneer een site het echt nodig
heeft. Niet nodig om caching "vlot te kunnen aanzetten".

## Verificatie

Front-end gedrag verifiëren met `npm run build` + een echte
`php artisan serve` render (de bestaande `php artisan test`-suite heeft een
pre-existing memory-failure op de image-test, niet gerelateerd). Caching lokaal
kort testen door tijdelijk `STATAMIC_STATIC_CACHING_STRATEGY=half` te zetten en
`php artisan cache:clear` / `php please static:clear` te gebruiken.
