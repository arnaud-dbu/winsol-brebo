<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

class PageBuilderPageTest extends TestCase
{
    public function test_showcase_page_renders_every_section(): void
    {
        $response = $this->get('/page-builder');

        $response->assertOk();

        foreach ([
            'text', 'text_image', 'ranges', 'cards', 'articles', 'technical_details',
            'features', 'grid_cta', 'image_gallery', 'cta', 'products',
        ] as $type) {
            $response->assertSee('data-section="'.$type.'"', false);
        }

        // Real entry content, per section — not just the marker attributes.
        $response->assertSee('Automatische lamellen', false); // text_image feature
        $response->assertSee('Waar mogen we mee helpen?', false); // ranges title
        $response->assertSee('Alle mogelijkheden op een rij', false); // cards title
        $response->assertSee('aangebouwde Pergola SO!', false); // text body
        $response->assertSee('Recent geschreven', false); // articles title
        $response->assertSee('Technische specificaties', false); // technical_details title
        $response->assertSee('Waar we voor staan', false); // features title
        $response->assertSee('Wij werken met Winsol', false); // grid_cta item
        $response->assertSee('Kom ons team versterken', false); // grid_cta item
        $response->assertSee('In beeld', false); // image_gallery overline
        $response->assertSee('Lokale verkooppunten, eigen vakmensen', false); // cta title
        $response->assertSee('Vijf soorten terrasoverkapping', false); // products title

        $html = $response->getContent();

        // `ranges` renders one `.range-card` per published range entry:
        // 8 sinds airco gedepubliceerd is (feedback Jimmy, 26-08-2026).
        $this->assertSame(8, substr_count($html, 'range-card'));

        // `cards` (4) is sinds 95da753 de enige sectie die `partial:card`
        // gebruikt: `products` (5) kreeg een eigen `productCard`. De varianten
        // card--horizontal/card--vertical bestaan niet meer; de kaart bepaalt
        // zijn richting nu op containerbreedte.
        $this->assertSame(4, substr_count($html, 'class="card '));
        $this->assertSame(5, substr_count($html, 'from-transparent from-60% to-black'));

        // Every slider-backed section renders one `.swiper-slide` per item:
        // ranges 8 + cards 4 + articles 3 + image_gallery 6. `products` staat
        // sinds 95da753 in een grid en levert er geen enkele meer.
        $this->assertSame(21, substr_count($html, 'swiper-slide'));
    }
}
