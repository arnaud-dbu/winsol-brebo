<?php

namespace Tests\Feature\Sections;

class RenderHarnessTest extends SectionTestCase
{
    public function test_render_helper_parses_antlers_with_context(): void
    {
        $html = $this->render('<p>{{ title }}</p>', ['title' => 'Pergola SO!']);

        $this->assertSame('<p>Pergola SO!</p>', trim($html));
    }
}
