<?php

namespace Tests\Unit\Inspace;

use App\Inspace\ExternalImageException;
use App\Inspace\ImageResolver;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

class ImageResolverTest extends TestCase
{
    private function anyAssetUrl(): string
    {
        $asset = AssetContainer::findByHandle('assets')->assets()->first();

        $this->assertNotNull($asset, 'De assets-container moet minstens een bestand bevatten.');

        return $asset->url();
    }

    public function test_a_known_asset_url_becomes_an_asset_reference(): void
    {
        $url = $this->anyAssetUrl();

        $out = (new ImageResolver)->toAssetRefs('<p><img src="'.$url.'"></p>');

        $this->assertStringContainsString('src="asset::', $out);
        $this->assertStringNotContainsString($url, $out);
    }

    public function test_an_external_url_is_rejected(): void
    {
        $this->expectException(ExternalImageException::class);

        (new ImageResolver)->toAssetRefs('<p><img src="https://cdn.example.com/foo.jpg"></p>');
    }

    public function test_an_alt_attribute_is_dropped_and_reported(): void
    {
        $resolver = new ImageResolver;

        $out = $resolver->toAssetRefs('<p><img src="'.$this->anyAssetUrl().'" alt="een beschrijving"></p>');

        $this->assertStringNotContainsString('alt=', $out);
        $this->assertSame(
            ['Het alt-attribuut op een <img> is genegeerd. Zet de alt-tekst bij de upload via POST /media.'],
            $resolver->warnings()
        );
    }

    public function test_html_without_images_is_untouched(): void
    {
        $html = '<p>Geen beeld.</p>';

        $this->assertSame($html, (new ImageResolver)->toAssetRefs($html));
    }

    public function test_a_single_quoted_src_is_resolved_too(): void
    {
        $out = (new ImageResolver)->toAssetRefs("<p><img src='".$this->anyAssetUrl()."'></p>");

        $this->assertStringContainsString('src="asset::', $out);
    }

    public function test_an_uppercase_img_tag_is_resolved_too(): void
    {
        $out = (new ImageResolver)->toAssetRefs('<p><IMG SRC="'.$this->anyAssetUrl().'"></p>');

        $this->assertStringContainsString('src="asset::', $out);
    }

    public function test_a_greater_than_sign_inside_another_attribute_does_not_break_the_match(): void
    {
        $out = (new ImageResolver)->toAssetRefs('<p><img title="a &gt; b" src="'.$this->anyAssetUrl().'"></p>');

        $this->assertStringContainsString('src="asset::', $out);
        $this->assertStringNotContainsString('b">', $out, 'De titel mag niet als losse tekst achterblijven.');
    }

    public function test_an_img_looking_string_inside_an_unrelated_attribute_is_left_as_text(): void
    {
        $url = $this->anyAssetUrl();

        $out = (new ImageResolver)->toAssetRefs(
            '<div title="&lt;img src=fake.jpg&gt;"><img src="'.$url.'"></div>'
        );

        $this->assertStringContainsString('src="asset::', $out);
        $this->assertStringContainsString('fake.jpg', $out);
        $this->assertStringNotContainsString('src="fake.jpg"', $out);
    }

    public function test_other_attributes_on_a_real_img_are_dropped(): void
    {
        $out = (new ImageResolver)->toAssetRefs(
            '<p><img class="hero" width="200" src="'.$this->anyAssetUrl().'"></p>'
        );

        $this->assertStringNotContainsString('class=', $out);
        $this->assertStringNotContainsString('width=', $out);
    }
}
