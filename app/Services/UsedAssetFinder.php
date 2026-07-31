<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\AssetContainer;

class UsedAssetFinder
{
    private const ASSET_EXTENSION_PATTERN = '/\.(jpe?g|png|webp|pdf)$/i';

    /**
     * Kleine-lettervorm van elk containerpad => het werkelijke pad, per
     * containerhandle. Lui opgebouwd: alleen een pad dat niet letterlijk
     * bestaat rechtvaardigt het uitlezen van de hele containerinhoud.
     *
     * @var array<string, array<string, string>>
     */
    private array $lowercaseIndex = [];

    public function __construct(private readonly ContentValueScanner $scanner) {}

    /**
     * Alle assetpaden waar minstens een contentveld naar wijst.
     *
     * De waarden komen uit de ruwe data in plaats van uit de augmented velden:
     * een assetsveld bewaart daar simpelweg zijn pad, en zo hoeft er niet per
     * blueprint uitgezocht te worden welk veld een asset is.
     *
     * @return Collection<int, string>
     */
    public function paths(): Collection
    {
        return collect($this->scanner->values())
            ->pluck('value')
            ->filter(fn (string $value): bool => (bool) preg_match(self::ASSET_EXTENSION_PATTERN, $value))
            ->unique()
            ->values();
    }

    /**
     * De assets achter die paden, voor zover ze in de container bestaan.
     *
     * @return Collection<int, Asset>
     */
    public function assets(string $container = 'assets'): Collection
    {
        $container = AssetContainer::find($container);

        if ($container === null) {
            return collect();
        }

        return $this->paths()
            ->map(fn (string $path): ?Asset => $this->resolve($container, $path))
            ->filter()
            ->values();
    }

    /**
     * Valt terug op een hoofdletterongevoelige match: `winsol:import-images`
     * saneert de bestandsnaam naar kleine letters, dus een handgeschreven
     * `pergolas/IMG_0001.JPG` in een entry wijst wel degelijk naar de asset
     * die daar als `pergolas/img_0001.jpg` ligt. Zonder deze terugval valt die
     * foto stil uit elke sanering en uit elke poort.
     */
    private function resolve(AssetContainerContract $container, string $path): ?Asset
    {
        if ($asset = $container->asset($path)) {
            return $asset;
        }

        $actual = $this->lowercaseIndex($container)[strtolower($path)] ?? null;

        return $actual === null ? null : $container->asset($actual);
    }

    /**
     * @return array<string, string>
     */
    private function lowercaseIndex(AssetContainerContract $container): array
    {
        return $this->lowercaseIndex[$container->handle()] ??= collect($container->files())
            ->mapWithKeys(fn (string $file): array => [strtolower($file) => $file])
            ->all();
    }
}
