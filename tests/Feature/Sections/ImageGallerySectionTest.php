<?php

namespace Tests\Feature\Sections;

class ImageGallerySectionTest extends SectionTestCase
{
    public function test_renders_a_bleeding_slider_with_pagination(): void
    {
        // The fixture urls below don't resolve to real assets (no
        // AssetContainer/Storage fixture is set up here — this test only
        // asserts the slider/pagination markup, not `{{ img }}`'s own
        // asset-resolution behaviour, which is covered by ImgTagTest).
        // `{{ img :src="url" }}` re-resolves that url via
        // AssetFacade::findByUrl() (see app/Tags/Img.php), which throws in
        // debug mode for a url that matches no real asset — see
        // ImgTagTest::test_missing_asset_throws_in_debug(). Production
        // behaviour is unaffected: real `images` field items carry real
        // urls that do resolve. Disabling debug here reproduces the
        // documented production fallback (silent empty render, see
        // ImgTagTest::test_missing_asset_renders_nothing()) instead of
        // exercising the debug-only developer guard.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/imageGallery" }}', [
            'title' => 'Dit project van dichtbij',
            'overline' => 'In beeld',
            'images' => [
                ['url' => '/img/een.jpg'],
                ['url' => '/img/twee.jpg'],
            ],
        ]);

        $this->assertStringContainsString('data-section="image_gallery"', $html);
        $this->assertStringContainsString('slider-bleed', $html);
        $this->assertStringContainsString('swiper-pagination', $html);
        $this->assertStringNotContainsString('data-slider-from', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
    }

    public function test_no_longer_branches_on_image_width(): void
    {
        $partial = file_get_contents(resource_path('views/partials/sections/imageGallery.antlers.html'));

        $this->assertStringNotContainsString('image_width', $partial);
    }
}
