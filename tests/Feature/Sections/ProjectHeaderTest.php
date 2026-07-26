<?php

namespace Tests\Feature\Sections;

class ProjectHeaderTest extends SectionTestCase
{
    public function test_renders_title_text_and_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/project" }}', [
            'title' => 'Pergola SO! met glazen schuifwanden',
            'text' => 'Een zuidgericht terras dat het hele jaar bruikbaar werd.',
            'image' => '/img/project.jpg',
        ]);

        $this->assertStringContainsString('data-header="project"', $html);
        $this->assertStringContainsString('Pergola SO! met glazen schuifwanden', $html);
        $this->assertStringContainsString('Een zuidgericht terras dat het hele jaar bruikbaar werd.', $html);
        $this->assertStringContainsString('data-header-media', $html);

        // Pin de layering-workaround (zie header.css): zonder deze assertie
        // zou het vervangen van `.header-title`/`.header-intro` door bv.
        // `text-display` alle bestaande tests groen laten terwijl de tekst
        // stilletjes kleiner wordt.
        $this->assertStringContainsString('<h1 class="header-title max-w-[866px]">Pergola SO! met glazen schuifwanden</h1>', $html);
        $this->assertStringContainsString('<p class="header-intro max-w-[866px]">Een zuidgericht terras dat het hele jaar bruikbaar werd.</p>', $html);
    }

    public function test_renders_the_range_name_as_eyebrow(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/project" }}', [
            'title' => 'Pergola SO! met glazen schuifwanden',
            'range' => [
                ['title' => 'Terrasoverkapping', 'url' => '/aanbod/terrasoverkapping'],
            ],
        ]);

        $this->assertStringContainsString('header-eyebrow', $html);
        $this->assertStringContainsString('Terrasoverkapping', $html);
    }

    public function test_omits_the_eyebrow_entirely_without_a_range(): void
    {
        config(['app.debug' => false]);

        // Dit is de tak die vandaag draait: `projects` linkt nog naar
        // `product`, en de `range`-relatie komt uit een parallelle branch.
        // Er mag geen leeg label-element achterblijven.
        $html = $this->render('{{ partial src="headers/project" }}', [
            'title' => 'Pergola SO! met glazen schuifwanden',
        ]);

        $this->assertStringNotContainsString('header-eyebrow', $html);
    }

    public function test_projects_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/projects/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/projects.yaml'));
        $this->assertStringContainsString('template: projects/show', $yaml);

        $view = file_get_contents(resource_path('views/projects/show.antlers.html'));
        $this->assertStringContainsString('headers/project', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
