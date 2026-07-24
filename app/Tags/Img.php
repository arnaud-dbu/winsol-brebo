<?php

namespace App\Tags;

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

        $fallbackWidth = min($maxWidth, $asset->width());
        $widths = $this->srcsetWidths($asset->width(), $maxWidth);
        $fallbackHeight = $ratios['base']
            ? (int) round($fallbackWidth / $ratios['base'])
            : (int) round($fallbackWidth * $asset->height() / $asset->width());

        $sources = collect($ratios['breakpoints'])
            ->map(fn (array $bp) => [
                'media' => "(min-width: {$bp['min']}px)",
                'srcset' => $this->srcset($asset, $widths, $bp['ratio'], 'webp', $quality),
                'width' => $fallbackWidth,
                'height' => (int) round($fallbackWidth / $bp['ratio']),
            ])
            ->push([
                'media' => null,
                'srcset' => $this->srcset($asset, $widths, $ratios['base'], 'webp', $quality),
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
            'fallback_src' => $this->glideUrl($asset, $fallbackWidth, $ratios['base'], 'jpg', $quality),
            'fallback_srcset' => $this->srcset($asset, $widths, $ratios['base'], 'jpg', $quality),
            'width' => $fallbackWidth,
            'height' => $fallbackHeight,
            'sizes' => $sizes,
            'alt' => $alt,
            'class' => $class,
            'priority' => $this->params->bool('priority', false),
            'fill' => $fill,
            'focus_css' => $this->focusCss($asset),
            'data_speed' => $this->params->get('data_speed'),
        ])->render();
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

    private function srcset(Asset $asset, array $widths, ?float $ratio, string $format, int $quality): string
    {
        return collect($widths)
            ->map(fn (int $w) => $this->glideUrl($asset, $w, $ratio, $format, $quality)." {$w}w")
            ->implode(', ');
    }

    private function glideUrl(Asset $asset, int $width, ?float $ratio, string $format, int $quality): string
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

        return $manipulator->build();
    }
}
