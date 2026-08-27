<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Entry;

class ArticleCardTest extends SectionTestCase
{
    public function test_the_overline_of_a_real_article_is_the_theme_and_not_the_title(): void
    {
        // Array-fixtures dekken deze bug niet af: `themes` heeft `max_items: 1`
        // en augmenteert naar één term. Een pair scoopt daar niet in en laat
        // `{{ title }}` terugvallen op de artikeltitel. Alleen een echte entry
        // legt dat vast.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('site', 'nl')
            ->where('slug', 'achttien-ramen-en-een-pergola-in-een-werf')
            ->first();

        $html = $this->render('{{ partial src="articleCard" }}', $article->toAugmentedArray());

        $this->assertStringContainsString(
            '<span class="article-card__category">Realisaties</span>',
            $html
        );
    }

    public function test_the_height_cap_crops_the_image_instead_of_stretching_it(): void
    {
        // `max-h-100` knijpt alleen de hoogte af; de breedte blijft de volle
        // kaart. Zonder object-fit rekt de browser het vierkante beeld dan
        // uit. Deze combinatie is dus geen stijlkwestie maar de fix zelf.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('site', 'nl')
            ->where('slug', 'showroom-aartselaar-is-opnieuw-open')
            ->first();

        $html = $this->render('{{ partial src="articleCard" }}', $article->toAugmentedArray());

        $this->assertMatchesRegularExpression(
            '/<img[^>]*class="[^"]*max-h-100[^"]*object-cover[^"]*"/',
            $html,
            'Een hoogtecap zonder object-cover vervormt het beeld'
        );
    }

    public function test_renders_a_linked_card_with_the_theme_as_category(): void
    {
        $html = $this->render('{{ partial src="articleCard" }}', [
            'title' => 'Zip-screens kiezen voor een nieuwbouw',
            'url' => '/nieuws/zip-screens-kiezen-voor-een-nieuwbouw',
            'themes' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
        ]);

        $this->assertStringContainsString('class="article-card', $html);
        $this->assertStringContainsString('href="/nieuws/zip-screens-kiezen-voor-een-nieuwbouw"', $html);
        $this->assertStringContainsString('article-card__category', $html);
        $this->assertStringContainsString('Zonwering', $html);
        $this->assertStringContainsString('<h3>Zip-screens kiezen voor een nieuwbouw</h3>', $html);
    }

    public function test_omits_the_category_when_no_theme_is_set(): void
    {
        $html = $this->render('{{ partial src="articleCard" }}', [
            'title' => 'Los artikel',
            'url' => '/nieuws/los-artikel',
        ]);

        $this->assertStringNotContainsString('article-card__category', $html);
        $this->assertStringContainsString('<h3>Los artikel</h3>', $html);
    }

    public function test_carries_no_slider_markup_of_its_own(): void
    {
        $html = $this->render('{{ partial src="articleCard" }}', [
            'title' => 'Wanneer vervang je je rolluiken',
            'url' => '/nieuws/wanneer-vervang-je-je-rolluiken',
        ]);

        $this->assertStringNotContainsString('swiper-slide', $html);
        $this->assertStringNotContainsString('data-slider', $html);
    }
}
