<?php

namespace Tests\Feature\Sections;

class QuicklinksSectionTest extends SectionTestCase
{
    public function test_three_columns_when_a_brochure_is_present(): void
    {
        $html = $this->render('{{ partial:quicklinks :brochure="brochure" }}', [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('lg:grid-cols-3', $html);
        $this->assertStringNotContainsString('lg:grid-cols-2', $html);
    }

    public function test_two_columns_when_there_is_no_brochure(): void
    {
        $html = $this->render('{{ partial:quicklinks }}');

        $this->assertStringContainsString('lg:grid-cols-2', $html);
        $this->assertStringNotContainsString('lg:grid-cols-3', $html);
    }
}
