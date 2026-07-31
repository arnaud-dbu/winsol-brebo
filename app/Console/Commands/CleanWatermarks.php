<?php

namespace App\Console\Commands;

use App\Services\UsedAssetFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Assets\Asset;
use Statamic\Facades\AssetContainer;

class CleanWatermarks extends Command
{
    protected $signature = 'winsol:clean-watermarks
        {--dry-run : Toont wat er zou gebeuren, zonder iets te wijzigen}
        {--list : Schrijft alleen de bestandsnamen uit, voor een aanvraag bij Winsol}';

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
            $targets->each(fn (Asset $asset) => $this->line($asset->path()));
            $this->info("{$targets->count()} foto's met watermerk in gebruik.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $targets->each(fn (Asset $asset) => $this->line("zou bijsnijden: {$asset->path()}"));
            $this->info("{$targets->count()} zouden bijgesneden worden.");

            return self::SUCCESS;
        }

        $cropped = 0;

        foreach ($targets as $asset) {
            if ($this->cropAsset($asset)) {
                $cropped++;
            }
        }

        $this->call('statamic:glide:clear');

        $this->info("{$cropped} bijgesneden.");

        return self::SUCCESS;
    }

    private function cropAsset(Asset $asset): bool
    {
        $box = array_map('intval', explode(',', (string) $asset->get('watermark_box')));

        if (count($box) !== 4) {
            $this->warn("Geen bruikbaar watermerkvlak, overgeslagen: {$asset->path()}");

            return false;
        }

        [, $boxY] = $box;

        $image = @imagecreatefromstring($asset->disk()->get($asset->path()));

        if ($image === false) {
            $this->warn("Kon niet lezen, overgeslagen: {$asset->path()}");

            return false;
        }

        // Snij tot net boven het watermerk. Een marge van vier pixels vangt
        // de antialiasing rond de letters op, die net onder de witdrempel
        // valt en anders als grijze rand achterblijft.
        $keepHeight = max(1, $boxY - 4);
        $croppedImage = imagecrop($image, ['x' => 0, 'y' => 0, 'width' => imagesx($image), 'height' => $keepHeight]);
        imagedestroy($image);

        if ($croppedImage === false) {
            $this->warn("Bijsnijden mislukt, overgeslagen: {$asset->path()}");

            return false;
        }

        // Terugschrijven in het formaat van het bestand zelf: een png-pad
        // met jpeg-bytes erin zou Glide en elke browser misleiden.
        ob_start();

        if (strtolower($asset->extension()) === 'png') {
            imagepng($croppedImage);
        } else {
            imagejpeg($croppedImage, null, (int) config('image-compression.jpeg_quality'));
        }

        $bytes = ob_get_clean();
        imagedestroy($croppedImage);

        $asset->disk()->put($asset->path(), $bytes);

        $asset->set('watermark', false);
        $asset->set('watermark_box', '');
        Cache::forget($asset->metaCacheKey());
        $asset->writeMeta($asset->generateMeta());
        $asset->save();

        return true;
    }
}
