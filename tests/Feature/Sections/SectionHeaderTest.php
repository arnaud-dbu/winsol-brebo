<?php

namespace Tests\Feature\Sections;

class SectionHeaderTest extends SectionTestCase
{
    private array $context = [
        'overline' => 'In de kijker',
        'title' => 'Pergola SO!',
        'text' => '<p>De pergola met draaibare lamellen.</p>',
    ];

    public function test_renders_overline_title_and_text(): void
    {
        $html = $this->render('{{ partial:sectionHeader }}', $this->context);

        $this->assertStringContainsString('class="overline"', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('draaibare lamellen', $html);
    }

    public function test_defaults_to_left_aligned(): void
    {
        $html = $this->render('{{ partial:sectionHeader }}', $this->context);

        $this->assertStringNotContainsString('section-header--centered', $html);
    }

    public function test_centered_variant(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_centered="true" }}', $this->context);

        $this->assertStringContainsString('section-header--centered', $html);
    }

    public function test_inverse_variant(): void
    {
        $html = $this->render('{{ partial:sectionHeader is_inverse="true" }}', $this->context);

        $this->assertStringContainsString('section-header--inverse', $html);
        $this->assertStringContainsString('overline--inverse', $html);
    }

    public function test_heading_tag_is_configurable(): void
    {
        $html = $this->render('{{ partial:sectionHeader tag="h3" }}', $this->context);

        $this->assertStringContainsString('<h3', $html);
        $this->assertStringNotContainsString('<h2', $html);
    }

    public function test_renders_nothing_without_title_or_text(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:sectionHeader }}')));
    }
}
