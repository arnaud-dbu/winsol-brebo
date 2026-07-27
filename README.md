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
