# Modelwerk — open punten

**Datum:** 2026-07-31
**Bron:** de reviews van `docs/superpowers/plans/2026-07-31-winsol-brebo-modelwerk.md`

Wat tijdens de uitvoering is opgemerkt maar bewust is blijven liggen. De
eindreview heeft dit getrieerd: alles wat vóór samenvoegen moest, is opgelost en
staat hier niet meer in. Wat hieronder staat, mag wachten.

## Twee tests staan bewust rood

De suite draait op 358 tests met twee failures. Dat is geen verwaarlozing maar
een afweging: bij allebei zou elke herschrijving iets vaststellen wat de auteur
nooit bedoeld heeft.

- **`CardLayoutCascadeTest::test_a_horizontal_cards_section_does_not_leak_into_a_later_products_section`** —
  de premisse bestaat niet meer. `card.antlers.html` leest nergens een
  `layout`-argument en `products.antlers.html` rendert `productCard`. De test
  beschrijft een lekroute die er structureel niet is; hem laten slagen betekent
  een nieuwe test schrijven.
- **`ReparationSectionTest::test_renders_the_decorative_watermark_out_of_the_accessibility_tree`** —
  het bronmateriaal spreekt zichzelf tegen. Commit `a257ed5` herschreef de
  assertie naar de svg-vorm én voegde in `test_renders_without_an_image` een
  assertie toe die zegt dat het watermerk aan het beeld hangt, terwijl de wrapper
  in `reparation.antlers.html:7` ongewijzigd bleef. Oplossen vergt gokken naar
  positioneringswaarden die nergens in de repo staan.

Beide verdienen een eigen beslissing van de eigenaar, niet een reparatie
onderweg.

## Raakt het contentwerk van project 2

- **`sectionHeader.antlers.html:1-5`** gebruikt een `{{ if }}`/`{{ else }}` om
  één waarde (`rule_class`) te zetten — dezelfde cascade-assignment-defectklasse
  die `CardLayoutCascadeTest` beschreef, op een partial die op vrijwel elke
  pagina meermaals draait.
- **`card.antlers.html`** bevat een kapotte klasse `@lg:spect-auto @lg:`
  (bedoeld was `aspect-auto`) die stilzwijgend niets doet.
- **Het label van `quicklinks/nl/vraag-brochure-aan.md`** is "Brochure
  aanvragen" en de tekst noemt "in uw bus of mailbox". Dat klopt niet meer nu de
  kaart rechtstreeks een pdf in een tabblad opent.
- **`locations.yaml` mist `localizable`** op het automatisch toegevoegde
  `title`-veld. Hoort bij de veldsweep die de FR-site sowieso vraagt.
- **`content/globals/globals.yaml`** bevat verouderde waarden naast
  `content/globals/nl/globals.yaml`. `GlobalsStore::makeItemFromFile` leest daar
  alleen `title` en `sites` uit, dus het is inerte dode data — maar verwarrend.

## Beeldpijplijn

- **`dscf4033.jpg` staat met de hand in de dummy-lijst** van
  `winsol:image-gaps`. Een volgend los dummybestand met een neutrale naam glipt
  door zowel de poort als de content-test. Verdwijnt vanzelf zodra project 2 de
  echte beelden plaatst.
- **`ImageGaps` laat de scanner twee keer lopen** (eigen scan plus die via
  `UsedAssetFinder`). Merkbaar pas op een volle container.
- **`CleanWatermarks` vertrouwt op de opgeslagen box** in plaats van vlak vóór
  het snijden opnieuw te meten. De bytes zijn op dat moment toch al ingelezen.
  Eén `WatermarkDetector::detect()`-aanroep zou de hele klasse
  verouderde-box-risico's wegnemen die nu met clamps is ingedamd.
- **`ImportImages` controleert de returnwaarde van `copy()` niet** en ruimt de
  wegwerpkopie niet op bij een afgebroken upload. Beide falen luid, dus geen
  stille schade.
- **Het hernoempad in `ImportImages` is dode code**: `ImageCompressor` hernoemt
  alleen HEIC en HEIF, en die filtert de Finder eruit.

## Testbrosheid

- `PageBuilderPageTest` en `CardsSectionTest` tellen kaarten via decoratieve
  stijldetails (een scrim-gradient, `class="card "`). Een Prettier-herordening
  breekt die met een onleesbare melding.
- `ServiceNavTest:59` pint een ongesorteerde klassevolgorde vast, terwijl
  Prettier die volgorde bepaalt.
- `RangeShowPageTest` mist `assertOk()`; een 500 geeft nu een verwarrende
  haystack-melding.
- Commit-hashes in testcommentaar (`CardTest`, `GridCtaSectionTest`,
  `ReparationFormTest`, `PageBuilderPageTest`) verjaren bij de eerste rebase.
- `BrochureFieldTest` en de quicklinks-tests pinnen `localizable` en het
  `type`-veld niet vast.

## Kleiner

- `tests/bootstrap.php` dupliceert het cachepad naast `config/cache.php` en
  `CACHE_STORE`/`APP_ENV` naast `phpunit.xml`, en gebruikt `php` uit `PATH` in
  plaats van `PHP_BINARY`.
- `resources/fieldsets/text.yaml` heeft al langer een eigen `media`/`video`/
  `image`-trio in een ander formaat dat `text.antlers.html` nooit uitleest.
- `embed.antlers.html` gebruikt een handgeschreven `<h2>` in plaats van
  `partial:sectionHeader`, en heeft een hardgecodeerde fallbacktekst naast een
  footer die `trans:site.*` gebruikt.
- De `extendPath`-heuristiek in `ImageGaps` labelt ook niet-replicatorvelden met
  een `type`-sleutel als sectie, bijvoorbeeld het `link`-fieldset.
- De docblock op `AppServiceProvider:25` en het plan stellen dat een verweesd
  product "een 200 met de rangepagina" gaf. In werkelijkheid rendert het de
  product-entry zelf; de winst van de sentinel is dat het product de
  rangenamespace niet meer bezet. De formulering is te stellig.

## CLAUDE.md spreekt zichzelf tegen

Onderaan `CLAUDE.md` staat een blok Laravel Boost-richtlijnen dat
`php artisan test --compact` voorschrijft. Dat botst met de projectregel
bovenaan hetzelfde bestand: `vendor/bin/phpunit -d memory_limit=1G`, nooit
`php artisan test`. Elke subagent in dit traject heeft die correctie expliciet
meegekregen. Zolang beide er staan, gaat een agent die alleen het onderste blok
leest de verkeerde kant op.
