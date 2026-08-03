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

    /**
     * De foto loopt schermbreed door tot tegen alle vier de randen: geen
     * afronding, en de sectie zit niet in een `container`.
     */
    public function test_the_image_is_full_bleed_without_rounding(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
            'image' => '/img/pergola.jpg',
        ]);

        $this->assertMatchesRegularExpression('/<section class="([^"]*)" data-header="product"/', $html);
        preg_match('/<section class="([^"]*)" data-header="product"/', $html, $matches);

        $this->assertStringNotContainsString('rounded', $matches[1]);
        $this->assertStringNotContainsString('container', $matches[1]);
    }

    /**
     * De nav zweeft over de foto. De vlag komt uit de layout en niet uit deze
     * partial: `navigation` rendert vóór `template_content` en kan dus niet
     * weten wat er onder hem komt.
     *
     * Het bovenverloop van de header is precies wat die zwevende nav leesbaar
     * houdt, dus als de een verdwijnt moet de ander mee — vandaar dat beide
     * kanten hier in één test staan.
     */
    public function test_the_layout_floats_the_navigation_over_this_header(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        // Deze header zet béide vlaggen aan: hij zweeft én is wit — net als de
        // home-hero, die in dezelfde vlag zit. De range-header zet alleen
        // `floating` aan, dus een test die slechts op 'zweeft' controleert zou
        // het verschil niet zien.
        $this->assertMatchesRegularExpression(
            '/nav_over_photo\s*=[^}]*template == \'products\/show\'/',
            $layout
        );
        $this->assertMatchesRegularExpression(
            '/:floating="nav_floats"/',
            $layout
        );
        $this->assertMatchesRegularExpression(
            '/:inverse="nav_over_photo"/',
            $layout
        );
        $this->assertMatchesRegularExpression(
            '/nav_floats\s*=\s*nav_over_photo\s*\|\|/',
            $layout
        );

        $nav = file_get_contents(resource_path('views/partials/navigation.antlers.html'));

        // Uit de flow en boven de pagina, anders schildert `main` eroverheen.
        $this->assertStringContainsString("floating ? 'absolute inset-x-0 top-0 z-50' : 'relative'", $nav);

        // Alles wat zwart is wordt wit; zwarte tekst op het donkere verloop is
        // onleesbaar. Dat hangt aan `inverse`, niet aan `floating`.
        $this->assertStringContainsString("nav_text_class = inverse ? 'text-white' : 'text-black'", $nav);
        $this->assertStringContainsString("inverse ? 'logo-inverse' : 'logo'", $nav);
        $this->assertStringContainsString(':inverse="inverse"', $nav);

        // En het verloop waar dat op steunt moet blijven bestaan.
        config(['app.debug' => false]);
        $header = $this->render('{{ partial src="headers/product" }}', [
            'title' => 'Pergola SO!',
            'image' => '/img/pergola.jpg',
        ]);
        $this->assertStringContainsString('bg-linear-to-b from-black/70', $header);
    }

    /**
     * De ratio geeft de header zijn hoogte en groeit dus mee met de
     * vensterbreedte; `product-frame` zet daar een plafond op. De cap hoort op
     * de `img` zelf — alleen daar snijdt `object-fit` de overtollige hoogte
     * weg in plaats van het beeld van onderaf af te kappen.
     */
    public function test_the_image_carries_the_height_cap(): void
    {
        $partial = file_get_contents(resource_path('views/partials/headers/product.antlers.html'));

        $this->assertMatchesRegularExpression(
            '/\{\{ img [^}]*class="[^"]*\bproduct-frame\b/',
            $partial,
            'De cap moet op de img-tag staan, niet op een wrapper.'
        );

        $css = file_get_contents(resource_path('css/components/header.css'));

        $this->assertMatchesRegularExpression(
            '/@utility product-frame \{[^}]*max-height:[^}]*object-fit:\s*cover/s',
            $css,
            'product-frame moet zowel een max-height als object-fit: cover zetten.'
        );
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
