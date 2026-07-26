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
    }
}
