<?php

namespace App\Console\Commands;

use App\Services\WatermarkDetector;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
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

        // Uploaden via de container in plaats van rechtstreeks naar de disk: dat
        // vuurt AssetUploaded, waardoor CompressUploadedAsset zijn werk doet.
        // Een kaal Storage::put zou ongecomprimeerde originelen op R2 zetten.
        foreach (Finder::create()->files()->in($source)->name('/\.(jpe?g|png)$/i') as $file) {
            $path = $folder.'/'.$file->getFilename();

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

            $asset->set('watermark', $result->hasWatermark);
            $asset->set('watermark_box', $result->box
                ? implode(',', [$result->box['x'], $result->box['y'], $result->box['width'], $result->box['height']])
                : '');
            $asset->save();

            $imported++;
            $flagged += $result->hasWatermark ? 1 : 0;
        }

        $this->info("{$imported} geimporteerd, {$skipped} overgeslagen, {$flagged} met watermerk.");

        return self::SUCCESS;
    }
}
