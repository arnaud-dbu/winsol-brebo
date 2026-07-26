<?php

namespace Tests\Feature\Sections;

class RangeCardTest extends SectionTestCase
{
    public function test_renders_a_full_card_link_with_its_copy(): void
    {
        $html = $this->render('{{ partial src="rangeCard" }}', [
            'title' => 'Rolluiken',
            'short_description' => 'Verduistering, isolatie en inbraakwering.',
            'url' => '/aanbod/rolluiken',
        ]);

        $this->assertStringContainsString('class="range-card', $html);
        $this->assertStringContainsString('href="/aanbod/rolluiken"', $html);
        $this->assertStringContainsString('<h3>Rolluiken</h3>', $html);
        $this->assertStringContainsString('Verduistering, isolatie en inbraakwering.', $html);
    }

    public function test_omits_the_description_paragraph_when_there_is_none(): void
    {
        $html = $this->render('{{ partial src="rangeCard" }}', [
            'title' => 'Airco',
            'url' => '/aanbod/airco',
        ]);

        $this->assertStringContainsString('<h3>Airco</h3>', $html);
        $this->assertStringNotContainsString('<p>', $html);
    }

    public function test_carries_no_slider_markup_of_its_own(): void
    {
        $html = $this->render('{{ partial src="rangeCard" }}', [
            'title' => 'Zonwering',
            'url' => '/aanbod/zonwering',
        ]);

        $this->assertStringNotContainsString('swiper-slide', $html);
        $this->assertStringNotContainsString('data-slider', $html);
    }
}
