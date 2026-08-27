<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Entry;

class ArticleHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/article" }}', [
            'title' => 'Een pergola die het hele jaar bruikbaar is',
            'text' => 'Lamellen, glazen schuifwanden en verwarming maken van een terras een buitenkamer.',
            'image' => '/img/article.jpg',
            'date' => '2026-07-21',
        ]);

        $this->assertStringContainsString('data-header="article"', $html);
        $this->assertStringContainsString('data-header-media', $html);

        // Pin de layering-workaround (zie header.css): zonder deze assertie
        // zou het vervangen van `.header-title`/`.header-intro` door bv.
        // `text-display` alle bestaande tests groen laten terwijl de tekst
        // stilletjes kleiner wordt.
        $this->assertStringContainsString('<h1 class="header-title max-w-[866px]">Een pergola die het hele jaar bruikbaar is</h1>', $html);
        $this->assertStringContainsString('<p class="header-intro max-w-[866px]">Lamellen, glazen schuifwanden en verwarming maken van een terras een buitenkamer.</p>', $html);
    }

    public function test_the_two_chips_of_a_real_article_are_the_theme_and_the_date(): void
    {
        config(['app.debug' => false]);

        // Array-fixtures dekken deze bug niet af: `theme` heeft `max_items: 1`
        // en augmenteert naar één term. Een pair scoopt daar niet in en laat
        // `{{ title }}` terugvallen op de artikeltitel.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('site', 'nl')
            ->where('slug', 'achttien-ramen-en-een-pergola-in-een-werf')
            ->first();

        $html = $this->render('{{ partial src="headers/article" }}', $article->toAugmentedArray());

        $this->assertStringContainsString('<span class="chip chip--dark">Realisaties</span>', $html);

        // Prettier wrapt de `<time>`-tag over meerdere regels (de openings-
        // tag met `datetime` overschrijdt de printWidth), dus geen exacte
        // substring-match.
        $this->assertMatchesRegularExpression(
            '/<time datetime="2026-06-25" class="chip chip--light">\s*25 juni 2026\s*<\/time>/',
            $html
        );
    }

    public function test_the_date_is_rendered_in_dutch(): void
    {
        config(['app.debug' => false]);

        // `isoFormat` en niet `format`: `format` geeft rauwe PHP-opmaak met
        // Engelse maandnamen. De Nederlandse maandnaam komt hier uit
        // `config('app.locale')` (= `nl`); `render()` doet geen HTTP-request,
        // dus Statamics Localize-middleware draait in dit testpad niet.
        $article = Entry::query()
            ->where('collection', 'articles')
            ->where('site', 'nl')
            ->where('slug', 'qubic-slide-haalt-waterdichtheidsklasse-9a')
            ->first();

        $html = $this->render('{{ partial src="headers/article" }}', $article->toAugmentedArray());

        $this->assertStringContainsString('21 mei 2026', $html);
        $this->assertStringNotContainsString('May', $html);
    }

    public function test_omits_the_theme_chip_entirely_without_a_theme(): void
    {
        config(['app.debug' => false]);

        // Er mag geen lege chip achterblijven.
        $html = $this->render('{{ partial src="headers/article" }}', [
            'title' => 'Los artikel',
            'date' => '2026-07-21',
        ]);

        $this->assertStringNotContainsString('chip--dark', $html);
        $this->assertStringContainsString('chip--light', $html);
    }
}
