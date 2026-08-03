# Animaties en hover-effecten

**Datum:** 2026-08-03
**Status:** goedgekeurd ontwerp, klaar voor implementatieplan

De site heeft vandaag nauwelijks hover-taal. Waar er iets gebeurt is het bijna
altijd `hover:opacity-70` — links in de nav, in het mega menu, in de
contactbalk en in de footer doven allemaal op dezelfde manier uit. De knoppen
hebben helemaal geen hover-state. Dit ontwerp vervangt dat door een taal die
per component betekenis heeft, met één rode draad: **geel als hover-signaal, en
een pijl die van −45° naar 0° kantelt.**

De randvoorwaarde is subtiliteit. Elke beweging hieronder blijft onder de 12%
in schaal en onder de 6% in verplaatsing; wat het modern maakt is de
consistentie en het tempo, niet de amplitude.

## Scope

In scope: de header-links, het mega menu, de vier knopvarianten, de range
cards, de nieuws cards, de locatiekaarten, de contactbalk, en een parallax op
de CTA-secties en de product-header.

Buiten scope, bewust ongemoeid:

- **De losse links ín het mega menu** (`hover:opacity-70`). Dat zijn
  lijstitems met icoon en omschrijving, geen knoppen.
- **De footerlinks** (`hover:opacity-80`). Zelfde reden.
- **`gridCta`.** Dat beeld is een `object-contain` product-png op een shape,
  geen schermvullende achtergrond. Parallax zou daar niet subtiel ogen maar
  kapot.
- **De pijlcirkel op de locatiekaart wordt niet zwart.** Die blijft wit; zwart
  is in de contactbalk juist hét hover-signaal en die twee moeten niet
  hetzelfde zeggen.

## Uitgangspunten

Vier dingen die het vertrekpunt vormen en die bij het narekenen makkelijk
verwarren:

1. **`app/Tags/Img.php` geeft al een `data_speed`-attribuut door** naar
   `components/img.antlers.html` en `partials/video.antlers.html`, maar niets
   in het project leest het uit. Het is een dode haak uit de boilerplate. Dit
   ontwerp gebruikt hem niet — de parallax draait op CSS — en laat hem staan.
2. **Er zit geen animatiebibliotheek in het project.** Alpine is er (met de
   `collapse`-plugin), Swiper is er, verder niets. Er komt er ook geen bij.
3. **`.section-nav__link` in `section-nav.css` is dode CSS.** De ankerbalk
   gebruikt in de markup `btn btn--outline`. De nieuwe outline-hover komt dus
   wél op die pills terecht, wat strookt met wat die dode regels bedoelden.
4. **`global.css` geeft elke `<picture>` een `overflow: hidden`, maar geen
   radius.** Dat is de reden dat een schalende `<img>` met eigen ronde hoeken
   vierkante hoeken laat zien; zie "Nieuws cards" hieronder.
5. **`partials/productCard.antlers.html` spreekt deze taal al half.** Die kaart
   heeft vandaag `group-hover:scale-105 duration-500` op zijn beeld en een
   `bg-accent`-cirkel om een `-rotate-45`-pijl. Wat hier voor de nieuwskaart
   staat is dus geen nieuwe uitvinding maar het afmaken van iets dat er al
   staat. Zie ook de follow-up onderaan.

## Fundament

### Tokens

Twee nieuwe kleuren in het `@theme`-blok van `site.css`:

```css
--color-accent-hover: #e9c714; /* accent, ~6% donkerder */
--color-black-hover: #23303a; /* zwart, iets lichter */
```

Geen nieuwe duur- of easing-tokens. De tijden komen uit de bestaande
Tailwind-schaal en volgen één regel: **kleur 200ms, vorm 300ms, beeld 400–500ms.**
Hoe groter het vlak dat beweegt, hoe trager het gaat.

### Beweging beperken

`base/global.css` dooft vandaag alleen `scroll-behavior`. Dat wordt een
blanket-regel plus één uitzondering:

```css
@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }

    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }

    /*
     * Een scroll-gedreven animatie leest zijn voortgang uit de tijdlijn en niet
     * uit `animation-duration` — de regel hierboven raakt hem dus niet. De
     * tijdlijn losknippen is wat hem wél stilzet.
     */
    .parallax-media {
        animation-timeline: none;
        scale: 1;
    }
}
```

### Nieuwe bestanden

- `resources/css/base/motion.css` — de parallax-utility en haar keyframes.
- `resources/css/components/cta.css` — het knop-hover-effect op het CTA-beeld.
- `resources/css/components/contact-bar.css` — de `contact-circle`-utility.

Alle drie krijgen een import in `site.css`: `motion.css` bij de base-blok,
de andere twee bij de components.

## Header-links

`partials/navigation.antlers.html`, alleen de desktopnav (`hidden lg:block`);
de mobiele navigatie blijft ongemoeid.

De links krijgen een pill in hun eigen contour, die op hover invalt. Geen
meeschuivende indicator en geen JavaScript — dezelfde aanpak als het
luloop-project.

De klassenreeks staat straks tweemaal in de markup (de `<button>` van Aanbod en
de gewone `<a>`), dus hij wordt een utility in `components/navigation.css`:

```css
@utility nav-link {
    @apply flex items-center gap-2 rounded-full px-4 py-1.5 text-base transition-colors duration-200 ease-out;
}

@utility nav-link--dark {
    @apply hover:bg-black/5 focus-visible:bg-black/5;
}

@utility nav-link--light {
    @apply hover:bg-white/12 focus-visible:bg-white/12;
}
```

De keuze tussen de twee varianten volgt `inverse` en komt als variabele
bovenaan het bestand te staan, naast de bestaande `nav_text_class` — dus geen
tak in de markup.

Drie gevolgen die expliciet horen:

- **De `ul`-gap gaat van `gap-8 xl:gap-10` naar `gap-2 xl:gap-3`.** Met `px-4`
  erbij staat er tussen twee labels 40px waar nu 32 staat (op `xl` 44 tegen
  40). De nav wordt een fractie ruimer, niet fors breder. Bevalt dat in het
  echt niet, dan is de gap de knop om aan te draaien.
- **De actieve pagina houdt `aria-[current=page]:opacity-60`.** Die krijgt géén
  permanente pill, anders leest de huidige pagina als "gehoverd" en verliest de
  hover zijn betekenis.
- **De Aanbod-knop houdt zijn pill vast zolang het paneel open staat**, via de
  bestaande `open`-state. Anders dooft de knop uit terwijl zijn menu nog
  openhangt.

De bestaande `transition-opacity hover:opacity-70 focus-visible:opacity-70`
verdwijnt van beide takken.

## Mega menu

`partials/megaMenu.antlers.html`.

`x-collapse` gaat van het paneel af. De plugin zelf blijft geregistreerd:
`partials/cookieConsent.antlers.html` gebruikt hem ook.

In de plaats komt een `x-transition` op de buitenste wrapper, met `origin-top`.
De kaart groeit dus van zijn eigen bovenrand naar beneden open — geen
JS-meting van de positie van de Aanbod-link.

```
enter        transition ease-out duration-300
enter-start  opacity-0 -translate-y-2 scale-98
enter-end    opacity-100 translate-y-0 scale-100
leave        transition ease-in duration-150
leave-start  opacity-100 translate-y-0 scale-100
leave-end    opacity-0 -translate-y-2 scale-98
```

Sneller weg dan naartoe: dat is wat responsief laat aanvoelen zonder de
beweging groter te maken.

De transition hangt aan de buitenste wrapper en niet aan de witte kaart erin.
Die wrapper draagt ook de `py-4`-strook die de cursor opvangt op weg naar het
paneel; die schaalt dus mee, over 16px hoogte en 2%, wat neerkomt op een
verschuiving van een fractie van een pixel. De alternatieve opzet — transition
op de kaart, `x-show` op de wrapper — zou Alpine dwingen twee elementen te
coördineren zonder dat het iets zichtbaars oplevert.

De chevron blijft ongewijzigd op `rotate-180`.

## Buttons

`components/button.css`.

```css
@utility btn {
    @apply ... transition-colors duration-200 ease-out;
}

@utility btn--primary {
    @apply bg-accent text-black hover:bg-accent-hover;
}

@utility btn--secondary {
    @apply bg-black text-white hover:bg-black-hover;
}

@utility btn--tertiary {
    @apply bg-light text-black hover:bg-light-shape;
}

@utility btn--outline {
    @apply border border-black/20 text-black hover:border-black hover:bg-black hover:text-white;
}
```

`btn--tertiary` stond niet in de briefing maar krijgt wel een hover: een knop
die niet reageert leest als uitgeschakeld.

De outline-knop vult op naar zwart. Dat kan overal, want hij staat nergens op
een donker vlak: `headers/hero.antlers.html` zet hem op de witte herokaart,
`quicklinkCard.antlers.html` op een `bg-light`-kaart, en `sectionNav` op wit
boven de eerste sectie.

Eén plek die dit blootlegt: de CTA-kaart in het mega menu is een
`<a class="… hover:opacity-70">` met een `<span class="btn btn--secondary">`
erin. Die opacity dooft straks de knop mee terwijl die zelf al een hover heeft.
Hij wordt `hover:bg-light-shape` op de kaart — dan verkleurt de kaart en
verkleurt de knop, elk op eigen houtje.

## Range cards

`partials/rangeCard.antlers.html` en `components/range-card.css`.

De kaart wordt een `group`. De shadow-lift die er staat blijft eronder liggen
als klik-affordance; die is met een Figma-verwijzing gedocumenteerd en wordt
niet vervangen maar aangevuld.

```
shape  →  transition-transform duration-500 ease-out group-hover:scale-110
png    →  transition-transform duration-500 ease-out group-hover:scale-105
```

De shape krijgt méér beweging dan de png, en dat is geen willekeur: die shape
is `fill-black/3` — bijna onzichtbaar, dus 5% zou je niet opmerken. De png
staat vol in beeld en heeft aan 5% genoeg. Samen leest het als "het product
komt naar voren terwijl de vorm erachter meeademt".

De shape draagt `-translate-y-1/3 -translate-x-1/4`. In Tailwind v4 zijn
`translate` en `scale` losse CSS-eigenschappen, dus de scale wist die
positionering niet — er is geen `transform`-conflict om te omzeilen. De kaart
heeft `overflow-hidden`, dus de uitzettende shape blijft binnen de rand.

## Nieuws cards

`partials/articleCard.antlers.html` en `components/article-card.css`.

De pijl zit nu in een `<span class="contents">` zonder eigen doos. Die wordt een
echte cirkel:

```
span  →  flex size-10 lg:size-11 shrink-0 items-center justify-center rounded-full
         bg-accent/0 transition-colors duration-200 ease-out group-hover:bg-accent
icon  →  size-5 lg:size-6 -rotate-45 transition-transform duration-300 ease-out
         group-hover:rotate-0
```

De cirkel is in rust `bg-accent/0` en niet afwezig — de doos staat er dus
altijd, en er is geen sprong in de layout op het moment dat de kleur invalt. De
bestaande `group-hover:translate-x-1` verdwijnt; de kanteling neemt die taak
over.

Het beeld schaalt mee met `group-hover:scale-105` over 500ms — dezelfde waarden
die `productCard` al gebruikt.

Daarvoor moet **de `rounded-md` van de `<img>` naar de `<picture>`.**
`global.css` geeft elke `picture` een `overflow: hidden`, maar geen radius.
Schaalt de `<img>` op met zijn eigen ronde hoeken, dan schuiven die hoeken
buiten het vierkante clip-vlak en zie je op hover vierkante hoeken verschijnen.

Dat de productkaart dit probleem niet heeft, bevestigt de diagnose: daar zit het
beeld in een `<a>` met `overflow-hidden rounded-md`, dus de anker-doos knipt en
niet de `<picture>`. De nieuwskaart heeft zo'n wrapper niet — daar draagt de
`<img>` zelf de radius.

```css
.article-card picture {
    @apply rounded-md;
}
```

Daarmee klopt "de fotocontainer blijft identiek" ook letterlijk: de `<picture>`
beweegt niet, alleen de `<img>` erin.

## Locatiekaarten

`partials/locationCard.antlers.html` en `components/locations.css`.

Zelfde patroon als de nieuwskaart, via `group` op de `<a>`:

```
a    →  group ... bg-light hover:bg-accent
svg  →  w-3.5 -rotate-45 transition-transform duration-300 ease-out group-hover:rotate-0
```

In `locations.css` gaat `transition-shadow` naar `transition` — anders klapt
het geel er hard in terwijl de schaduw netjes opbouwt.

De witte cirkel om de pijl blijft wit. Op geel leest die nog steeds als een
knop, en zwart maken zou hem laten botsen met de contactbalk, waar zwart juist
het hover-signaal ís.

## Parallax

`base/motion.css`:

```css
@utility parallax-media {
    --parallax-zoom: 1.12;
    --parallax-shift: 6%;

    scale: var(--parallax-zoom);
    animation: parallax-rise linear both;
    animation-timeline: view();
    animation-range: cover;
}

@keyframes parallax-rise {
    from {
        translate: 0 calc(var(--parallax-shift) * -1);
    }
    to {
        translate: 0 var(--parallax-shift);
    }
}
```

Geen JavaScript. `view()` koppelt de animatie aan de positie van het element in
het venster. Browsers zonder ondersteuning negeren `animation-timeline` en
tonen een stilstaand beeld — precies de juiste degradatie voor een effect dat
subtiel hoort te zijn.

De zoom van 1.12 is afgeleid, niet gekozen: het beeld schuift 6% naar boven én
6% naar beneden, dus er moet aan weerszijden 6% extra hoogte onder de rand
zitten. Verlaagt de shift, dan mag de zoom mee omlaag.

`--parallax-zoom` staat bewust los van de keyframes, zodat een component de
zoom kan bijstellen zonder de beweging te raken. `scale` en `translate` zijn in
modern CSS aparte eigenschappen, dus een transition op de ene botst niet met
een animatie op de andere. Dat is wat het knop-effect hieronder mogelijk maakt
zonder een tweede laag DOM.

**Waar hij komt:** het beeld in `partials/sections/cta.antlers.html` en in
`partials/headers/product.antlers.html`.

De twee donkere lagen van de product-header liggen `absolute inset-0` op de
sectie en niet op het beeld, dus het verloop en de tekst blijven staan terwijl
de foto eronder drijft. Beide secties hebben `overflow-hidden`, dus de zoom
loopt nergens buiten de rand.

De product-header staat bovenaan de pagina en is bij het laden al volledig in
beeld. `animation-range: cover` zet de animatie dan halverwege haar bereik,
niet aan het begin — het beeld start dus rond zijn neutrale positie en niet
zichtbaar verschoven.

## CTA-knop schaalt het achtergrondbeeld

`components/cta.css`:

```css
.cta__media {
    transition: scale 400ms ease-out;
}

.cta:has(.btn:hover) .cta__media {
    --parallax-zoom: 1.18;
}
```

De knop staat in `sectionHeader`, diep in de sectie; het beeld is zijn oom. Van
kind naar boven selecteren kan alleen met `:has()`, vandaar een regel op de
sectie in plaats van een `group` op de knop.

Het CTA-beeld krijgt in de markup beide klassen: `parallax-media cta__media`.

De CTA rendert twee `sectionHeader`s (een mobiele en een desktopvariant), maar
er is er altijd één `hidden` — en een verborgen knop kan niet gehoverd worden,
dus de twee bijten elkaar niet.

## Contactbalk

`partials/contactDetails.antlers.html` en `components/contact-bar.css`.

Drie identieke cirkels, dus een utility:

```css
@utility contact-circle {
    @apply flex size-10 shrink-0 items-center justify-center rounded-full transition-colors duration-200 ease-out;
}
```

De `<a>` wordt een `group`; de cirkel krijgt
`group-hover:bg-black group-hover:text-white`. Alle drie de items doen mee,
WhatsApp inbegrepen — daar verandert alleen de achtergrond van groen naar
zwart, want dat merkglyph heeft `fill="white"` in het bestand zelf staan en is
dus al wit.

De `hover:opacity-70` gaat van alle drie de links af. Anders dooft de hele
regel terwijl de cirkel het signaal moet dragen.

## Openstaande follow-ups, niet in deze diff

**De pijl van de productkaart.** `productCard.antlers.html` heeft een
permanent gele cirkel met een `-rotate-45`-pijl die niet meebeweegt. Na deze
diff kantelen de pijlen van de nieuwskaart en de locatiekaart wél, en is de
productkaart de enige die stil blijft staan. Dat is een echte inconsistentie,
maar de productkaart stond niet in de briefing en de cirkel is daar altijd geel
in plaats van geel-op-hover — die twee verschillen samen vragen een eigen
ontwerpbeslissing, geen meelifter.

**`.btn--pill`-basis.** `section-nav.css` verwijst naar een openstaande
follow-up om de vormdeclaraties van vijf pills te extraheren; die staat in
`docs/superpowers/specs/2026-07-26-pagebuilder-sections-followups.md` en raakt
vier bestaande secties. Deze diff raakt `button.css` wel, maar alleen om
hover-states toe te voegen — de vormdeclaraties blijven staan waar ze staan.

## Testen

De wijzigingen zijn vrijwel volledig CSS en markup-attributen, dus het
zwaartepunt ligt bij handmatige controle in de browser. Een test op een
Tailwind-klasse is bovendien broos: hij bevriest een opmaakkeuze in een
assertie. Daarom wordt er maar op twee plekken iets vastgelegd, en telkens om
een *beslissing* te bewaken, niet om een klasse te tellen.

- **`NavigationTest`** (bestaat) krijgt er een geval bij dat `{{ partial:navigation
  inverse="true" }}` rendert en controleert dat de lichte variant meekomt waar
  de donkere staat bij een gewone render. De keuze tussen die twee is de enige
  tak in dit ontwerp die van een variabele afhangt, en hij is onzichtbaar in de
  helft van de gevallen — een pagina met een lichte header laat een fout in de
  inverse-tak nooit zien.
- **`MegaMenuTest`** (bestaat) krijgt een assertie dat `x-collapse` weg is en
  de `x-transition`-attributen er staan. Dat bewaakt de vervanging zelf: valt
  `x-collapse` er ooit weer in, dan vecht de hoogte-animatie stil met de
  transform en is er niets dat piept.

Verder blijven de bestaande sectietests draaien als regressienet:
`ContactDetailsTest`, `CtaSectionTest`, `LocationsTest`, `ArticleCardTest`,
`RangeCardTest`, `ProductHeaderTest`. Er worden geen nieuwe bestanden voor
aangemaakt.

**De contactbalk valt buiten het testbereik.** `SectionTestCase` rendert zonder
cascade, dus `{{ globals:contact:… }}` is daar altijd leeg — de hele
`{{ if }}`-tak met de drie cirkels rendert eenvoudigweg niet.
`ContactDetailsTest` test vandaag om diezelfde reden alleen de locatiekaarten.
Die drie cirkels controleer je in de browser, niet in phpunit.

Draaien met `phpunit` en 1G geheugen, niet met `php artisan test`.

## Handmatige controle

Wat je alleen ziet door te kijken:

1. De nav-pills op een lichte pagina én op de product-header (wit op beeld).
2. Het mega menu openen en sluiten, en de cursor van de Aanbod-link naar de
   kaart bewegen zonder dat het paneel dichtklapt.
3. De vier knopvarianten naast elkaar, op wit en op `bg-light`.
4. De parallax bij traag scrollen — merkbaar, maar niet als beweging die
   opvalt.
5. De CTA-knop hoveren en zien of het beeld eronder meeschaalt.
6. Alles nog eens met "beweging beperken" aan in de systeeminstellingen.
