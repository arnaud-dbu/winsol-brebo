# winsol-brebo — codeerregels

Statamic + Tailwind v4 + Swiper 14. Onderstaande regels gelden bij elke code-edit.

## Conditionals

Inline condities schrijf je als ternary, niet als `{{ if }}`-blok om één waarde.

```antlers
{{# fout #}}
{{ if featured }}bg-accent{{ else }}bg-white{{ /if }}

{{# goed #}}
{{ featured ? 'bg-accent' : 'bg-white' }}
```

Wordt de conditie complexer dan één keuze — meerdere takken, geneste checks, of
dezelfde conditie die zich in de markup blijft herhalen — dan haal je hem uit de
markup. Twee opties, kies wat het overzichtelijkst leest:

- Bepaal de waarde in een `{{ if }}`/`{{ elseif }}`-blok **boven** de sectie en
  gebruik daaronder alleen de uitkomst.
- Gebruik `{{ switch }}` wanneer je één variabele tegen een reeks vaste waarden
  afzet.

De markup zelf blijft daarmee vrij van takken.

## Views & bestandsindeling

Een overzichtspagina is altijd een `index` in de map van de collectie, nooit een
los `-overview`-bestand op het hoogste niveau:

```
{{# fout #}}
resources/views/range-overview.antlers.html
resources/views/ranges/show.antlers.html

{{# goed #}}
resources/views/ranges/index.antlers.html
resources/views/ranges/show.antlers.html
```

Overzicht en detail van dezelfde collectie horen dus in dezelfde map te staan.

## Styling

Style met Tailwind-utilities in de markup. Geen `style=""`-attributen.

Zodra een component of dezelfde set klassen zich herhaalt, maak je er een eigen
utility van in plaats van de reeks opnieuw uit te schrijven:

```css
@utility card-shell {
    @apply flex flex-col gap-4 rounded-2xl bg-white p-6;
}
```

Utilities komen in `resources/css/`: componentgebonden in `components/<naam>.css`,
generiek in `base/`. Nieuwe utility → import toevoegen in `site.css`.

Nieuwe kleur, spacing of radius wordt eerst een token in het `@theme`-blok van
`site.css`. Geen arbitrary values als `bg-[#1a2b3c]` of `mt-[37px]`.

## Iconen

Een Phosphor-icoon haal je altijd op met de `icon`-tag, nooit met `svg` en een
pad naar `icons/`:

```antlers
{{# fout #}}
{{ svg src="icons/regular/phone" class="size-6" }}

{{# goed #}}
{{ icon src="phone" class="size-6" }}
```

`App\Tags\Icon` zet er `icons/<gewicht>/` omheen. Standaard is `regular` — dat is
het gewicht van de site. Een ander gewicht vraag je met `suffix`, dat zowel de map
als het bestandssuffix zet: `{{ icon src="phone" suffix="fill" }}` wordt
`icons/fill/phone-fill`.

De `svg`-tag blijft voor alles wat níét in `resources/svg/icons/` staat — logo's,
merkglyphs als `whatsapp.svg`, decoratieve vormen als `shape.svg`.

## Typografie

Alles wat typografie is — headings, paragrafen, overline en dergelijke — staat in
`resources/css/base/typography.css`. Niet verspreid over componentbestanden en
niet als losse utility-reeksen in de markup.

Geef daarbij de voorkeur aan `@utility` boven een elementselector, tenzij het
echt om de standaardstijl van dat element gaat.

## Commentaar

Geen commentaar bij code die zichzelf uitlegt. Voeg alleen toe wat je niet uit de
code kunt lezen:

- iets dat ontbreekt of nog niet klopt
- een `TODO` die cruciaal is om niet te vergeten
- een niet-evidente reden waarom iets zo staat en niet anders

## Swiper

Hoeveel cards er per breakpoint zichtbaar zijn, bepaal je via
`options.breakpoints` — niet met CSS-breedtes en niet met aparte instanties:

```js
options.breakpoints = {
    640: {
        slidesPerView: 1.2,
    },
    1024: {
        slidesPerView: 2.2,
        spaceBetween: 20,
    },
    1280: {
        slidesPerView: 2.2,
        spaceBetween: 24,
    },
    1920: {
        slidesPerView: 2.2,
        spaceBetween: 32,
    },
};
```

Swiper-breakpoints zijn **niet cumulatief**: hij kiest de hoogste matchende
breakpoint en merget uitsluitend díe over de basisparameters — lagere
breakpoints worden overgeslagen. Geef elke breakpoint dus een volledig paar mee
zodra je ook `spaceBetween` laat variëren, anders valt die terug op de basis.

## Dit project

- **Tests:** `phpunit` met 1G geheugen, nooit `php artisan test`.
- **Sliders:** niet zelf een breakpoints-object schrijven. Zet
  `data-slider-per-view="1.2,lg:2.2"` en `data-slider-space="16,lg:20,xl:24,3xl:32"`
  op het slider-element; `buildResponsive()` in
  `resources/js/components/sliders.js` bouwt het object en stapelt beide assen
  zelf om het niet-cumulatieve gedrag op te vangen.
- **Formattering:** Prettier doet de Tailwind-klassevolgorde
  (`prettier-plugin-tailwindcss`) en de Antlers-formattering
  (`prettier-plugin-antlers`). Niet handmatig herschikken.
