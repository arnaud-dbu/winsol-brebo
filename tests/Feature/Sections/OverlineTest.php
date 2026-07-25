<?php

namespace Tests\Feature\Sections;

class OverlineTest extends SectionTestCase
{
    public function test_renders_label_with_rule(): void
    {
        $html = $this->render('{{ partial:overline label="In de kijker" }}');

        $this->assertStringContainsString('class="overline"', $html);
        $this->assertStringContainsString('In de kijker', $html);
        $this->assertStringContainsString('overline__rule', $html);
    }

    public function test_renders_nothing_without_label(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:overline }}')));
    }

    public function test_adds_inverse_modifier(): void
    {
        $html = $this->render('{{ partial:overline label="Aanbod" is_inverse="true" }}');

        $this->assertStringContainsString('overline--inverse', $html);
    }
}
