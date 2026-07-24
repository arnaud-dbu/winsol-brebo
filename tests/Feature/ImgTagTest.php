<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Antlers;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class ImgTagTest extends TestCase
{
    private function makeImageAsset(
        int $width = 1200,
        int $height = 800,
        array $data = [],
        string $filename = 'photo.jpg',
    ): Asset {
        Storage::fake('r2');

        $container = AssetContainer::make('assets')->disk('r2')->title('Assets');
        $container->save();

        if (str_ends_with($filename, '.svg')) {
            Storage::disk('r2')->put($filename, '<svg xmlns="http://www.w3.org/2000/svg"/>');
        } else {
            $image = imagecreatetruecolor($width, $height);
            ob_start();
            imagejpeg($image);
            Storage::disk('r2')->put($filename, ob_get_clean());
            imagedestroy($image);
        }

        $asset = $container->makeAsset($filename);
        $asset->data($data);
        $asset->save();

        return $asset;
    }

    private function render(string $template, array $context = []): string
    {
        return (string) Antlers::parse($template, $context);
    }

    public function test_renders_picture_with_webp_source_and_jpg_fallback(): void
    {
        $asset = $this->makeImageAsset(1200, 800);

        $html = $this->render('{{ img :src="image" alt="Een foto" }}', ['image' => $asset]);

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('fm=webp', $html);
        $this->assertStringContainsString('fm=jpg', $html);
        $this->assertStringContainsString('alt="Een foto"', $html);
        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('decoding="async"', $html);
    }

    public function test_srcset_is_capped_at_intrinsic_width_and_max_width(): void
    {
        $asset = $this->makeImageAsset(1200, 800);

        $html = $this->render('{{ img :src="image" }}', ['image' => $asset]);

        // Ladder: 320/480/640/960/1280/1680/2560, cap = min(1680, 1200) = 1200.
        $this->assertStringContainsString('w=960', $html);
        $this->assertStringNotContainsString('w=1280', $html);
        $this->assertStringNotContainsString('w=2560', $html);

        // max_width verlaagt de cap verder.
        $html = $this->render('{{ img :src="image" max_width="640" }}', ['image' => $asset]);
        $this->assertStringContainsString('w=640', $html);
        $this->assertStringNotContainsString('w=960', $html);
    }

    public function test_without_ratio_dimensions_follow_intrinsic_aspect(): void
    {
        $asset = $this->makeImageAsset(1200, 800);

        $html = $this->render('{{ img :src="image" }}', ['image' => $asset]);

        // Fallback-breedte = min(1680, 1200) = 1200; hoogte schaalt mee: 800.
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="800"', $html);
        // Zonder ratio geen crop-parameters.
        $this->assertStringNotContainsString('fit=crop', $html);
    }

    public function test_blank_src_renders_nothing(): void
    {
        $html = $this->render('{{ img src="" }}');

        $this->assertSame('', trim($html));
    }

    public function test_ratio_crops_server_side_on_the_focal_point(): void
    {
        $asset = $this->makeImageAsset(1200, 800, ['focus' => '75-25-1']);

        $html = $this->render('{{ img :src="image" ratio="1/1" }}', ['image' => $asset]);

        // 1/1-crop: hoogte = breedte, focal point in de fit-parameter.
        $this->assertStringContainsString('w=480', $html);
        $this->assertStringContainsString('h=480', $html);
        $this->assertStringContainsString('fit=crop-75-25-1', $html);
        // width/height-attributen volgen de ratio (1200 cap, 1/1).
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="1200"', $html);
    }

    public function test_focal_crop_defaults_to_plain_crop_without_focus(): void
    {
        $asset = $this->makeImageAsset(1200, 800);

        $html = $this->render('{{ img :src="image" ratio="16/9" }}', ['image' => $asset]);

        // Geen focus-veld: crop_focal degradeert naar fit=crop (centrum).
        $this->assertStringContainsString('fit=crop', $html);
        $this->assertStringContainsString('h=270', $html); // 480 / (16/9)
    }

    public function test_breakpoint_ratios_render_media_sources_largest_first(): void
    {
        $asset = $this->makeImageAsset(2000, 1500, ['focus' => '50-50-1']);

        $html = $this->render(
            '{{ img :src="image" ratio="1/1" md:ratio="4/3" lg:ratio="16/9" }}',
            ['image' => $asset],
        );

        $this->assertStringContainsString('media="(min-width: 1024px)"', $html);
        $this->assertStringContainsString('media="(min-width: 768px)"', $html);

        // Grootste breakpoint eerst, basis-source (zonder media) als laatste.
        $lg = strpos($html, '(min-width: 1024px)');
        $md = strpos($html, '(min-width: 768px)');
        $base = strpos($html, '<source type="image/webp" width=');
        $this->assertLessThan($md, $lg);
        $this->assertLessThan($base, $md);

        // Per-source width/height: cap = min(1680, 2000) = 1680.
        $this->assertStringContainsString('height="945"', $html);  // 1680 / (16/9)
        $this->assertStringContainsString('height="1260"', $html); // 1680 / (4/3)
        $this->assertStringContainsString('height="1680"', $html); // 1680 / (1/1)
    }

    public function test_priority_renders_eager_with_fetchpriority(): void
    {
        $asset = $this->makeImageAsset();

        $html = $this->render('{{ img :src="image" priority="true" }}', ['image' => $asset]);

        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);
    }

    public function test_fill_adds_cover_classes_and_focal_object_position(): void
    {
        $asset = $this->makeImageAsset(1200, 800, ['focus' => '75-25-1']);

        $html = $this->render(
            '{{ img :src="image" fill="true" class="rounded-lg" }}',
            ['image' => $asset],
        );

        $this->assertStringContainsString(
            'class="absolute inset-0 w-full h-full object-cover rounded-lg"',
            $html,
        );
        $this->assertStringContainsString('style="object-position: 75% 25%"', $html);
    }

    public function test_class_lands_on_the_img_not_the_picture(): void
    {
        $asset = $this->makeImageAsset();

        $html = $this->render('{{ img :src="image" class="rounded-lg" }}', ['image' => $asset]);

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringNotContainsString('<picture class', $html);
        $this->assertStringContainsString('class="rounded-lg"', $html);
    }

    public function test_alt_falls_back_to_asset_alt_field(): void
    {
        $asset = $this->makeImageAsset(1200, 800, ['alt' => 'Alt uit asset']);

        $html = $this->render('{{ img :src="image" }}', ['image' => $asset]);
        $this->assertStringContainsString('alt="Alt uit asset"', $html);

        $html = $this->render('{{ img :src="image" alt="Override" }}', ['image' => $asset]);
        $this->assertStringContainsString('alt="Override"', $html);
    }

    public function test_fill_focus_css_degrades_safely_for_missing_or_garbage_focus(): void
    {
        // Leeg focus-veld: default 50% 50%.
        $asset = $this->makeImageAsset(1200, 800, ['focus' => '']);
        $html = $this->render('{{ img :src="image" fill="true" }}', ['image' => $asset]);
        $this->assertStringContainsString('style="object-position: 50% 50%"', $html);

        // Garbage wordt naar integers gecast: altijd valide CSS, geen injectie.
        $asset = $this->makeImageAsset(1200, 800, ['focus' => '75%-25%</style>']);
        $html = $this->render('{{ img :src="image" fill="true" }}', ['image' => $asset]);
        $this->assertStringContainsString('style="object-position: 75% 25%"', $html);
        $this->assertStringNotContainsString('</style>', $html);
    }

    public function test_svg_renders_as_plain_img_without_glide(): void
    {
        $asset = $this->makeImageAsset(filename: 'logo.svg', data: ['alt' => 'Logo']);

        $html = $this->render('{{ img :src="image" class="h-8" }}', ['image' => $asset]);

        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('fm=webp', $html);
        $this->assertStringContainsString('class="h-8"', $html);
        $this->assertStringContainsString('alt="Logo"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_external_url_renders_passthrough_img(): void
    {
        $html = $this->render('{{ img src="https://example.com/foto.jpg" alt="Extern" }}');

        $this->assertStringContainsString('src="https://example.com/foto.jpg"', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('width=', $html);
    }

    public function test_missing_asset_renders_nothing(): void
    {
        config(['app.debug' => false]); // productiegedrag: stil falen

        $html = $this->render('{{ img src="/assets/bestaat-niet.jpg" }}');

        $this->assertSame('', trim($html));
    }

    public function test_missing_asset_throws_in_debug(): void
    {
        config(['app.debug' => true]);

        try {
            $this->render('{{ img src="/assets/bestaat-niet.jpg" }}');
            $this->fail('Expected an exception for missing asset.');
        } catch (\Throwable $e) {
            $root = $e;
            while ($root->getPrevious()) {
                $root = $root->getPrevious();
            }
            $this->assertInstanceOf(\InvalidArgumentException::class, $root);
        }
    }

    public function test_invalid_ratio_throws_in_debug_and_is_ignored_in_production(): void
    {
        $asset = $this->makeImageAsset();

        config(['app.debug' => true]);
        try {
            $this->render('{{ img :src="image" ratio="16:9" }}', ['image' => $asset]);
            $this->fail('Expected an exception for invalid ratio.');
        } catch (\Throwable $e) {
            $root = $e;
            while ($root->getPrevious()) {
                $root = $root->getPrevious();
            }
            $this->assertInstanceOf(\InvalidArgumentException::class, $root);
        }
    }

    public function test_invalid_ratio_is_ignored_without_debug(): void
    {
        $asset = $this->makeImageAsset(1200, 800);

        config(['app.debug' => false]);
        $html = $this->render('{{ img :src="image" ratio="16:9" }}', ['image' => $asset]);

        $this->assertStringNotContainsString('fit=crop', $html);
        $this->assertStringContainsString('height="800"', $html);
    }

    public function test_empty_string_params_fall_back_to_defaults(): void
    {
        $asset = $this->makeImageAsset(2000, 1500);

        // De oude partial-bug: max_width="" sizes="" mag niets breken.
        $html = $this->render(
            '{{ img :src="image" max_width="" sizes="" }}',
            ['image' => $asset],
        );

        $this->assertStringContainsString('sizes="100vw"', $html);
        $this->assertStringContainsString('w=1680', $html); // default max_width
    }

    public function test_external_src_is_escaped_against_attribute_breakout(): void
    {
        $html = $this->render(
            '{{ img :src="evil" }}',
            ['evil' => 'http://x"><script>alert(1)</script>'],
        );

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('"><', $html);
    }

    public function test_only_real_http_urls_get_external_passthrough(): void
    {
        config(['app.debug' => false]);

        // Geen geldige scheme: geen passthrough, stil niets renderen.
        $this->assertSame('', trim($this->render('{{ img src="httpfoo.nl/x.jpg" }}')));

        // Hoofdletters-scheme is wel een echte externe URL.
        $html = $this->render('{{ img src="HTTPS://example.com/foto.jpg" }}');
        $this->assertStringContainsString('HTTPS://example.com/foto.jpg', $html);
    }

    public function test_zero_ratio_is_rejected_like_invalid_input(): void
    {
        $asset = $this->makeImageAsset(1200, 800);

        // Nul-teller mag nooit door de filter glippen (division by zero).
        config(['app.debug' => false]);
        $html = $this->render('{{ img :src="image" ratio="0/5" md:ratio="0/5" }}', ['image' => $asset]);

        $this->assertStringNotContainsString('media=', $html);
        $this->assertStringContainsString('height="800"', $html); // intrinsieke verhouding

        config(['app.debug' => true]);
        // Antlers wraps tag exceptions in ViewException; the original lives in getPrevious().
        try {
            $this->render('{{ img :src="image" ratio="0/5" }}', ['image' => $asset]);
            $this->fail('Expected an exception for zero-numerator ratio.');
        } catch (\Throwable $e) {
            $root = $e;
            while ($root->getPrevious()) {
                $root = $root->getPrevious();
            }
            $this->assertInstanceOf(\InvalidArgumentException::class, $root);
        }
    }
}
