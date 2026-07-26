<?php

namespace Tests\Feature\Sections;

class ProjectRangesTagTest extends SectionTestCase
{
    public function test_it_yields_only_ranges_that_have_projects(): void
    {
        $html = $this->render('{{ project_ranges }}[{{ slug }}]{{ /project_ranges }}');

        // De vier ranges waaraan de zes projecten hangen.
        $this->assertStringContainsString('[ramen-en-deuren]', $html);
        $this->assertStringContainsString('[rolluiken]', $html);
        $this->assertStringContainsString('[pergolas]', $html);
        $this->assertStringContainsString('[zonwering]', $html);

        // De vijf ranges zonder projecten.
        $this->assertStringNotContainsString('[airco]', $html);
        $this->assertStringNotContainsString('[velux]', $html);
        $this->assertStringNotContainsString('[stalen-binnendeuren]', $html);
        $this->assertStringNotContainsString('[garagepoorten]', $html);
        $this->assertStringNotContainsString('[somfy-smart-home]', $html);
    }

    public function test_it_deduplicates_and_sorts_by_title(): void
    {
        $html = $this->render('{{ project_ranges }}[{{ title }}]{{ /project_ranges }}');

        // Drie projecten hangen aan `pergolas`; die range hoort er één keer te staan.
        $this->assertSame(1, substr_count($html, "[Terrasoverkappingen & pergola's]"));

        $this->assertSame(
            '[Ramen en deuren][Rolluiken][Terrasoverkappingen & pergola\'s][Zonwering]',
            trim($html)
        );
    }
}
