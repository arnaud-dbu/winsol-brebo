<?php

namespace App\Console\Commands;

use App\Services\UsedAssetFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Statamic\Assets\Asset;
use Throwable;

class CleanWatermarks extends Command
{
    /**
     * Marge onder de gedetecteerde boxrand die de antialiasing rond de
     * letters opvangt, die net onder de witdrempel valt en anders als
     * grijze rand achterblijft.
     */
    private const MARGIN = 4;

    /**
     * WatermarkDetector meet zijn hoekzone vanaf 0,845 van de beeldhoogte
     * (zie App\Services\WatermarkDetector::CORNER_Y); een echte watermerkbox
     * ligt dus altijd in de onderste ~15%. Deze grens houdt ruimte voor de
     * hoogte van de letters zelf, die boven die hoeklijn uitsteken, maar
     * weigert een box die halverwege de foto zou beginnen.
     */
    private const MIN_WATERMARK_Y_FRACTION = 0.75;

    protected $signature = 'winsol:clean-watermarks
        {--dry-run : Toont wat er zou gebeuren, zonder iets te wijzigen}
        {--list : Schrijft alleen de bestandsnamen uit, voor een aanvraag bij Winsol}
        {--force : Vraagt geen bevestiging voor de onomkeerbare bulkactie}';

    protected $description = 'Snijdt het Winsol-watermerk weg bij de foto\'s die entries werkelijk gebruiken';

    public function handle(UsedAssetFinder $finder): int
    {
        /** @var Collection<int, Asset> $targets */
        $targets = $finder->assets()
            ->filter(fn (Asset $asset): bool => (bool) $asset->get('watermark'))
            ->values();

        if ($this->option('list')) {
            $targets->each(fn (Asset $asset): mixed => $this->line($this->sourceName($asset)));
            $this->info("{$targets->count()} foto's met watermerk in gebruik.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $targets->each(fn (Asset $asset): mixed => $this->line("zou bijsnijden: {$asset->path()}"));
            $this->info("{$targets->count()} zouden bijgesneden worden.");

            return self::SUCCESS;
        }

        $confirmation = $this->confirmDestructiveRun($targets->count());

        if ($confirmation === null) {
            $this->error('Niet-interactieve modus: gebruik --force om deze onomkeerbare actie zonder bevestiging uit te voeren.');

            return self::FAILURE;
        }

        if (! $confirmation) {
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
     * De lijst gaat als aanvraag naar Winsol, die zijn foto's onder de
     * oorspronkelijke naam kent. Het opgeslagen pad is daarvoor niet altijd
     * bruikbaar: `Réalisation Été - Screens 04.JPG` wordt bij import
     * `realisation-ete--screens-04.jpg`, en dat is aan de andere kant niet
     * meer terug te zoeken. Assets van vóór het `source_filename`-veld hebben
     * hem niet, en vallen terug op het pad.
     */
    private function sourceName(Asset $asset): string
    {
        $source = $asset->get('source_filename');

        return is_string($source) && $source !== '' ? $source : $asset->path();
    }

    /**
     * Dit commando overschrijft foto's op R2 zonder terugvaloptie. Een
     * script of CI-run heeft geen interactieve terminal en moet expliciet
     * --force meegeven; `confirm()` zelf niet aanroepen in die situatie
     * voorkomt dat een niet-interactieve `ConfirmationQuestion` stilzwijgend
     * een default teruggeeft in plaats van de run te weigeren.
     *
     * @return bool|null true om door te gaan, false bij een expliciete
     *                   weigering, null wanneer er niet-interactief geen
     *                   bevestiging gevraagd kon worden.
     */
    private function confirmDestructiveRun(int $count): ?bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return null;
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

        // Een watermerk hoort in de onderste ~15% te zitten (zie
        // MIN_WATERMARK_Y_FRACTION). Ligt de boxrand daarboven, dan komt hij
        // uit onzin (een niet-numeriek veld dat naar 0 casst) of uit een box
        // die niet meer bij dit bestand hoort — geen van beide is een reden
        // om te snijden.
        if ($box['y'] < $height * self::MIN_WATERMARK_Y_FRACTION) {
            $this->warn("Onwaarschijnlijk watermerkvlak (y={$box['y']} op hoogte {$height}), overgeslagen: {$asset->path()}");

            return false;
        }

        // Nooit voorbij de werkelijke hoogte snijden: crop() van een groter
        // gevraagde hoogte dan de afbeelding pad juist bij in plaats van te
        // clippen, en dat plakt een zwarte band onderaan.
        $keepHeight = max(1, min($height, $box['y'] - self::MARGIN));

        // Een box die na deze begrenzing niets meer afsnijdt, hoort niet meer
        // bij dit bestand (bijvoorbeeld nadat een eerdere, mislukte run de
        // foto al bijsneed zonder de vlag om te zetten — zie C2/I3 in de
        // review). Toch schrijven zou de foto zinloos opnieuw comprimeren en
        // de vlag ten onrechte op "schoon" zetten terwijl het watermerk nog
        // zichtbaar is.
        if ($keepHeight >= $height) {
            $this->warn("Verouderd of onbruikbaar watermerkvlak (snede zou niets wijzigen), overgeslagen: {$asset->path()}");

            return false;
        }

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

        return $this->persist($asset, $bytes);
    }

    /**
     * Losstaand van attemptCrop() zodat een fout hier — na het schrijven van
     * de bytes — een eigen boodschap krijgt: de generieke "overgeslagen" van
     * cropAsset() zou anders ten onrechte suggereren dat er niets gebeurd is,
     * terwijl de foto op dat moment al overschreven is.
     */
    private function persist(Asset $asset, string $bytes): bool
    {
        try {
            $asset->disk()->put($asset->path(), $bytes);

            $asset->set('watermark', false);
            $asset->set('watermark_box', '');
            Cache::forget($asset->metaCacheKey());
            $asset->writeMeta($asset->generateMeta());
            $asset->save();
        } catch (Throwable $e) {
            $this->warn("Bestand van {$asset->path()} is al overschreven, maar de vlag kon niet omgezet worden ({$e->getMessage()}). Controleer dit bestand handmatig.");

            return false;
        }

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
