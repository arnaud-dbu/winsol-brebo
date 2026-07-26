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
            'text', 'text_image', 'ranges', 'cards', 'projects', 'technical_details',
            'features', 'grid_cta', 'image_gallery', 'cta', 'products',
        ] as $type) {
            $response->assertSee('data-section="'.$type.'"', false);
        }

        // Real entry content, per section — not just the marker attributes.
        $response->assertSee('Automatische lamellen', false); // text_image feature
        $response->assertSee('Waar mogen we mee helpen?', false); // ranges title
        $response->assertSee('Alle mogelijkheden op een rij', false); // cards title
        $response->assertSee('aangebouwde Pergola SO!', false); // text body
        $response->assertSee('Recent gerealiseerd', false); // projects title
        $response->assertSee('Technische specificaties', false); // technical_details title
        $response->assertSee('Waar we voor staan', false); // features title
        $response->assertSee('Wij werken met Winsol', false); // grid_cta item
        $response->assertSee('Kom ons team versterken', false); // grid_cta item
        $response->assertSee('In beeld', false); // image_gallery overline
        $response->assertSee('Lokale verkooppunten, eigen vakmensen', false); // cta title
        $response->assertSee('Zes soorten terrasoverkapping', false); // products title

        $html = $response->getContent();

        // `ranges` renders one `.range-card` per seeded range entry (9).
        $this->assertSame(9, substr_count($html, 'range-card'));

        // `cards` (4, horizontal layout) + `products` (6, vertical layout)
        // are the only sections that consume `partial:card`.
        $this->assertSame(4, substr_count($html, 'card--horizontal'));
        $this->assertSame(6, substr_count($html, 'card--vertical'));

        // Every slider-backed section (ranges 9, cards 4, projects 3,
        // image_gallery 3, products 6) renders one `.swiper-slide` per item.
        $this->assertSame(25, substr_count($html, 'swiper-slide'));
    }
}
