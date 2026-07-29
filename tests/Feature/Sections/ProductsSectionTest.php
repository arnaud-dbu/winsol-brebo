<?php

namespace Tests\Feature\Sections;

class ProductsSectionTest extends SectionTestCase
{
    private function render_products(int $count): string
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $products = [];
        for ($i = 1; $i <= $count; $i++) {
            $products[] = ['title' => "Product {$i}", 'url' => "/aanbod/product-{$i}", 'image' => 'pergola.jpg'];
        }

        return $this->render('{{ partial src="sections/products" }}', [
            'overline' => 'producten',
            'title' => 'Zes soorten terrasoverkapping',
            'products' => $products,
        ]);
    }

    /** Klassen van de `<ul>` die de grid draagt. */
    private function gridClasses(string $html): string
    {
        $this->assertMatchesRegularExpression('/<ul\s+class="([^"]*)"/', $html);
        preg_match('/<ul\s+class="([^"]*)"/', $html, $matches);

        return $matches[1];
    }

    public function test_renders_a_card_per_product_in_a_grid(): void
    {
        $html = $this->render_products(5);

        $this->assertStringContainsString('data-section="products"', $html);
        $this->assertStringContainsString('lg:items-center lg:text-center', $html);
        $this->assertSame(5, substr_count($html, '<li>'));
        $this->assertSame(5, substr_count($html, 'href="/aanbod/product-'));

        // Grid, geen slider meer.
        $this->assertMatchesRegularExpression('/\b\w+:grid-cols-6\b/', $this->gridClasses($html));
        $this->assertStringNotContainsString('swiper', $html);
        $this->assertStringNotContainsString('data-slider-from', $html);
    }

    /**
     * De vulling van de laatste rij zit in de klassen op de `<ul>` en niet in
     * per-kaart berekende spans. `nth-child(3n+1)` is de kaart die een rij
     * opent: zijn er dan nog twee te gaan, dan halveren die de rij (span 3);
     * is het meteen de laatste, dan neemt die alle zes. Vijf producten geven
     * zo de 3 + 2 uit Figma 449:1799.
     *
     * Dat de kaarten zelf géén kolomklasse dragen is de kern van deze test.
     * De spans stonden eerder per kaart in de template, berekend uit het
     * aantal producten — met even veel varianten als er aantallen zijn, en
     * dus even veel klassen die Tailwind los moest terugvinden. Nu staat er
     * één vaste set op de `<ul>`, gelijk voor elk aantal.
     */
    public function test_the_last_row_fill_lives_on_the_list_not_on_the_cards(): void
    {
        foreach ([1, 2, 4, 5, 7] as $count) {
            $html = $this->render_products($count);
            $grid = $this->gridClasses($html);

            // Op welk breakpoint de zes kolommen ingaan is een ontwerpkeuze en
            // staat hier niet vast; dát alle vier de regels er zijn én op
            // hetzelfde breakpoint staan wel — een half stel breekt de vulling.
            $this->assertMatchesRegularExpression('/\b(\w+):grid-cols-6\b/', $grid, "aantal: {$count}");
            preg_match('/\b(\w+):grid-cols-6\b/', $grid, $m);
            $bp = $m[1];

            foreach ([
                "{$bp}:[&>*]:col-span-2",
                "{$bp}:[&>*:nth-child(3n+1):nth-last-child(2)]:col-span-3",
                "{$bp}:[&>*:nth-child(3n+1):nth-last-child(2)~*]:col-span-3",
                "{$bp}:[&>*:nth-child(3n+1):last-child]:col-span-6",
                // Twee kolommen daaronder: een oneven aantal laat de laatste
                // kaart anders alleen op een halve rij achter.
                "sm:max-{$bp}:[&>*:nth-child(2n+1):last-child]:col-span-2",
            ] as $class) {
                $this->assertStringContainsString($class, $grid, "aantal: {$count}");
            }

            // De kaarten zelf dragen geen kolomklasse.
            preg_match_all('/<li[^>]*>/', $html, $items);
            foreach ($items[0] as $item) {
                $this->assertStringNotContainsString('col-span', $item, "aantal: {$count}");
            }

            $this->assertMatchesRegularExpression('/<li>\s*<a/', $html, "aantal: {$count}");
        }
    }

    /**
     * De kaart is zelf de link, dus de pijl mag geen tweede `<a>` of `<button>`
     * zijn — dat zou een link in een link opleveren. Zelfde afspraak als
     * `locationCard`.
     */
    public function test_the_card_is_the_link_and_the_arrow_is_decorative(): void
    {
        $html = $this->render_products(1);

        $this->assertStringContainsString('href="/aanbod/product-1"', $html);

        // Eén enkele `<a>`, en geen knop erin. Op `<a` met willekeurige
        // witruimte erna, want de formatter breekt lange tags over regels.
        $this->assertSame(1, preg_match_all('/<a[\s>]/', $html));
        $this->assertStringNotContainsString('<button', $html);

        // De pijl zit in een accent cirkel en is decoratief.
        $this->assertStringContainsString('-rotate-45', $html);
        $this->assertMatchesRegularExpression('/<span\s+aria-hidden="true"\s+class="[^"]*bg-accent/', $html);
    }

    /**
     * Het verloop staat vóór de tekst in de markup en de tekstlaag staat op
     * `relative`. Allebei nodig: de foto staat door `fill="true"` absoluut, en
     * zonder die twee valt de titel ofwel onder het verloop ofwel onder de foto.
     */
    public function test_the_scrim_precedes_the_text_which_sits_above_it(): void
    {
        $html = $this->render_products(1);

        $scrimPos = strpos($html, 'from-transparent from-60% to-black');
        $textPos = strpos($html, '<h3');

        $this->assertNotFalse($scrimPos, 'geen verloop gerenderd');
        $this->assertNotFalse($textPos);
        $this->assertGreaterThan($scrimPos, $textPos);

        // De `<div>` die de titel draagt, moet `relative` zijn.
        $this->assertMatchesRegularExpression('/<div class="relative[^"]*">\s*<div class="flex items-center/', $html);
    }

    public function test_renders_nothing_but_the_header_without_products(): void
    {
        $html = $this->render('{{ partial src="sections/products" }}', ['title' => 'Zes soorten terrasoverkapping']);

        $this->assertStringContainsString('Zes soorten terrasoverkapping', $html);
        $this->assertStringNotContainsString('<ul', $html);
        $this->assertStringNotContainsString('<li>', $html);
    }
}
