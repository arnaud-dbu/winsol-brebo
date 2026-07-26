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

    public function test_adds_inverse_until_modifier(): void
    {
        $html = $this->render('{{ partial:overline label="Aanbod" inverse_until="lg" }}');

        $this->assertStringContainsString('overline--inverse-until-lg', $html);
    }

    public function test_inverse_until_with_unsupported_value_falls_back_to_dark(): void
    {
        $html = $this->render('{{ partial:overline label="Aanbod" inverse_until="not-a-breakpoint" }}');

        $this->assertStringNotContainsString('overline--inverse', $html);
    }

    public function test_is_inverse_wins_when_both_args_are_passed(): void
    {
        $html = $this->render('{{ partial:overline label="Aanbod" is_inverse="true" inverse_until="lg" }}');

        $this->assertStringContainsString('overline--inverse"', $html);
        $this->assertStringNotContainsString('overline--inverse-until-lg', $html);
    }
}
