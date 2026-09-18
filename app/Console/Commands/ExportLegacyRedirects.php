<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Het oude domein wijst nog niet naar deze server, dus `RedirectLegacyUrls`
 * ziet geen enkel verzoek voor `winsoldilbeek.be`. De omleidingen draaien
 * daarom als los PHP-bestand in de front controller van de oude site, bij
 * The Rising Castle. Dit commando bouwt dat bestand uit dezelfde tabel en met
 * hetzelfde algoritme als `App\Services\LegacyRedirect`.
 *
 * Eén verschil: een adres dat nergens op uitkomt gaat hier niet naar de 404
 * van de nieuwe site, maar wordt doorgelaten. Anders zouden `/cms/` en de
 * documenten op de oude server onbereikbaar worden.
 */
class ExportLegacyRedirects extends Command
{
    protected $signature = 'winsol:export-legacy-redirects
                            {path=.scratch/redirects/winsol-legacy-redirects.php : Waar het bestand komt}';

    protected $description = 'Bouwt het omleidingsbestand voor de oude server van winsoldilbeek.be uit config/legacy_redirects.php';

    public function handle(): int
    {
        $path = $this->argument('path');

        file_put_contents($path, $this->render());

        $this->info('Geschreven naar '.$path.' ('.count(config('legacy_redirects.paths')).' regels).');

        return self::SUCCESS;
    }

    public function render(): string
    {
        return strtr(self::TEMPLATE, [
            '{{ target }}' => var_export(config('legacy_redirects.target'), true),
            '{{ paths }}' => $this->exportMap(config('legacy_redirects.paths')),
            '{{ homepages }}' => $this->exportList(config('legacy_redirects.homepages')),
            '{{ brochures }}' => $this->exportMap(config('legacy_redirects.brochures')),
            '{{ brochure_slugs }}' => $this->exportList(config('legacy_redirects.brochure_slugs')),
        ]);
    }

    /**
     * @param  array<string, string>  $map
     */
    private function exportMap(array $map): string
    {
        $width = max(array_map(fn (string $key): int => strlen(var_export($key, true)), array_keys($map)));

        $lines = array_map(
            fn (string $key, string $value): string => '        '.str_pad(var_export($key, true), $width).' => '.var_export($value, true).',',
            array_keys($map),
            $map,
        );

        return "[\n".implode("\n", $lines)."\n    ]";
    }

    /**
     * @param  list<string>  $list
     */
    private function exportList(array $list): string
    {
        $lines = array_map(fn (string $item): string => '        '.var_export($item, true).',', $list);

        return "[\n".implode("\n", $lines)."\n    ]";
    }

    private const TEMPLATE = <<<'PHP'
<?php

/**
 * Omleidingen van winsoldilbeek.be naar winsol-brebo.be.
 *
 * Gegenereerd met `php artisan winsol:export-legacy-redirects` in de repository
 * van winsol-brebo.be. Niet met de hand bijwerken: vraag een nieuwe versie als
 * de tabel verandert.
 *
 * Gebruik: includeer dit bestand als eerste regel van de front controller van
 * de website, vóór er iets anders gebeurt.
 *
 *     require __DIR__ . '/winsol-legacy-redirects.php';
 *
 * Een adres zonder eigen regel klimt segment voor segment omhoog tot het er een
 * vindt: `/nl/Ons-aanbod/Rolluiken/Iets/` gaat naar de rolluikenpagina. De klim
 * komt nooit op een homepage uit. Vindt hij niets, dan doet dit bestand niets
 * en laadt de pagina zoals altijd. Daarmee blijven /cms/, /documents/ en alle
 * andere links op dit domein ongemoeid, zonder uitzonderingslijst.
 *
 * Alleen GET en HEAD worden omgeleid. Een 301 op een POST laat de browser
 * opnieuw versturen zonder body, waardoor een formulier stil zou leeglopen.
 *
 * Alles staat in een functie, zodat de `return` hierin ook werkt als het
 * bestand in de front controller geplakt wordt in plaats van ge-`require`d.
 */

(function (): void {
    if (! in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        return;
    }

    $target = {{ target }};

    $paths = {{ paths }};

    $homepages = {{ homepages }};

    $brochures = {{ brochures }};

    $brochureSlugs = {{ brochure_slugs }};

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $path = strtolower(preg_replace('#/+#', '/', '/' . $path));
    $path = $path === '/' ? '/' : rtrim($path, '/');

    $segments = explode('/', trim($path, '/'));
    $destination = $paths[$path] ?? null;

    if ($destination === null && in_array(end($segments), $brochureSlugs, true)) {
        $destination = $brochures['/' . reset($segments)] ?? null;
    }

    while ($destination === null && count($segments) > 1) {
        array_pop($segments);

        $parent = $paths['/' . implode('/', $segments)] ?? null;

        if ($parent !== null && ! in_array($parent, $homepages, true)) {
            $destination = $parent;
        }
    }

    if ($destination === null) {
        return;
    }

    $query = $_SERVER['QUERY_STRING'] ?? '';

    header('Location: ' . $target . $destination . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
})();

PHP;
}
