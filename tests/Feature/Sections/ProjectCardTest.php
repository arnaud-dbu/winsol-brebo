<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Entry;

class ProjectCardTest extends SectionTestCase
{
    public function test_the_overline_of_a_real_project_is_the_range_and_not_the_title(): void
    {
        // Array-fixtures dekken deze bug niet af: `range` heeft `max_items: 1`
        // en augmenteert naar één `Entry`. Een pair scoopt daar niet in en
        // liet `{{ title }}` terugvallen op de projecttitel. Alleen een echte
        // entry legt dat vast.
        $project = Entry::query()
            ->where('collection', 'projects')
            ->where('slug', 'pergola-so-met-glazen-schuifwanden')
            ->first();

        $html = $this->render('{{ partial src="projectCard" }}', $project->toAugmentedArray());

        $this->assertStringContainsString(
            '<span class="project-card__category">Terrasoverkapping</span>',
            $html
        );
    }

    public function test_renders_a_linked_card_with_the_range_as_category(): void
    {
        $html = $this->render('{{ partial src="projectCard" }}', [
            'title' => 'Zip-screens op nieuwbouwwoning',
            'url' => '/realisaties/zip-screens-op-nieuwbouwwoning',
            'range' => ['title' => 'Zonwering', 'slug' => 'zonwering'],
        ]);

        $this->assertStringContainsString('class="project-card', $html);
        $this->assertStringContainsString('href="/realisaties/zip-screens-op-nieuwbouwwoning"', $html);
        $this->assertStringContainsString('project-card__category', $html);
        $this->assertStringContainsString('Zonwering', $html);
        $this->assertStringContainsString('<h3>Zip-screens op nieuwbouwwoning</h3>', $html);
    }

    public function test_omits_the_category_when_no_range_is_set(): void
    {
        $html = $this->render('{{ partial src="projectCard" }}', [
            'title' => 'Los project',
            'url' => '/realisaties/los-project',
        ]);

        $this->assertStringNotContainsString('project-card__category', $html);
        $this->assertStringContainsString('<h3>Los project</h3>', $html);
    }

    public function test_carries_no_slider_markup_of_its_own(): void
    {
        $html = $this->render('{{ partial src="projectCard" }}', [
            'title' => 'Rolluiken op rijwoning',
            'url' => '/realisaties/rolluiken-op-rijwoning',
        ]);

        $this->assertStringNotContainsString('swiper-slide', $html);
        $this->assertStringNotContainsString('data-slider', $html);
    }
}
