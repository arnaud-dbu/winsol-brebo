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
        // op een licht beeld onleesbaar. Deze moeten distincte `absolute inset-0`
        // divs zijn en vóór het tekstblok staan, zodat de tekst erbovenop ligt.
        $radial = strpos($html, 'bg-radial');
        $linear = strpos($html, 'bg-linear-to-b');
        $textBlock = strpos($html, 'class="container absolute');

        $this->assertNotFalse($radial, 'Radiale verdonkering (bg-radial) niet gevonden.');
        $this->assertNotFalse($linear, 'Bovenverloop (bg-linear-to-b) niet gevonden.');
        $this->assertNotFalse($textBlock, 'Tekstblok niet gevonden.');

        // Beide overlay-divs moeten vóór het tekstblok staan in DOM-volgorde.
        $this->assertLessThan($textBlock, $radial, 'Radiale laag moet vóór tekstblok staan.');
        $this->assertLessThan($textBlock, $linear, 'Bovenverloop moet vóór tekstblok staan.');

        // Beide overlay-divs moeten elk hun eigen `absolute inset-0` div zijn,
        // niet in dezelfde element. De regex verifiëert dat elke class gekoppeld
        // is aan een div met `absolute inset-0`.
        $this->assertMatchesRegularExpression(
            '/class="absolute inset-0 bg-radial/',
            $html,
            'Radiale verdonkering moet in eigen div met absolute inset-0 staan.'
        );
        $this->assertMatchesRegularExpression(
            '/class="absolute inset-0 bg-linear-to-b/',
            $html,
            'Bovenverloop moet in eigen div met absolute inset-0 staan.'
        );

        // Pin de layering-workaround (zie header.css): zonder deze assertie
        // zou het vervangen van `.header-title`/`.header-intro` door bv.
        // `text-display` alle bestaande tests groen laten terwijl de tekst
        // stilletjes kleiner wordt.
        $this->assertStringContainsString('<h1 class="header-title">Pergola SO!</h1>', $html);
        $this->assertStringContainsString('<p class="header-intro">De pergola met draaibare lamellen.</p>', $html);
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);
    }

    public function test_renders_the_heading_in_flow_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
        ]);

        // Zonder beeld is er niets in de flow om de sectie hoogte te geven.
        // Als het tekstblok dan nog `absolute inset-0` was, zou de sectie
        // 0px hoog blijven en de H1 onzichtbaar zijn.
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringNotContainsString('absolute inset-0', $html);
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
