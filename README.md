<p align="center">
<picture>
    <source srcset="https://statamic.com/assets/branding/squircle/statamic-logo-lime-white.svg" media="(prefers-color-scheme: dark)">
    <img align="center" width="350" alt="Statamic Logo" src="https://statamic.com/assets/branding/squircle/statamic-logo-lime.svg">
</picture>
</p>

## Project opstarten

### Vereisten

- PHP 8.2+ en Composer
- Node.js 18+ en npm
- [Laravel Herd](https://herd.laravel.com) (of Valet)

### Eerste keer installeren

```bash
git clone <repo-url> statamic-base
cd statamic-base
composer setup
```

`composer setup` doet alles in één keer: `composer install`, `.env` aanmaken, app key genereren, database migreren, `npm install` en `npm run build`.

### Draaien met Herd

1. Zorg dat de projectmap in een door Herd geparkeerde map staat (bv. `~/Herd`), **of** link het project handmatig:

    ```bash
    herd link statamic-base
    ```

2. Zet in `.env` de `APP_URL` gelijk aan het Herd-domein (de mapnaam bepaalt het domein):

    ```
    APP_URL=http://statamic-base.test
    ```

3. Start Vite voor CSS/JS met hot reload:

    ```bash
    npm run dev
    ```

4. Open de site op [http://statamic-base.test](http://statamic-base.test). Het control panel vind je op `/cp`.

> [!TIP]
> Herd serveert PHP automatisch — je hoeft géén `php artisan serve` te draaien. Alleen `npm run dev` is nodig tijdens het ontwikkelen. Werk je even niet aan CSS/JS, dan volstaat een eenmalige `npm run build` (geen Vite-proces nodig).

### Control panel gebruiker aanmaken

```bash
php please make:user
```

### Alternatief: zonder Herd

```bash
composer dev
```

Dit start `php artisan serve`, de queue, logs (pail) en Vite samen. De site draait dan op [http://localhost:8000](http://localhost:8000) — zet `APP_URL` in `.env` daarop af.

### Testen op je telefoon

```bash
npm run dev:mobile
```

Dit draait Vite met assets en hot reload via Tailscale, zodat de site op je gsm bereikbaar is op `https://arnauds-macbook-pro.tailcfa200.ts.net` — ook buitenshuis op 4G/5G. Vereist eenmalig per machine:

1. Tailscale-app ingelogd op Mac én gsm (zelfde account),
2. `tailscale serve --bg http://127.0.0.1:8090` en `tailscale serve --bg --https=8443 http://127.0.0.1:5173`,
3. de Host-rewrite proxy in `~/Library/Application Support/Herd/config/valet/Nginx/tailscale-proxy.conf` — zet daar de Host op `winsol-brebo.test` en draai `herd restart`.

### Problemen oplossen

| Probleem | Oplossing |
| --- | --- |
| `Vite manifest not found` of een pagina zonder styling | Draai `npm run dev` (of eenmalig `npm run build`) |
| 404 op `statamic-base.test` | Project niet gelinkt/geparkeerd in Herd — draai `herd link` in de projectmap |
| Verkeerde links of redirects naar een ander domein | `APP_URL` in `.env` komt niet overeen met de URL in je browser |
| Kan niet inloggen op `/cp` | Maak een gebruiker aan met `php please make:user` |
| Wijzigingen in content/templates verschijnen niet | Draai `php please cache:clear` |

## Beeldpijplijn

Drie commando's brengen de foto's van Winsol de site in en houden ze bruikbaar. Ze horen in deze volgorde te draaien.

### 1. `php please winsol:import-images {bronmap} {doelmap}`

Importeert elke `.jpg`/`.jpeg`/`.png` uit de bronmap (recursief) naar de map `{doelmap}` in de `assets`-container, bewaart de oorspronkelijke bestandsnaam in `source_filename` en zet per foto de vlag `watermark` plus het veld `watermark_box` op basis van `App\Services\WatermarkDetector`.

Een foto die er al staat wordt overgeslagen; twee bronbestanden die op hetzelfde doelpad uitkomen leveren een botsing en exitcode 1, zodat er niet stilzwijgend één van de twee verdwijnt. De bestaat-al-check draait op het pad ná sanering, want Statamic maakt `IMG_0001.JPG` bij het uploaden zelf tot `img_0001.jpg` — zou de check op de ruwe naam toetsen, dan importeerde elke run de hele map opnieuw.

> [!IMPORTANT]
> **De bronmap blijft intact, maar alleen doordat het commando via een wegwerpkopie uploadt.** Statamic's `AssetUploader` *verplaatst* zijn bronbestand bij een console-upload in plaats van het te kopiëren (`Uploader::upload()`, er staat geen `source_preset` op deze container). Wees je hiervan bewust bij elke wijziging aan dit commando: geef je `UploadedFile` het echte pad in de bronmap mee, dan is het origineel na de import weg — zonder foutmelding en zonder terugvaloptie. De kopie in `sys_get_temp_dir()` is dus geen omweg maar de hele beveiliging.

### 2. `php please winsol:clean-watermarks`

Snijdt de watermerkbalk weg bij de foto's die de content werkelijk gebruikt en waarvan `watermark` aanstaat. Ongebruikte foto's blijven ongemoeid. Na afloop gaat `watermark` uit, wordt `watermark_box` leeggemaakt en wordt de Glide-cache gewist.

| Optie | Wat het doet |
| --- | --- |
| `--dry-run` | Toont welke foto's bijgesneden zouden worden en wijzigt niets |
| `--list` | Schrijft alleen de bestandsnamen uit — de oorspronkelijke uit `source_filename`, zodat de lijst als aanvraag naar Winsol kan voor versies zonder watermerk |
| `--force` | Slaat de bevestiging over; nodig in een script of CI, want zonder interactieve terminal weigert het commando te draaien |

De actie is onomkeerbaar: de foto's op R2 worden overschreven zonder terugvaloptie. Een box die onwaarschijnlijk hoog ligt of die niets meer zou afsnijden wordt overgeslagen in plaats van toegepast.

### 3. `php please winsol:image-gaps`

De opleveringspoort. Loopt over alle entries, globals en taxonomietermen en meldt twee dingen apart:

- **Placeholders in gebruik** — elk veld dat nog naar `placeholder/` of `dummy-images/` wijst, of naar een van de losse dummybestanden, met collectie, entry en veldpad erbij.
- **Watermerken in gebruik** — elke asset die de content gebruikt en waarvan `watermark` nog aanstaat. `winsol:clean-watermarks` sluit ook met exitcode 0 af wanneer élke foto overgeslagen werd, dus zonder deze tweede lijst kon een site groen door beide commando's met zichtbaar gewatermerkte foto's erop.

Exitcode 0 zolang beide lijsten leeg zijn, anders 1 — bruikbaar als poort in een opleveringsscript. Bij oplevering hoort de uitvoer leeg te zijn.

## About Statamic

Statamic is the flat-first, Laravel + Git powered CMS designed for building beautiful, easy to manage websites.

> [!NOTE]
> This repository contains the code for a fresh Statamic project that is installed via the Statamic CLI tool.
>
> The code for the Statamic Composer package itself can be found at the [Statamic core package repository][cms-repo].


## Learning Statamic

Statamic has extensive [documentation][docs]. We dedicate a significant amount of time and energy every day to improving them, so if something is unclear, feel free to open issues for anything you find confusing or incomplete. We are happy to consider anything you feel will make the docs and CMS better.

## Support

We provide official developer support on [Statamic Pro](https://statamic.com/pricing) projects. Community-driven support is available via [GitHub Discussions](https://github.com/statamic/cms/discussions) and in [Discord][discord].


## Contributing

Thank you for considering contributing to Statamic! We simply ask that you review the [contribution guide][contribution] before you open issues or send pull requests.


## Code of Conduct

In order to ensure that the Statamic community is welcoming to all and generally a rad place to belong, please review and abide by the [Code of Conduct](https://github.com/statamic/cms/wiki/Code-of-Conduct).


## Important Links

- [Statamic Main Site](https://statamic.com)
- [Statamic Documentation][docs]
- [Statamic Core Package Repo][cms-repo]
- [Statamic Migrator](https://github.com/statamic/migrator)
- [Statamic Discord][discord]

[docs]: https://statamic.dev/
[discord]: https://statamic.com/discord
[contribution]: https://github.com/statamic/cms/blob/master/CONTRIBUTING.md
[cms-repo]: https://github.com/statamic/cms
