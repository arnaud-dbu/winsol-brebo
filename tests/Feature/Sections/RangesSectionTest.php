<?php

namespace Tests\Feature\Sections;

class RangesSectionTest extends SectionTestCase
{
    public function test_renders_a_slide_per_range_and_is_always_a_slider(): void
    {
        $html = $this->render('{{ partial src="sections/ranges" }}', [
            'title' => 'Waar mogen we mee helpen?',
            'overline' => 'Aanbod',
            'range' => [
                ['title' => 'Ramen en deuren', 'short_description' => 'Volledig op maat.', 'url' => '/aanbod/ramen-en-deuren'],
                ['title' => 'Rolluiken', 'short_description' => 'Comfort en veiligheid.', 'url' => '/aanbod/rolluiken'],
            ],
        ]);

        $this->assertStringContainsString('data-section="ranges"', $html);
        $this->assertStringContainsString('data-slider', $html);
        $this->assertStringNotContainsString('data-slider-from', $html);
        $this->assertSame(2, substr_count($html, 'swiper-slide'));
        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
    }
}
