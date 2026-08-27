<?php

namespace Tests\Feature\Sections;

use Tests\TestCase;

class RangeQuicklinksTest extends TestCase
{
    private function jumpBar(string $uri): string
    {
        $html = $this->get($uri)->assertOk()->getContent();

        preg_match('~<section class="range-jump-section".*?</section>~s', $html, $match);

        return $match[0] ?? '';
    }

    /**
     * @return list<string>
     */
    private function labels(string $uri): array
    {
        preg_match_all('~range-jump__label">([^<]+)~', $this->jumpBar($uri), $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * De volgorde komt uit het `order`-veld op de termen, niet uit de
     * alfabetische volgorde van de producten of de groepen.
     */
    public function test_it_groups_the_products_in_the_order_of_the_taxonomy(): void
    {
        $this->assertSame(
            ['Aluminium schrijnwerk', 'PVC schrijnwerk', 'Steellook', 'Accessoires'],
            $this->labels('/aanbod/ramen-en-deuren'),
        );
    }

    public function test_every_product_of_the_range_gets_a_pill_that_links_to_its_page(): void
    {
        $bar = $this->jumpBar('/aanbod/ramen-en-deuren');

        $this->assertSame(9, substr_count($bar, 'range-jump__link'));

        foreach (['aluminium-ramen', 'pvc-schuiframen', 'steellook', 'vliegenramen'] as $slug) {
            $this->assertStringContainsString(
                'href="/aanbod/ramen-en-deuren/'.$slug.'"',
                $bar,
                "Het product {$slug} ontbreekt in de sprongbalk.",
            );
        }
    }

    /**
     * De balk staat vóór de eerste sectie; wie gericht zoekt hoort er niet
     * eerst een halve pagina voor te scrollen. Dat is de hele reden van dit
     * component, dus de positie is de assertie.
     */
    public function test_the_bar_sits_between_the_hero_and_the_first_section(): void
    {
        $html = $this->get('/aanbod/ramen-en-deuren')->assertOk()->getContent();

        $header = strpos($html, 'data-header="range"');
        $bar = strpos($html, 'data-section="range-quicklinks"');
        $firstSection = strpos($html, 'data-section="text_image"');

        $this->assertNotFalse($bar, 'De sprongbalk ontbreekt.');
        $this->assertLessThan($bar, $header, 'De balk hoort onder de hero te staan.');
        $this->assertLessThan($firstSection, $bar, 'De balk hoort boven de eerste sectie te staan.');
    }

    /**
     * Een range waarvan nog geen enkel product een groep draagt, valt terug op
     * de naam van de range zelf. Zonder kop worden het losse woorden op de
     * pagina en is de hiërarchie weg; "Overige" zou dan weer nietszeggend zijn.
     */
    public function test_a_range_without_groups_falls_back_to_the_range_title(): void
    {
        $bar = $this->jumpBar('/aanbod/rolluiken');

        $this->assertSame(['Rolluiken'], $this->labels('/aanbod/rolluiken'));
        $this->assertSame(6, substr_count($bar, 'range-jump__link'));

        // De enige groep verdeelt zich over de volle breedte in plaats van als
        // smalle kolom onder zijn kop te blijven staan.
        $this->assertStringContainsString('range-jump__items--spread', $bar);
    }

    /**
     * `title` binnen de groepslus zou terugvallen op de paginacascade, en dan
     * draagt élke groep de naam van de range — dezelfde lekroute die
     * sectionHeader op /home al trof. Vandaar `group_title`. Op een range mét
     * groepen mag die naam dus nergens als kop opduiken.
     */
    public function test_the_group_headings_do_not_inherit_the_page_title(): void
    {
        $this->assertNotContains('Ramen en deuren', $this->labels('/aanbod/ramen-en-deuren'));
    }

    /**
     * De kolomverdeling is alleen voor het ongegroepeerde geval. Zou ze ook op
     * een range mét groepen aanstaan, dan lopen de producten van verschillende
     * groepen door elkaar over de kolommen heen.
     */
    public function test_the_spread_layout_is_reserved_for_the_ungrouped_case(): void
    {
        $this->assertStringNotContainsString(
            'range-jump__items--spread',
            $this->jumpBar('/aanbod/ramen-en-deuren'),
        );
    }

    public function test_a_range_without_products_renders_no_bar_at_all(): void
    {
        $this->assertSame('', $this->jumpBar('/aanbod/velux'));
    }

    /**
     * De groepslabels komen uit de termlocalisaties en de producturls uit de
     * juiste site; zonder sitefilter zou de balk hier de Nederlandse titels
     * en urls tonen.
     */
    public function test_the_bar_speaks_the_language_of_the_site(): void
    {
        $this->assertSame(
            ['Menuiserie en aluminium', 'Menuiserie en PVC', 'Steellook', 'Accessoires'],
            $this->labels('/fr/aanbod/ramen-en-deuren'),
        );

        $this->assertStringContainsString(
            'href="/fr/aanbod/ramen-en-deuren/pvc-ramen"',
            $this->jumpBar('/fr/aanbod/ramen-en-deuren'),
        );

        // Ook de terugval op de rangetitel spreekt de taal van de site.
        $this->assertSame(['Volets roulants'], $this->labels('/fr/aanbod/rolluiken'));
    }
}
