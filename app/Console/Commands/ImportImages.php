<?php

namespace App\Console\Commands;

use App\Services\WatermarkDetector;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Statamic\Assets\AssetUploader;
use Statamic\Facades\AssetContainer;
use Symfony\Component\Finder\Finder;

class ImportImages extends Command
{
    protected $signature = 'winsol:import-images
        {source : Map met de foto\'s die geimporteerd moeten worden}
        {folder : Doelmap binnen de assets-container, bijvoorbeeld de range-slug}';

    protected $description = 'Importeert foto\'s naar de assets-container en markeert watermerken';

    /**
     * `source_filename` van alles wat al in de doelmap staat, als lookup.
     * Eenmalig opgebouwd bij de eerste toets in een run.
     *
     * @var array<string, int>
     */
    private array $importedSourceNames = [];

    public function handle(WatermarkDetector $detector): int
    {
        $source = rtrim($this->argument('source'), '/');
        $folder = trim($this->argument('folder'), '/');

        if (! is_dir($source)) {
            $this->error("Bronmap bestaat niet: {$source}");

            return self::FAILURE;
        }

        $container = AssetContainer::find('assets');

        $imported = 0;
        $skipped = 0;
        $flagged = 0;
        $conflicts = 0;

        /**
         * Doelpad => bronbestand, om binnen deze ene run te vangen of twee
         * bronbestanden op hetzelfde doelpad uitkomen. Finder::in() recurseert
         * standaard; platslaan tot enkel de bestandsnaam liet twee bestanden met
         * dezelfde naam in verschillende submappen samenvallen, waarna de tweede
         * stil als "overgeslagen" telde in plaats van als botsing.
         *
         * @var array<string, string>
         */
        $seen = [];

        // Uploaden via de container in plaats van rechtstreeks naar de disk: dat
        // vuurt AssetUploaded, waardoor CompressUploadedAsset zijn werk doet.
        // Een kaal Storage::put zou ongecomprimeerde originelen op R2 zetten.
        foreach (Finder::create()->files()->in($source)->name('/\.(jpe?g|png)$/i') as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $path = $this->sanitizedPath($folder.'/'.$relative);

            if (isset($seen[$path])) {
                $conflicts++;
                $this->error("Botsing op {$path}: {$seen[$path]} en {$file->getRealPath()} wijzen naar hetzelfde doel.");

                continue;
            }
            $seen[$path] = $file->getRealPath();

            if ($this->alreadyImported($container, $folder, $path, $relative)) {
                $skipped++;

                continue;
            }

            // Statamic's Uploader verwijdert zijn bronbestand zodra hij vanuit de
            // console draait (Uploader::upload(), geen source_preset op deze
            // container). Wijst UploadedFile naar het echte pad in de bronmap, dan
            // is het origineel na deze aanroep weg. Daarom eerst een wegwerpkopie.
            $tempPath = sys_get_temp_dir().'/winsol-import-'.bin2hex(random_bytes(8)).'.'.$file->getExtension();
            copy($file->getRealPath(), $tempPath);

            $asset = $container->makeAsset($path)->upload(
                new UploadedFile($tempPath, $file->getFilename(), null, null, true)
            );

            if (is_file($tempPath)) {
                unlink($tempPath);
            }

            $result = $detector->detect($asset->disk()->get($asset->path()));

            // De sanering is niet omkeerbaar: `Réalisation Été - Screens
            // 04.JPG` wordt `realisation-ete--screens-04.jpg`, en daarmee is
            // de foto bij Winsol niet meer terug te zoeken. Bewaren kost hier
            // niets; na de importbatches zou het een backfill over de hele
            // container zijn.
            $asset->set('source_filename', $relative);
            $asset->set('watermark', $result->hasWatermark);
            $asset->set('watermark_box', $result->box
                ? implode(',', [$result->box['x'], $result->box['y'], $result->box['width'], $result->box['height']])
                : '');
            $asset->save();

            $imported++;
            $flagged += $result->hasWatermark ? 1 : 0;
        }

        $this->importedSourceNames = [];

        $message = "{$imported} geimporteerd, {$skipped} overgeslagen, {$flagged} met watermerk";
        $message .= $conflicts > 0 ? ", {$conflicts} botsingen." : '.';
        $this->info($message);

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Is dit bronbestand al eerder geïmporteerd?
     *
     * De voorspelde padvergelijking alléén volstond niet. `uploadPath()` past
     * `getSafeFilename()` toe op de bestandsnaam die het asset zélf al draagt,
     * en het resultaat wijkt af zodra er tekens in zitten die deze functie niet
     * kent — een en-dash in `Pergolas – Terrasoverkappingen – Z!P & Z!P Cube`
     * leverde drie streepjes op waar de container er twee opsloeg, waarna een
     * tweede run de foto niet terugvond en Statamic er een timestamp-suffix
     * achter plakte.
     *
     * Daarom is `source_filename` leidend: dat is de naam zoals hij in de
     * bronmap staat, exact zoals deze import hem heeft weggeschreven. Geen
     * voorspelling, dus ook niets dat kan afwijken. Het voorspelde pad blijft
     * als tweede toets staan voor assets die vóór dit veld zijn geïmporteerd.
     *
     * @param  \Statamic\Contracts\Assets\AssetContainer  $container
     */
    private function alreadyImported($container, string $folder, string $path, string $relative): bool
    {
        if ($container->asset($path)) {
            return true;
        }

        if ($this->importedSourceNames === []) {
            $this->importedSourceNames = $container->assets($folder, true)
                ->map->get('source_filename')
                ->filter()
                ->flip()
                ->all();
        }

        return isset($this->importedSourceNames[$relative]);
    }

    /**
     * Bootst het pad na dat `AssetUploader::uploadPath()` er straks zelf van
     * maakt. Blijft nodig om binnen één run botsingen te betrappen en om de
     * bestaat-al-toets te dekken voor assets zonder `source_filename`.
     */
    private function sanitizedPath(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (config('statamic.assets.lowercase')) {
            $extension = strtolower($extension);
        }

        $filename = AssetUploader::getSafeFilename(pathinfo($path, PATHINFO_FILENAME));

        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $directory = $directory === '.' ? '' : $directory.'/';

        return $directory.$filename.'.'.$extension;
    }
}
