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

            if ($container->asset($path)) {
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

        $message = "{$imported} geimporteerd, {$skipped} overgeslagen, {$flagged} met watermerk";
        $message .= $conflicts > 0 ? ", {$conflicts} botsingen." : '.';
        $this->info($message);

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Bootst het pad na dat `AssetUploader::uploadPath()` er straks zelf van
     * maakt, en gebruikt daarvoor diens eigen `getSafeFilename()` in plaats
     * van een eigen kopie: `config('statamic.assets.lowercase')` staat aan,
     * en die methode strijkt spaties, aanhalingstekens en accenten glad. Toetst
     * de bestaat-al-check op het ongesaneerde pad, dan vindt een tweede run
     * `IMG_0001.JPG` nooit terug onder het al opgeslagen `img_0001.jpg`, en
     * plakt Statamic er een timestamp-suffix achter — in de echte bronmap is
     * geen enkele bestandsnaam al safe, dus zou dat elke foto bij elke run
     * opnieuw uploaden. De mapnamen zelf saneert `uploadPath()` niet, dus die
     * blijven hier ook ongemoeid.
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
