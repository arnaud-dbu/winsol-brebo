<?php

namespace Tests\Feature\Sections;

class SliderTest extends SectionTestCase
{
    public function test_renders_swiper_scaffolding_with_options(): void
    {
        $html = $this->render(
            '{{ partial:slider per_view="1.15,md:2,xl:3" space="16,lg:32" from="md" pagination="true" navigation="true" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}'
        );

        $this->assertStringContainsString('data-slider', $html);
        $this->assertStringContainsString('data-slider-per-view="1.15,md:2,xl:3"', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);

        // `space` stuurt twee dingen: Swipers `spaceBetween` én — boven
        // `from`, waar Swiper vernietigd is — de `--slider-gap` die het
        // CSS-grid gebruikt. Zonder dit attribuut valt die tweede helft
        // stil terug op de stylesheet-default (zie sliders.js:syncGap).
        $this->assertStringContainsString('data-slider-space="16,lg:32"', $html);
        $this->assertStringContainsString('class="swiper-wrapper"', $html);
        $this->assertStringContainsString('swiper-slide', $html);
        $this->assertStringContainsString('swiper-pagination', $html);
    }

    public function test_omits_navigation_when_not_requested(): void
    {
        $html = $this->render('{{ partial:slider per_view="1" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}');

        $this->assertStringNotContainsString('swiper-button-next', $html);
        $this->assertStringNotContainsString('swiper-pagination', $html);
    }
}
