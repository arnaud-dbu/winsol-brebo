<?php

namespace Tests\Feature\Sections;

class EmbedSectionTest extends SectionTestCase
{
    public function test_it_renders_an_iframe_with_the_given_url_and_height(): void
    {
        $html = $this->render('{{ partial:sections/embed }}', [
            'title' => 'Simuleer je lening',
            'url' => 'https://www.kbc.be/co2-p-wgt/visual-widget/resources/0001/nl/',
            'height' => 900,
        ]);

        $this->assertStringContainsString('data-section="embed"', $html);
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('https://www.kbc.be/co2-p-wgt/visual-widget/resources/0001/nl/', $html);
        $this->assertStringContainsString('height="900"', $html);
        $this->assertStringContainsString('Simuleer je lening', $html);
    }

    public function test_it_renders_nothing_without_a_url(): void
    {
        $html = $this->render('{{ partial:sections/embed }}', ['title' => 'Leeg']);

        $this->assertSame('', trim($html));
    }
}
