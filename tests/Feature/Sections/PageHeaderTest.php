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

    public function test_renders_a_divider_when_asked(): void
    {
        $html = $this->render('{{ partial src="headers/default" divider="true" }}', [
            'title' => 'Ons aanbod',
        ]);

        $this->assertStringContainsString('page-header__divider', $html);
        $this->assertStringContainsString('lg:block', $html);
    }

    public function test_renders_no_divider_by_default(): void
    {
        $html = $this->render('{{ partial src="headers/default" }}', [
            'title' => 'Ons aanbod',
        ]);

        $this->assertStringNotContainsString('page-header__divider', $html);
        // Verify structural integrity: critical closing structure without extra blank lines
        // This catches whitespace drift that substring checks alone cannot catch
        $this->assertStringContainsString('        </div>' . "\r\n" . '    </div>', $html);
        // Verify no extra blank line (8 spaces) appears between section-header-gap and container closes
        $this->assertStringNotContainsString('        </div>' . "\r\n" . '        ' . "\r\n", $html);
        // Verify key structural elements are present
        $this->assertStringContainsString('<section class="section--default', $html);
        $this->assertStringContainsString('class="container">', $html);
        $this->assertStringContainsString('<h1>Ons aanbod</h1>', $html);
    }
}
