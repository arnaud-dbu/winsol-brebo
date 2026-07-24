<?php

namespace Tests\Feature;

use Statamic\Facades\Antlers;
use Tests\TestCase;

class FontPreloadingTest extends TestCase
{
    private function renderPartial(array $fonts): string
    {
        return (string) Antlers::parse(
            file_get_contents(resource_path('views/partials/fonts.antlers.html')),
            ['font_faces' => $fonts]
        );
    }

    public function test_empty_config_renders_nothing(): void
    {
        $html = trim($this->renderPartial([]));

        $this->assertStringNotContainsString('rel="preload"', $html);
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertSame('', $html);
    }

    public function test_face_with_preload_renders_link_and_font_face(): void
    {
        $html = $this->renderPartial([
            [
                'family'  => 'Acme',
                'src'     => '/fonts/acme-regular.woff2',
                'weight'  => 400,
                'style'   => 'normal',
                'display' => 'swap',
                'preload' => true,
            ],
        ]);

        $this->assertStringContainsString(
            '<link rel="preload" as="font" type="font/woff2" href="/fonts/acme-regular.woff2" crossorigin>',
            $html
        );
        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString("font-family: 'Acme';", $html);
        $this->assertStringContainsString("src: url('/fonts/acme-regular.woff2') format('woff2');", $html);
        $this->assertStringContainsString('font-weight: 400;', $html);
        $this->assertStringContainsString('font-style: normal;', $html);
        $this->assertStringContainsString('font-display: swap;', $html);
    }

    public function test_face_without_preload_renders_font_face_but_no_link(): void
    {
        $html = $this->renderPartial([
            [
                'family'  => 'Acme',
                'src'     => '/fonts/acme-bold.woff2',
                'weight'  => 700,
                'style'   => 'normal',
                'display' => 'swap',
                'preload' => false,
            ],
        ]);

        $this->assertStringNotContainsString('rel="preload"', $html);
        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('font-weight: 700;', $html);
    }

    public function test_variable_font_weight_range_is_supported(): void
    {
        $html = $this->renderPartial([
            [
                'family'  => 'Acme',
                'src'     => '/fonts/acme-variable.woff2',
                'weight'  => '100 900',
                'style'   => 'normal',
                'display' => 'swap',
                'preload' => true,
            ],
        ]);

        $this->assertStringContainsString('font-weight: 100 900;', $html);
    }

    public function test_config_defaults_to_empty_array(): void
    {
        $this->assertSame([], config('fonts.fonts'));
    }

    public function test_fonts_are_shared_with_views(): void
    {
        $this->assertSame(config('fonts.fonts', []), view()->shared('font_faces'));
    }

    public function test_layout_includes_fonts_partial_before_vite(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $partialPos = strpos($layout, '{{ partial:fonts }}');
        $vitePos = strpos($layout, '{{ vite');

        $this->assertNotFalse($partialPos, 'The layout must include the fonts partial.');
        $this->assertNotFalse($vitePos, 'The layout must include the Vite tag.');
        $this->assertLessThan(
            $vitePos,
            $partialPos,
            'The fonts partial must appear before the Vite tag so preloads are discovered early.'
        );
    }
}
