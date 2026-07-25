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
        $html = $this->render('{{ partial:overline label="Test Overline" }}');

        // Overline partial should render successfully (it exists in resources/views/partials/)
        $this->assertStringContainsString('class="overline"', $html);
        $this->assertStringContainsString('Test Overline', $html);
    }
}
