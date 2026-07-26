<?php

namespace Tests\Feature\Sections;

class ProductHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_both_overlays(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
            'text' => 'De pergola met draaibare lamellen.',
            'image' => '/img/pergola.jpg',
        ]);

        $this->assertStringContainsString('data-header="product"', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('De pergola met draaibare lamellen.', $html);
        $this->assertStringContainsString('data-header-media', $html);

        // Twee donkere lagen (Figma 301:3495): een radiale verdonkering over
        // het hele vlak plus het bovenverloop. Zonder beide is de witte tekst
        // op een licht beeld onleesbaar.
        $this->assertStringContainsString('bg-radial', $html);
        $this->assertStringContainsString('bg-linear-to-b', $html);
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);
    }

    public function test_products_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/products/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/products.yaml'));
        $this->assertStringContainsString('template: products/show', $yaml);

        $view = file_get_contents(resource_path('views/products/show.antlers.html'));
        $this->assertStringContainsString('headers/product', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
