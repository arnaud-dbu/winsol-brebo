# Animaties en hover-effecten — follow-ups

**Datum:** 2026-08-03
**Hoort bij:** `2026-08-03-animaties-en-hover-effecten-design.md`

Wat tijdens de uitvoering boven kwam en bewust buiten die diff bleef.

## 1. Prettier-drift op twee bestanden, met één gedeelde oorzaak

`resources/views/partials/headers/product.antlers.html` en
`resources/css/components/article-card.css` voldoen allebei niet aan
`npx prettier --check`, en deden dat al vóór dit werk (nagerekend op de
commit waar de tak van aftakt).

Bij de CSS is het een simpele klassevolgorde (`font-semibold uppercase
text-black/40` waar Prettier `font-semibold text-black/40 uppercase` wil).
Die is gratis recht te zetten.

Bij de Antlers-partial zit er een echte botsing onder. Prettier wil de
conditie naar voren halen:

```antlers
{{# nu #}}
<div class="container {{ if image }}absolute inset-0 {{ /if }}flex items-center …">

{{# wat Prettier wil #}}
<div class="{{ if image }}absolute inset-0 {{ /if }}flex container items-center …">
```

En `ProductHeaderTest::test_renders_title_text_and_both_overlays` assert op
de letterlijke string `class="container absolute`, dus die breekt dan.

De echte oorzaak is dat er een `{{ if }}`-blok ín een `class`-attribuut
staat, wat op zichzelf al tegen de conditionals-regel van `CLAUDE.md` ingaat.
Til die conditie naar een variabele boven de markup, en de patstelling lost
zichzelf op: Prettier krijgt een gewoon klasse-attribuut te sorteren en de
test hoeft niet meer op ruwe volgorde te pinnen.

Let op: `prettier --write` op dat bestand slaat ook het `{{# … #}}`-blok
bovenaan plat tot één alinea. Dat blok is bewust gestructureerd. Wie deze
follow-up oppakt, moet dat commentaar apart terugzetten.

## 2. De pijl van de productkaart beweegt niet mee

`partials/productCard.antlers.html` heeft een permanent gele cirkel met een
`-rotate-45`-pijl. Sinds dit werk kantelen de pijlen van de nieuwskaart en
de locatiekaart wél van −45° naar 0°, dus de productkaart is de enige die
stilstaat.

Alleen die rotatie erbij zetten lost het niet op: daar is de cirkel altijd
geel in plaats van geel-op-hover, dus je krijgt een vierde dialect in plaats
van één taal. Dit vraagt een eigen ontwerpbeslissing over wat die kaart op
hover hoort te doen.

## 3. `MegaMenuTest` pint twee opmaakkeuzes vast

`test_the_panel_animates_with_a_transform_and_not_with_a_height_collapse`
assert naast `x-collapse` en `x-transition:enter-start` ook op `origin-top`
en `scale-98`.

`origin-top` is dragend: zonder dat schaalt het paneel vanuit zijn midden en
is het "uit de link schuiven" weg. `scale-98` is een afstelwaarde. Wie de
animatie ooit bijstelt naar `scale-95` maakt daarmee een test rood zonder
dat er gedrag verandert. Overweeg die ene assertie te laten vallen.

## 4. Drie bekend-rode tests

Deze faalden al vóór dit werk en zijn niet aangeraakt:

- `SomfyRangePageTest::test_the_page_renders_its_sections_in_the_designed_order`
- `CardLayoutCascadeTest::test_a_horizontal_cards_section_does_not_leak_into_a_later_products_section`
- `LocationsTest::test_it_credits_the_tile_providers_outside_the_hidden_map`

## 5. Nog visueel te beoordelen: de parallax

De parallax is mechanisch nagerekend en de code klopt, maar of hij op het
scherm het juiste doet is niet bevestigd. De metingen tijdens de uitvoering
liepen via een browsertab die niet gerenderd werd (`visibilityState:
"hidden"`), waardoor `document.timeline` stilstond en elke transitie op tijd
nul bleef hangen. Zulke metingen zeggen niets.

Wat er nog gekeken moet worden:

- Beweegt het beeld merkbaar maar niet opdringerig bij traag scrollen? De
  reis is 6% van de beeldhoogte over een volledige passage.
- Op de product-header blijven de twee donkere lagen bewust staan terwijl de
  foto eronder schuift. Leest dat als diepte of als losdrijven?
- `animation-range: cover` zet een sectie bovenaan de pagina niet op exact
  50% maar rond 60% van haar bereik, dus de header start iets onder neutraal.
  Waarschijnlijk onzichtbaar, het duidelijkst op een hoog desktopvenster.
- Op een engine zonder `animation-timeline` (Safari ≤ 18, Firefox ≤ 143)
  hoort het beeld nu volledig onaangeroerd te renderen. Dat is afgevangen met
  `@supports`, maar niet in zo'n browser nagekeken.
- `.cta:has(.btn:hover)` blijft op touch hangen na een tik, tot je ergens
  anders tikt.
