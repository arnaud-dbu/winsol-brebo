<?php

namespace App\Tags;

use App\Imaging\AlphaBounds;
use App\Imaging\AssetAlphaBounds;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Image;
use Statamic\Fields\Value;
use Statamic\Tags\Tags;

class Img extends Tags
{
    protected static $handle = 'img';

    private const WIDTHS = [320, 480, 640, 960, 1280, 1680, 2560];

    private const DEFAULT_MAX_WIDTH = 1680;

    private const DEFAULT_QUALITY = 85;

    private const BREAKPOINTS = [
        'sm' => 640,
        'md' => 768,
        'lg' => 1024,
        'xl' => 1280,
        '2xl' => 1536,
    ];

    public function index(): string
    {
        $src = $this->params->get('src');
        $asset = $this->resolveAsset($src);

        if (! $asset) {
            // Alleen absolute http(s)-URL's; protocol-relative (//cdn) bewust niet.
            if (is_string($src) && preg_match('#^https?://#i', $src)) {
                return $this->passthrough($src);
            }

            throw_if(
                config('app.debug') && ! blank($src),
                new \InvalidArgumentException('{{ img }}: asset niet gevonden voor src "'.(is_string($src) ? $src : gettype($src)).'".')
            );

            return '';
        }

        if (in_array(strtolower($asset->extension()), ['svg', 'gif'], true)) {
            return $this->passthrough((string) $asset->url(), $asset->get('alt') ?? '');
        }

        $maxWidth = (int) $this->params->get('max_width') ?: self::DEFAULT_MAX_WIDTH;
        $quality = (int) $this->params->get('quality') ?: self::DEFAULT_QUALITY;
        $sizes = trim((string) $this->params->get('sizes')) ?: '100vw';
        $alt = trim((string) ($this->params->get('alt') ?? $asset->get('alt') ?? ''));

        $ratios = $this->ratios();
        $trim = $this->trimBounds($asset);

        // Na een trim slaan alle maten op het bijgesneden beeld: Glide zet de
        // trim vóór Size, dus `w` levert die breedte ook echt op. Zou hier het
        // canvas blijven staan, dan vroegen we breedtes op die de bron niet
        // heeft en blies Glide het product op.
        $sourceWidth = $trim?->width ?? $asset->width();
        $sourceHeight = $trim?->height ?? $asset->height();

        $fallbackWidth = min($maxWidth, $sourceWidth);
        $widths = $this->srcsetWidths($sourceWidth, $maxWidth);
        $fallbackHeight = $ratios['base']
            ? (int) round($fallbackWidth / $ratios['base'])
            : (int) round($fallbackWidth * $sourceHeight / $sourceWidth);

        $sources = collect($ratios['breakpoints'])
            ->map(fn (array $bp) => [
                'media' => "(min-width: {$bp['min']}px)",
                'srcset' => $this->srcset($asset, $widths, $bp['ratio'], 'webp', $quality, $trim),
                'width' => $fallbackWidth,
                'height' => (int) round($fallbackWidth / $bp['ratio']),
            ])
            ->push([
                'media' => null,
                'srcset' => $this->srcset($asset, $widths, $ratios['base'], 'webp', $quality, $trim),
                'width' => $fallbackWidth,
                'height' => $fallbackHeight,
            ])
            ->all();

        $fill = $this->params->bool('fill', false);
        $class = trim(
            ($fill ? 'absolute inset-0 w-full h-full object-cover ' : '')
            .trim((string) $this->params->get('class'))
        );

        return view('components.img', [
            'passthrough' => false,
            'sources' => $sources,
            'fallback_src' => $this->glideUrl($asset, $fallbackWidth, $ratios['base'], 'jpg', $quality, $trim),
            'fallback_srcset' => $this->srcset($asset, $widths, $ratios['base'], 'jpg', $quality, $trim),
            'width' => $fallbackWidth,
            'height' => $fallbackHeight,
            'sizes' => $sizes,
            'alt' => $alt,
            'class' => $class,
            'priority' => $this->params->bool('priority', false),
            'fill' => $fill,
            'focus_css' => $this->focusCss($asset),
            'trim_width' => $trim ? round($trim->widthRatio() * 100, 3).'%' : null,
            'data_speed' => $this->params->get('data_speed'),
        ])->render();
    }

    /**
     * De omhullende van het zichtbare deel, als `trim` aanstaat.
     *
     * Geeft `null` zodra er niets te winnen valt — geen png, geen alpha, of een
     * product dat het canvas al vult — zodat de `trim`-parameter dan ook niet
     * in de Glide-url belandt en er geen tweede cachevariant ontstaat.
     */
    private function trimBounds(Asset $asset): ?AlphaBounds
    {
        if (! $this->params->bool('trim', false)) {
            return null;
        }

        $bounds = app(AssetAlphaBounds::class)->for($asset);

        return $bounds?->coversWholeCanvas() ? null : $bounds;
    }

    private function passthrough(string $url, string $assetAlt = ''): string
    {
        return view('components.img', [
            'passthrough' => true,
            'src' => $url,
            'alt' => trim((string) ($this->params->get('alt') ?? $assetAlt)),
            'class' => trim((string) $this->params->get('class')),
            'priority' => $this->params->bool('priority', false),
            'data_speed' => $this->params->get('data_speed'),
        ])->render();
    }

    private function focusCss(Asset $asset): string
    {
        // ?: i.p.v. ?? zodat ook een leeg focus-veld de default pakt; int-cast
        // houdt de inline style altijd valide en injectievrij.
        [$x, $y] = array_map('intval', array_pad(explode('-', $asset->get('focus') ?: '50-50-1'), 2, 50));

        return "{$x}% {$y}%";
    }

    public static function parseRatio(?string $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('#^(\d+(?:\.\d+)?)/(\d+(?:\.\d+)?)$#', $value, $m)
            || (float) $m[1] === 0.0
            || (float) $m[2] === 0.0) {
            throw_if(
                config('app.debug'),
                new \InvalidArgumentException("Ongeldige ratio \"{$value}\" voor {{ img }}; verwacht bv. \"16/9\".")
            );

            return null;
        }

        return (float) $m[1] / (float) $m[2];
    }

    /**
     * @return array{base: ?float, breakpoints: array<int, array{min: int, ratio: float}>}
     */
    private function ratios(): array
    {
        $breakpoints = collect(self::BREAKPOINTS)
            ->map(fn (int $min, string $prefix) => [
                'min' => $min,
                // Statamic's Parameters::get() vertaalt ':' naar '.', dus prefixed keys raw uitlezen.
                'ratio' => self::parseRatio($this->params->all()["{$prefix}:ratio"] ?? null),
            ])
            ->filter(fn (array $bp) => $bp['ratio'] !== null)
            ->sortByDesc('min')
            ->values()
            ->all();

        return [
            'base' => self::parseRatio($this->params->get('ratio')),
            'breakpoints' => $breakpoints,
        ];
    }

    private function resolveAsset(mixed $src): ?Asset
    {
        if ($src instanceof Value) {
            $src = $src->value();
        }

        if ($src instanceof Asset) {
            return $src;
        }

        if (is_string($src) && $src !== '') {
            return AssetFacade::findByUrl($src);
        }

        return null;
    }

    private function srcsetWidths(int $assetWidth, int $maxWidth): array
    {
        $cap = min($assetWidth, $maxWidth);
        $widths = array_values(array_filter(self::WIDTHS, fn (int $w) => $w <= $cap));

        return $widths ?: [$cap];
    }

    private function srcset(Asset $asset, array $widths, ?float $ratio, string $format, int $quality, ?AlphaBounds $trim): string
    {
        return collect($widths)
            ->map(fn (int $w) => $this->glideUrl($asset, $w, $ratio, $format, $quality, $trim)." {$w}w")
            ->implode(', ');
    }

    private function glideUrl(Asset $asset, int $width, ?float $ratio, string $format, int $quality, ?AlphaBounds $trim): string
    {
        $manipulator = Image::manipulate($asset)
            ->width($width)
            ->format($format)
            ->quality($quality);

        if ($ratio) {
            $manipulator
                ->height((int) round($width / $ratio))
                ->fit('crop_focal');
        }

        if ($trim) {
            // Via params() en niet setParam(): die laatste toetst aan Glide's
            // eigen parameterlijst en kent onze TrimTransparent niet.
            $manipulator->params($manipulator->getParams() + ['trim' => 1]);
        }

        return $manipulator->build();
    }
}
