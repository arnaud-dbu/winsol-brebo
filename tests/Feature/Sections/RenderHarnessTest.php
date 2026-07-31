<?php

namespace Tests\Feature\Sections;

class RenderHarnessTest extends SectionTestCase
{
    public function test_render_helper_parses_antlers_with_context(): void
    {
        $html = $this->render('<p>{{ title }}</p>', ['title' => 'Pergola SO!']);

        $this->assertSame('<p>Pergola SO!</p>', trim($html));
    }

    public function test_render_helper_resolves_partials(): void
    {
        // Regression test: ensure partial resolution works in the render harness
        // This guards against future changes that break partial support in test templates
        $html = $this->render('{{ partial:sectionHeader }}', [
            'overline' => 'Test Overline',
            'title' => 'Test Title',
        ]);

        // sectionHeader partial should render successfully (it exists in resources/views/partials/)
        $this->assertMatchesRegularExpression(self::OVERLINE_CLASS, $html);
        $this->assertStringContainsString('Test Overline', $html);
        $this->assertStringContainsString('Test Title', $html);
    }
}
