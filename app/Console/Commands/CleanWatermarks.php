<?php

namespace App\Console\Commands;

use App\Services\UsedAssetFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Statamic\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Throwable;

class CleanWatermarks extends Command
{
    /**
     * Marge onder de gedetecteerde boxrand die de antialiasing rond de
     * letters opvangt, die net onder de witdrempel valt en anders als
     * grijze rand achterblijft.
     */
    private const MARGIN = 4;

    protected $signature = 'winsol:clean-watermarks
        {--dry-run : Toont wat er zou gebeuren, zonder iets te wijzigen}
        {--list : Schrijft alleen de bestandsnamen uit, voor een aanvraag bij Winsol}
        {--force : Vraagt geen bevestiging voor de onomkeerbare bulkactie}';

    protected $description = 'Snijdt het Winsol-watermerk weg bij de foto\'s die entries werkelijk gebruiken';

    public function handle(UsedAssetFinder $finder): int
    {
        $container = AssetContainer::find('assets');

        /** @var Collection<int, Asset> $targets */
        $targets = $finder->paths()
            ->map(fn (string $path): ?Asset => $container->asset($path))
            ->filter()
            ->filter(fn (Asset $asset): bool => (bool) $asset->get('watermark'));

        if ($this->option('list')) {
            $targets->each(fn (Asset $asset): mixed => $this->line($asset->path()));
            $this->info("{$targets->count()} foto's met watermerk in gebruik.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $targets->each(fn (Asset $asset): mixed => $this->line("zou bijsnijden: {$asset->path()}"));
            $this->info("{$targets->count()} zouden bijgesneden worden.");

            return self::SUCCESS;
        }

        if (! $this->confirmDestructiveRun($targets->count())) {
            $this->info('Geannuleerd, niets gewijzigd.');

            return self::SUCCESS;
        }

        $manager = new ImageManager(new GdDriver);
        $cropped = 0;

        foreach ($targets as $asset) {
            if ($this->cropAsset($asset, $manager)) {
                $cropped++;
            }
        }

        $this->call('statamic:glide:clear');

        $this->info("{$cropped} bijgesneden.");

        return self::SUCCESS;
    }

    /**
     * Dit commando overschrijft foto's op R2 zonder terugvaloptie. Een
     * script of CI-run heeft geen interactieve terminal en moet expliciet
     * --force meegeven; anders zou Symfony's ConfirmationQuestion in een
     * niet-interactieve context stilzwijgend de default-waarde teruggeven
     * en per ongeluk toch bijsnijden.
     */
    private function confirmDestructiveRun(int $count): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->warn("{$count} foto's met watermerk worden onomkeerbaar bijgesneden op R2, zonder terugvaloptie.");

        return $this->confirm('Doorgaan?', false);
    }

    private function cropAsset(Asset $asset, ImageManager $manager): bool
    {
        try {
            return $this->attemptCrop($asset, $manager);
        } catch (Throwable $e) {
            $this->warn("Fout bij {$asset->path()}, overgeslagen: {$e->getMessage()}");

            return false;
        }
    }

    private function attemptCrop(Asset $asset, ImageManager $manager): bool
    {
        $box = $this->parseBox((string) $asset->get('watermark_box'));

        if ($box === null) {
            $this->warn("Geen bruikbaar watermerkvlak, overgeslagen: {$asset->path()}");

            return false;
        }

        $image = $manager->read($asset->disk()->get($asset->path()));
        $height = $image->height();

        // Een watermerk hoort onderin te zitten. Ligt de boxrand boven de
        // helft van de beeldhoogte, dan komt hij uit onzin (een niet-numeriek
        // veld dat naar 0 casst) of uit een box die niet meer bij dit
        // bestand hoort — geen van beide is een reden om te snijden.
        if ($box['y'] < $height / 2) {
            $this->warn("Onwaarschijnlijk watermerkvlak (y={$box['y']} op hoogte {$height}), overgeslagen: {$asset->path()}");

            return false;
        }

        // Nooit voorbij de werkelijke hoogte snijden: crop() van een groter
        // gevraagde hoogte dan de afbeelding pad juist bij in plaats van te
        // clippen, en dat plakt een zwarte band onderaan.
        $keepHeight = max(1, min($height, $box['y'] - self::MARGIN));

        $croppedImage = $image->crop($image->width(), $keepHeight, 0, 0);

        // Terugschrijven in het formaat van het bestand zelf: een png-pad
        // met jpeg-bytes erin zou Glide en elke browser misleiden. Intervention
        // Image kiest hier ook automatisch de juiste encoder voor webp en
        // behoudt de alphakanaal van een png, in tegenstelling tot een kale
        // imagepng()-aanroep.
        $quality = (int) config('image-compression.jpeg_quality');

        $bytes = (string) match (strtolower($asset->extension())) {
            'png' => $croppedImage->toPng(),
            'webp' => $croppedImage->toWebp(quality: $quality),
            default => $croppedImage->toJpeg(quality: $quality),
        };

        $asset->disk()->put($asset->path(), $bytes);

        $asset->set('watermark', false);
        $asset->set('watermark_box', '');
        Cache::forget($asset->metaCacheKey());
        $asset->writeMeta($asset->generateMeta());
        $asset->save();

        return true;
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function parseBox(string $raw): ?array
    {
        $parts = explode(',', $raw);

        if (count($parts) !== 4 || collect($parts)->contains(fn (string $part): bool => ! is_numeric($part))) {
            return null;
        }

        [$x, $y, $width, $height] = array_map(intval(...), $parts);

        return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
    }
}
