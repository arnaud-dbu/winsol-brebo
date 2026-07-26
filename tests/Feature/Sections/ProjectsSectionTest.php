<?php

namespace Tests\Feature\Sections;

class ProjectsSectionTest extends SectionTestCase
{
    public function test_renders_a_linked_card_per_project(): void
    {
        $html = $this->render('{{ partial src="sections/projects" }}', [
            'title' => 'Recent gerealiseerd',
            'overline' => 'realisaties',
            'projects' => [
                ['title' => 'Pergola SO! met glazen schuifwanden', 'url' => '/realisaties/pergola-so'],
                ['title' => 'Zip-screens op nieuwbouwwoning', 'url' => '/realisaties/zip-screens'],
            ],
        ]);

        $this->assertStringContainsString('data-section="projects"', $html);
        $this->assertStringContainsString('data-slider-from="md"', $html);
        $this->assertSame(2, substr_count($html, 'project-card'));
        $this->assertStringContainsString('href="/realisaties/pergola-so"', $html);
    }
}
