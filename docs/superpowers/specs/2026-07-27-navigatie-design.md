# Navigatie

**Datum:** 2026-07-27
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

De header krijgt de vijf navigatie-items uit het ontwerp, een offerte-knop, een
taalpill, en een mega menu onder "Aanbod" dat de ranges per taxonomie-categorie
toont.

Bron: Figma `dgMxUtoYzYrR5FRuwPzQBn`.

| Node | Wat |
|---|---|
| `332:3244` | `Nav` — de header, 1744×105 |
| `366:5017` | `AanbodNav` — het mega menu als kaart (**dit is de gekozen variant**) |
| `366:4827` | `AanbodNav` — full-bleed variant, **niet gekozen** |
| `457:5870` | mobiele header, 402 breed — alleen logo + hamburger, geen open-state |

De node-id uit de oorspronkelijke briefing (`293-3516`) wijst naar
`/aanbod/categorie`; de navigatie zit daarin als instance `332:3271` van
`332:3244`.

## Scope

In scope: de navigatiestructuur, het navigatie-blueprint, de header-partial,
een nieuwe mega-menu-partial, de mobiele partial, de labels in het taalbestand
en de tests.

Buiten scope, met redenen onderaan in "Open punten": multisite aanzetten, de
`/offerte`-pagina bouwen, en de `children`-dropdown uit de mobiele partial
halen.

## Bestanden

```
content/trees/navigation/main.yaml                       aangepast
resources/blueprints/navigation/main.yaml                mega_menu-toggle erbij
resources/views/partials/navigation.antlers.html         herschreven
resources/views/partials/megaMenu.antlers.html           nieuw
resources/views/partials/languagePill.antlers.html       nieuw
resources/views/partials/mobileNavigation.antlers.html   offerte-knop + taalpill
lang/nl/site.php                                         labels erbij
tests/Feature/Sections/NavigationTest.php                bijgewerkt
tests/Feature/Sections/MegaMenuTest.php                  nieuw
```

Geen nieuw CSS-bestand. De opmaak is Tailwind in de partials; hergebruikt
worden `overline` (categorie-koppen), `.btn--accent` (offerte-knop) en de
`container`-utility. `lang/en/site.php` bestaat niet en wordt niet aangemaakt —
er is één site.

## De navigatiestructuur

`content/trees/navigation/main.yaml` gaat van drie naar vijf items, in de
volgorde van `332:3244`:

| # | Titel | Entry | Bestand | Wijziging |
|---|---|---|---|---|
| 1 | Aanbod | `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a02` | `pages/aanbod.md` | nieuw, `mega_menu: true` |
| 2 | Realisaties | `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a03` | `pages/realisaties.md` | hernoemd én omgehangen |
| 3 | Service | `c1a2b3d4-0000-4e5f-8a9b-0c1d2e3f4a04` | `pages/service.md` | nieuw |
| 4 | Over ons | `559b2b7e-a511-409c-9eec-51d314cec648` | `pages/over-ons.md` | ongewijzigd |
| 5 | Contact | `f0ee3161-1534-4986-9ef1-a92fccfba619` | `pages/contact.md` | ongewijzigd |

Het item heette "Projecten" en wees naar `pages/cases.md`
(`abe3f0e6-93bd-4c99-9389-393613952117`), een entry op het generieke
`page`-blueprint. `pages/realisaties.md` is de echte overzichtspagina: blueprint
`projects_overview`, template `projects-overview`, en het doel van de
`rangeFilter`. Het item wordt dus niet alleen hernoemd maar ook omgehangen.
`cases.md` blijft bestaan en wordt hier niet aangeraakt.

De boom blijft plat. Er komen geen genestelde items bij.

## Het mega menu herkennen

`resources/blueprints/navigation/main.yaml` krijgt één veld erbij:

```yaml
-
    handle: mega_menu
    field:
        type: toggle
        display: 'Mega menu'
        instructions: 'Toont het volledige aanbod per categorie onder dit item. Alleen zinvol op het item dat naar de aanbod-pagina wijst.'
```

De template kijkt naar die vlag, niet naar een entry-id en niet naar een URL.
Een hardcoded id in een template is onzichtbaar voor de redactie en breekt
stilzwijgend als de entry ooit opnieuw wordt aangemaakt; een URL-match breekt
bij een slug-wijziging. De toggle is bovendien het enige wat een test hoeft te
zetten om het gedrag te bewijzen.

De bestaande `children`-dropdown verdwijnt uit de desktop-template. Geen enkel
item in de boom heeft kinderen, het ontwerp kent maar één dropdown-patroon, en
twee dropdown-mechanismen naast elkaar in dezelfde `<li>` is een bron van
conflicten (een item met zowel kinderen als `mega_menu` zou twee panelen
openen).

## De header

`resources/views/partials/navigation.antlers.html`. Eén `<header>` met de
`container`-utility, drie flex-blokken, `justify-between`.

**Links** — het logo, ongewijzigd ten opzichte van de huidige werkkopie: één
`{{ svg src="logo" }}` op `h-8 md:h-10 2xl:h-12`. Figma toont het merk nog als
twee losse lagen (vector + "BY BREBO" als tekst); de werkkopie heeft dat al
samengevoegd tot één asset. Die keuze blijft staan.

**Midden** — `<nav class="hidden lg:block">` met de items uit `nav:main`.

Het breekpunt schuift van `md` naar `lg`. De header wisselde op 768px naar de
desktopvariant; dat kon met drie items en geen knoppen. Ruwe telling op 768px
met wat er nu bij komt: logo ±120px, vijf items met `gap-8` ±470px,
offerte-knop ±180px, taalpill ±80px — ruim 850px in een container van 688px.
De hamburger in `mobileNavigation.antlers.html` schuift mee (`md:hidden` wordt
`lg:hidden`), anders is er tussen 768px en 1024px helemaal geen navigatie.

| Eigenschap | Figma | Implementatie |
|---|---|---|
| tussenruimte | 40px | `gap-8 xl:gap-10` |
| labelgrootte | 20px | `text-base` (vloeiend 16 → 20) |
| kleur | `#121b22` | `text-black` (= het token) |

De vloeiende `text-base` landt op 20px vanaf `2xl` en zakt daaronder mee met de
rest van de site. Dat is bewust: de nav mag op 1024px niet even groot zijn als
op 2560px.

Het Aanbod-item is een `<button>` met een caret van 14px die 180° draait als het
paneel open staat, precies zoals de huidige dropdown-knop dat doet.

**Rechts** — twee elementen met `gap-2.5`:

*Gratis offerte.* `.btn--accent` bestaat al en dekt de Figma-knop exact:
`rounded-full bg-accent px-8 py-5 font-semibold` tegenover Figma's
`rounded-[56px] bg-[#f8d71c] px-[32px] py-[20px]`, 20px semibold label. Geen
nieuwe knopvariant nodig. De link is hardcoded `/offerte`; het label komt uit
`lang/nl/site.php`, zoals de andere chrome-teksten. Die pagina bestaat nog niet
maar wordt in een parallelle sessie gebouwd volgens
`2026-07-27-offerte-page-design.md`, met exact die route.

*Taalpill.* Eigen partial, want dezelfde pill staat ook onderaan het mobiele
paneel. Outline-pill: `rounded-full border border-black/25 px-[18px] py-5`,
label "NL" 20px semibold, caret 14px. **Niet interactief.** `multisite` staat op
`false` en er is één site, dus er valt niets te kiezen. Een knop met
`aria-expanded` die een leeg of eenregelig paneel opent is slechtere markup dan
een label dat er zo uitziet: de caret krijgt `aria-hidden`, en een `sr-only`
"Taal: Nederlands" geeft schermlezers de betekenis. Zodra er een tweede site is,
wordt dit hetzelfde `x-data`-patroon als het mega menu.

## Het mega menu

`resources/views/partials/megaMenu.antlers.html`, gerenderd binnen het `<li>`
van het item met `mega_menu`.

### Gedrag

Alpine, hetzelfde patroon als de dropdown die het vervangt: `x-data="{ open:
false }"` op het `<li>`, `@click.outside="open = false"`, `@keyup.escape="open =
false"`, `:aria-expanded="open.toString()"` op de knop, en op het paneel
`x-show`, `x-cloak` en `x-collapse`. De `@alpinejs/collapse`-plugin is al
geregistreerd in `resources/js/site.js`, dus "naar beneden schuiven" is letterlijk
wat er gebeurt — geen eigen JS-bestand.

Het paneel krijgt een `id` en de knop een `aria-controls` die daarnaar wijst.

### Vorm

Twee lagen, zoals `366:5017` ze heeft:

1. **De strook** — `absolute top-full left-0 w-full`, volle breedte, met een
   onderrand `border-b border-black/25`. Geen eigen achtergrond, en
   `pointer-events-none`, zodat een klik náást de kaart doorvalt naar de
   pagina en de `@click.outside` op de `<header>` het paneel sluit.
2. **De kaart** — daarbinnen, gecentreerd en wél klikbaar:
   `bg-white rounded-md p-6 shadow-lg xl:p-10`, maximaal `85rem` (1360px, de
   breedte uit het ontwerp op een venster van 1744px).

De schaduw staat niet in Figma. Ze komt erbij omdat een witte kaart op een
witte pagina anders geen rand heeft: in het ontwerp leest hij als kaart doordat
het canvas eromheen grijs is, en dat canvas bestaat op de site niet. Dit is de
enige toevoeging aan het ontwerp.

### Kolommen

`grid grid-cols-2 gap-10 xl:grid-cols-3 xl:gap-20`. Het CTA-blok zit onderaan
de laatste categoriekolom, zoals in het ontwerp: die kolom is
`justify-between`, dus de categorie staat bovenaan en het blok onderaan.

Figma geeft de derde kolom een vaste breedte van 407px. Hier is dat een gelijke
`1fr`. Op 1744px scheelt dat een vijftigtal pixels; een vaste 407px naast twee
`1fr`-kolommen klapt onder ongeveer 1600px in elkaar, en het ontwerp bestaat
alleen op 1744px.

Twee kolommen tot `xl`, daarboven drie. Het menu verschijnt vanaf `lg`
(1024px); drie kolommen van elk een `46px`-thumbnail plus tekst passen daar
niet in.

Het raster gaat uit van de drie categorieën die er vandaag zijn
(`voor-je-woning`, `rondom-je-woning`, `slim-en-comfort`). Een vierde is geen
ontworpen geval: die valt op een tweede rij, en het CTA-blok schuift mee naar
de dan laatste kolom. Dat is een zichtbare, niet-kapotte uitkomst, en het
moment om het ontwerp erop na te vragen in plaats van er nu een raster voor te
bouwen dat niemand besteld heeft.

### Data

Dezelfde query als `resources/views/range-overview.antlers.html`:

```antlers
{{ taxonomy:range_categories sort="order:asc" }}
    {{ collection:ranges range_categories:overlaps="{slug}" sort="order:asc" }}
```

`:overlaps` en niet `:contains`, om dezelfde reden als daar: `:contains` matcht
op substring en zou een slug pakken die toevallig in een andere zit. Sorteren op
`order` houdt beide plekken in de Figma-volgorde.

De query staat twee keer in de codebase. Dat is bewust: de markup verschilt
volledig (grid van `rangeCard`s tegenover een kolom van kleine rijen), en de
query in een tag of partial gieten om vier regels te delen levert een abstractie
op die aan beide kanten in de weg zit. Als er een derde consument komt, is dat
het moment om te extraheren.

### Een categorie-kolom

De kop is de bestaande `overline`-partial:
`{{ partial:overline :label="title | entities" }}`. Uppercase, semibold,
letterafstand `0.125em`, accent-streepje eronder — precies wat `366:5022` toont.

Daaronder de ranges, `flex flex-col gap-6`.

### Een range-rij

Een `<a>` naar `{{ url }}`, `flex items-start gap-3`:

| Onderdeel | Figma | Implementatie |
|---|---|---|
| thumbnail-vak | 46×46, `bg-light`, radius 3.45px | `size-[46px] shrink-0 rounded-sm bg-light` |
| afbeelding | per range uitgesneden | `{{ img :src="image" }}`, `object-contain`, `p-1.5` |
| titel | 20px semibold, `leading-1.1` | `text-[20px] font-semibold leading-[1.1]` |
| omschrijving | 14px, `leading-1.5`, `#121b22` op 75% | `text-[14px] leading-[1.5] text-black/75` |
| tussenruimte titel/tekst | 4px | `gap-1` |

De tekstgroottes zijn vaste pixelwaarden en geen `text-*`-tokens. De tokens in
`site.css` zijn vloeiende `clamp()`-waarden voor paginacontent die van 16 naar
20px meeschaalt; een menu-item van 14px dat meegroeit naar 18px is niet wat het
ontwerp toont. Chrome schaalt hier niet mee met de viewport.

De afbeeldingen in Figma zijn per stuk bijgesneden en verschoven binnen hun
vakje. Dat is niet reproduceerbaar vanuit de data — het zijn dezelfde
bron-PNG's uit `assets/ranges/`, dus `object-contain` met wat padding geeft
hetzelfde beeld zonder negen uitzonderingen in de template.

De afbeeldingen krijgen `loading="lazy"`: het paneel staat op elke pagina in de
DOM maar is dicht.

### Het CTA-blok

Onderaan de derde kolom, een `<a>` naar `/aanbod`:
`rounded-md bg-light px-8 py-6` met `gap-4`, een titel van 20px semibold
(`leading-1.5`), en daaronder een donkere pill.

Die pill is *niet* `.btn--dark`. Die is `px-8 py-5 text-base`; Figma's knop hier
is `px-[20px] py-[14px]` met een label van 16px. Kleiner dus, en dat is de enige
plek waar die maat voorkomt. De knop wordt inline met Tailwind opgemaakt in
plaats van als zesde `.btn--`-variant in `button.css` — de briefing vraagt
expliciet om Tailwind boven eigen CSS, en één afwijkende knop rechtvaardigt geen
nieuwe klasse.

Titel en knoplabel komen uit `lang/nl/site.php`. Het is chrome, geen
entry-content: er is geen entry die "Niet zeker welke oplossing past?" bezit, en
er komt geen blueprint-veld bij om één zin beheerbaar te maken.

## Mobiel

`resources/views/partials/mobileNavigation.antlers.html`.

Het mega menu rendert niet op mobiel. Het desktop-`<nav>` is `hidden md:block`
en de partial zit daarbinnen, dus dat volgt vanzelf — Aanbod is in het paneel
gewoon een link naar `/aanbod`.

Wat er bij komt, onder de lijst: de offerte-knop (`.btn--accent`, volle breedte)
en dezelfde niet-interactieve taalpill. Figma heeft geen open-state voor mobiel,
dus dit is ingevuld en niet overgenomen.

De `children`-blok in deze partial blijft staan. Hij rendert niets zolang de
boom plat is, en hem weghalen is opruimwerk zonder ontwerp om op terug te
vallen.

## Labels

`lang/nl/site.php` krijgt erbij:

| Sleutel | Waarde |
|---|---|
| `nav_quote` | `Gratis offerte` |
| `nav_language` | `Taal: Nederlands` |
| `nav_language_short` | `NL` |
| `mega_menu_cta_title` | `Niet zeker welke oplossing past?` |
| `mega_menu_cta_label` | `Volledig aanbod` |

## Tests

Draaien met `vendor/bin/phpunit -d memory_limit=1G`, nooit met `php artisan
test`.

### `tests/Feature/Sections/NavigationTest.php` — bijgewerkt

Twee bestaande tests kloppen niet meer en worden herschreven:

- `test_menu_is_driven_by_the_main_navigation_structure` verwacht "Projecten".
  Wordt "Realisaties", plus "Aanbod" en "Service". De strekking blijft: de
  titels komen uit de boom, niet uit de template.
- `test_menu_does_not_hardcode_a_fake_aanbod_dropdown` verwacht dat "Aanbod"
  er *niet* staat. De bewijslast draait om: het item staat er nu wél, en de
  test bewijst dat het paneel uit de `mega_menu`-vlag komt en niet uit
  hardcoded markup.

De drie toegankelijkheidstests (landmark-label, hamburger-labels, dialoognaam)
blijven ongewijzigd.

Nieuw in dit bestand:

- de offerte-knop wijst naar `/offerte` en draagt het label uit het taalbestand
- de taalpill staat er, met de `sr-only`-tekst en zonder `aria-expanded`

### `tests/Feature/Sections/MegaMenuTest.php` — nieuw

- de drie categorietitels staan in de volgorde van hun `order`-veld
- alle negen ranges staan er, elk met zijn `short_description`
- elke range linkt naar zijn eigen URL onder `/aanbod/`
- "Volledig aanbod" linkt naar `/aanbod`
- de knop draagt `aria-expanded` en een `aria-controls` die naar het paneel-id
  wijst
- er is precies één paneel in de header — alleen het item met `mega_menu`
  krijgt er een, de vier andere items blijven gewone links

Die laatste is de vervanger van de oude "geen nep-dropdown"-test. De boom kan
in een `SectionTestCase` niet per test omgezet worden, dus het bewijs loopt via
het aantal: vijf items, één knop met `aria-expanded`, vier `<a>`'s.

Beide bestanden erven van `SectionTestCase`. Die kent geen cascade, dus
`{{ globals:… }}` is er leeg — deze partials gebruiken die niet, alleen
`trans:`, `nav:main`, `taxonomy:` en `collection:`.

## Open punten

**Multisite.** De taalpill is vandaag decoratie. Zodra `multisite` aangaat en er
een tweede site is, wordt het een echte dropdown op `Site::all()`. Dat is een
apart traject: het raakt de contentstructuur, de routes en elke template.

**`/offerte`.** De knop wijst naar een route die op het moment van schrijven nog
niet bestaat. Die pagina wordt parallel gebouwd. Landt ze niet, dan is dit één
regel om naar `/contact` om te hangen.

**De `overline`-regel.** Figma tekent het streepje onder de categoriekop 3px dik
(`h-0` met `inset-[-1.5px_0]`); `overline.css` implementeert `h-px`. Dat verschil
bestaat al voor elke andere overline op de site en wordt hier niet opgelost —
het hoort in een aparte ronde thuis, waar het in één keer overal klopt.

**`cases.md`.** Blijft achter zonder navigatie-item. Of die entry nog een doel
heeft, is een contentvraag, geen navigatievraag.

**De `children`-dropdown op mobiel.** Blijft staan terwijl de desktop-variant
verdwijnt. Asymmetrisch, maar het alternatief is functionaliteit weghalen
waarvoor geen ontwerp bestaat.
