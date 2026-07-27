# Offertepagina — open punten

Wat na het bouwen van `/offerte` open bleef, plus de bevindingen uit de
eindreview die bewust niet in deze branch zijn opgelost. Niets hiervan
blokkeert de pagina zelf; het eerste blok wél het live zetten ervan.

Bron: `2026-07-27-offerte-page-design.md` en het plan
`docs/superpowers/plans/2026-07-27-offerte-page.md`.

## Vóór de pagina live gaat

### 1. De R2-bucket achter `r2_private` moet zelf niet publiek zijn

De `private`-assetcontainer staat sinds de eindreview op een eigen schijf,
`r2_private` in `config/filesystems.php`. Die schijf heeft bewust géén
`url`-sleutel: `AssetContainer::accessible()` kijkt letterlijk of de
schijfconfig een `url` heeft, en `private()` is de ontkenning daarvan. Zonder
`url` publiceert Statamic dus geen URL meer en serveert het de bestanden via
zijn eigen afgeschermde route.

**Dat is een halve garantie.** Het ontbreken van een `url` weerhoudt Statamic
ervan een link te tonen — het maakt het object in de bucket niet
ontoegankelijk. Staat de bucket (of de R2-custom-domain-koppeling) op
world-readable, dan is het pad nog steeds op te vragen door wie het kent of
raadt. Klantfoto's en bouwplannen van particulieren horen daar niet.

Pre-launchcheck, uit te voeren door wie de R2-console beheert:

- de bucket achter `R2_PRIVATE_BUCKET` heeft géén publieke r2.dev-URL en géén
  custom domain gekoppeld
- een `curl` op een bekend objectpad geeft 403, niet 200
- de accountsleutels in `R2_ACCESS_KEY_ID`/`R2_SECRET_ACCESS_KEY` hebben schrijf-
  rechten op die bucket (ze zijn gedeeld met de publieke `r2`-schijf)

Twee nieuwe env-variabelen, allebei met een werkende fallback zodat een
omgeving die ze niet zet niet stukloopt:

- `R2_PRIVATE_BUCKET` — valt terug op `R2_BUCKET`, dus standaard dezelfde
  bucket als de publieke assets. **Dit is precies de situatie die je wilt
  vermijden**: zolang deze niet gezet is, staan de uploads in de publieke
  bucket, alleen zonder gepubliceerde URL. Een aparte bucket is de bedoeling.
- `R2_PRIVATE_ROOT` — valt terug op `private`, een eigen prefix binnen de
  bucket.

### 2. Het notificatieadres is nog een placeholder

`resources/forms/offerte.yaml` stuurt naar `hello@stuw.agency`, overgenomen van
het contactformulier. Dat is het adres van het bureau, niet van de klant. Het
echte adres (of de mailinglijst) van Winsol Brebo is nog nodig — anders komt
geen enkele offerteaanvraag aan waar hij hoort.

## Losse observaties uit de eindreview

Geen van deze is opgepakt; ze staan hier zodat ze niet opnieuw ontdekt hoeven
te worden.

- **Het herstellingsformulier heeft hetzelfde validatiegat als `products`
  had.** `resources/views/partials/reparationForm.antlers.html` op `main`
  schrijft zijn filiaal-`<select>` met de hand uit `{{ collection:locations }}`,
  zonder enige server-side `in:`-controle. Een vervalste POST zet daar dus
  willekeurige tekst in de notificatiemail — dezelfde klasse fout als de
  bypass die op `products` gedicht is. `App\Fieldtypes\LocationSelect` (deze
  branch) doet precies dit netjes: hem daar ook gebruiken schrapt de
  handgeschreven markup én sluit het gat.
- **reCAPTCHA is site-breed dood.** `app/Listeners/VerifyRecaptcha.php` bestaat,
  maar is nergens aan het `FormSubmitted`-event gekoppeld — niet in
  `EventServiceProvider`, niet via auto-discovery. Beide formulieren leunen dus
  volledig op de honeypot. Ofwel de listener registreren, ofwel het bestand
  weggooien; het nu laten staan suggereert bescherming die er niet is.
- **`.offerte-form` staat twee keer in `offerte-form.css`**: bovenaan het
  kaartuiterlijk, onderaan de rasterplaatsing bij de rest van de
  paginaopbouw. Bewuste groepering — bij elkaar zetten leest slechter zolang
  de twee blokken over verschillende dingen gaan — maar als het bestand
  groeit is samenvoegen de veiligere keuze.
- **Het succesblok hardcodeert `href="/realisaties"`**, terwijl de CTA een paar
  regels verderop in `content/collections/pages/offerte.md` diezelfde pagina
  via een entry-referentie oplost. Verandert de slug, dan verhuist de CTA mee
  en de knop in de bevestiging niet.
