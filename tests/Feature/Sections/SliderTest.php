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

    // De dots mogen geen kind van `.slider` zijn: die box klipt met
    // `overflow: hidden` (nodig voor de bleed) en wordt niet hoger dan zijn
    // track, waardoor de dot-rij eronder wegviel. Zie de comments in het
    // partial — dit is precies de regressie die terugkomt zodra iemand de
    // paginering "logisch" weer in de slider zet.
    public function test_pagination_sits_outside_the_clipping_slider(): void
    {
        $html = $this->render(
            '{{ partial:slider per_view="1" pagination="true" bleed="true" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}'
        );

        $document = new \DOMDocument();
        $document->loadHTML('<html><body>' . $html . '</body></html>', LIBXML_NOERROR);
        $xpath = new \DOMXPath($document);

        $this->assertSame(1, $xpath->query("//div[@class='swiper-pagination']")->length);
        $this->assertSame(
            0,
            $xpath->query("//div[contains(@class, 'slider ')]//div[@class='swiper-pagination']")->length,
            'De paginering hoort naast .slider te staan, niet erin — anders klipt overflow: hidden hem weg.'
        );
        $this->assertStringContainsString('slider-frame', $html);
    }

    // `loop` en `centered` zijn opt-in: sliders die ze niet meegeven mogen
    // ze ook niet in de markup krijgen, want sliders.js leest ze puur op
    // aanwezigheid van het data-attribuut (zie createSwiper()).
    public function test_loop_and_centering_are_opt_in(): void
    {
        $html = $this->render('{{ partial:slider per_view="1" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}');

        $this->assertStringNotContainsString('data-slider-loop', $html);
        $this->assertStringNotContainsString('data-slider-centered', $html);

        $html = $this->render('{{ partial:slider per_view="1" loop="true" centered="true" }}<div class="swiper-slide">Een</div>{{ /partial:slider }}');

        $this->assertStringContainsString('data-slider-loop="true"', $html);
        $this->assertStringContainsString('data-slider-centered="true"', $html);
    }
}
