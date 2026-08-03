<?php

namespace Tests\Feature\Sections;

class ArticlesSectionTest extends SectionTestCase
{
    public function test_renders_a_linked_card_per_article(): void
    {
        $html = $this->render('{{ partial src="sections/articles" }}', [
            'title' => 'Recent geschreven',
            'overline' => 'nieuws',
            'articles' => [
                [
                    'title' => 'Een pergola die het hele jaar bruikbaar is',
                    'url' => '/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is',
                    'themes' => ['title' => 'Terrasoverkapping', 'slug' => 'terrasoverkapping'],
                ],
                [
                    'title' => 'Zip-screens kiezen voor een nieuwbouw',
                    'url' => '/nieuws/zip-screens-kiezen-voor-een-nieuwbouw',
                    'themes' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
                ],
            ],
        ]);

        $this->assertStringContainsString('data-section="articles"', $html);
        $this->assertStringContainsString('data-slider-from="xl"', $html);
        $this->assertSame(2, substr_count($html, 'article-card '));
        $this->assertStringContainsString('href="/nieuws/een-pergola-die-het-hele-jaar-bruikbaar-is"', $html);
        $this->assertStringContainsString('Zonwering', $html);
    }

    public function test_it_does_not_fall_back_to_the_page_intro(): void
    {
        // `text` zit niet in deze set. Zonder expliciete lege waarde valt
        // `sectionHeader` terug op de velden van de pagina zelf; op /home zette
        // dat ooit de hero-intro boven de slider.
        $html = $this->render('{{ partial src="sections/articles" }}', [
            'title' => 'Recent geschreven',
            'text' => 'De intro van de pagina zelf',
            'articles' => [],
        ]);

        $this->assertStringNotContainsString('De intro van de pagina zelf', $html);
    }
}
