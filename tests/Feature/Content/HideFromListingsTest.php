<?php

namespace Tests\Feature\Content;

use App\Services\PublishedArticles;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

/**
 * Het AEO-bureau zet pagina's neer die bestaan om gelezen te worden door
 * zoekmachines en AI-assistenten, niet om naartoe te navigeren. `hide_from_listings`
 * laat ze weg uit de sprongbalk en de overzichten, maar bewust niet uit de
 * sitemap en niet van hun eigen adres: dan zou de reden waarvoor ze bestaan
 * wegvallen.
 */
class HideFromListingsTest extends TestCase
{
    use CreatesTemporaryContent;

    private const RAMEN_EN_DEUREN = '8c2e41a0-0002-4a1b-9c7d-3e5f6a7b8c02';

    private function product(string $slug, string $titel, bool $verborgen): void
    {
        $this->temporaryEntry('products', $slug, [
            'title' => $titel,
            'range' => [self::RAMEN_EN_DEUREN],
            'hide_from_listings' => $verborgen,
        ]);
    }

    public function test_a_hidden_product_stays_out_of_the_range_jump_bar(): void
    {
        $this->product('zichtbaar-testproduct', 'Zichtbaar testproduct', false);
        $this->product('verborgen-testproduct', 'Verborgen testproduct', true);

        $response = $this->get('/aanbod/ramen-en-deuren');

        $response->assertOk();
        $response->assertSee('Zichtbaar testproduct', false);
        $response->assertDontSee('Verborgen testproduct', false);
    }

    /**
     * De pagina zelf blijft bestaan. Zou ze een 404 geven, dan is ze voor een
     * crawler even onbereikbaar als voor een bezoeker en heeft verbergen geen
     * enkele zin meer.
     */
    public function test_a_hidden_product_is_still_reachable_at_its_own_url(): void
    {
        $this->product('verborgen-testproduct', 'Verborgen testproduct', true);

        $response = $this->get('/aanbod/ramen-en-deuren/verborgen-testproduct');

        $response->assertOk();
        $response->assertSee('Verborgen testproduct', false);
    }

    public function test_a_hidden_product_stays_in_the_sitemap(): void
    {
        $this->product('verborgen-testproduct', 'Verborgen testproduct', true);

        $response = $this->get('/sitemap_products.xml');

        $response->assertOk();
        $response->assertSee('/aanbod/ramen-en-deuren/verborgen-testproduct', false);

        // De sitemap wordt gecachet in de gedeelde testing-store, en het
        // tijdelijke product verdwijnt pas na deze test. Zonder deze regel
        // erft SitemapTest een sitemap met een adres dat niet meer bestaat en
        // valt daar om op een fout die hier is gemaakt.
        Cache::forget('sitemap.collection.products');
    }

    public function test_a_hidden_article_stays_out_of_the_news_overview(): void
    {
        $this->temporaryEntry('articles', 'zichtbaar-testartikel', [
            'title' => 'Zichtbaar testartikel',
        ], '2026-09-01');

        $this->temporaryEntry('articles', 'verborgen-testartikel', [
            'title' => 'Verborgen testartikel',
            'hide_from_listings' => true,
        ], '2026-09-02');

        $response = $this->get('/nieuws');

        $response->assertOk();
        $response->assertSee('Zichtbaar testartikel', false);
        $response->assertDontSee('Verborgen testartikel', false);
    }

    public function test_a_hidden_article_is_still_reachable_at_its_own_url(): void
    {
        $this->temporaryEntry('articles', 'verborgen-testartikel', [
            'title' => 'Verborgen testartikel',
            'hide_from_listings' => true,
        ], '2026-09-02');

        $this->get('/nieuws/verborgen-testartikel')->assertOk();
    }

    /**
     * De tegenovergestelde fout van hierboven: filtert PublishedArticles te
     * gretig, dan verdwijnt Nieuws uit de navigatie terwijl er wel degelijk
     * leesbare artikels staan, en wijst er niets meer naar het overzicht.
     */
    public function test_a_hidden_article_does_not_hide_nieuws_from_the_navigation(): void
    {
        $this->temporaryEntry('articles', 'zichtbaar-testartikel', [
            'title' => 'Zichtbaar testartikel',
        ], '2026-09-01');

        $this->temporaryEntry('articles', 'verborgen-testartikel', [
            'title' => 'Verborgen testartikel',
            'hide_from_listings' => true,
        ], '2026-09-02');

        $this->assertTrue(app(PublishedArticles::class)->exist());
    }
}
