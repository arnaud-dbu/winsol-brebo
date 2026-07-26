<?php

namespace Tests\Feature\Sections;

class PageHeaderTest extends SectionTestCase
{
    public function test_renders_title_and_intro(): void
    {
        $html = $this->render('{{ partial src="headers/default" }}', [
            'title' => 'Pagebuilder',
            'text' => 'Samen je huis klaarmaken voor de toekomst.',
        ]);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Pagebuilder', $html);
        $this->assertStringContainsString('Samen je huis klaarmaken', $html);
    }
}
