<?php

namespace Tests\Feature\Sections;

class FooterTest extends SectionTestCase
{
    public function test_renders_three_link_columns_and_a_colophon(): void
    {
        $html = $this->render('{{ partial:footer }}');

        $this->assertSame(3, substr_count($html, 'footer__column'));
        $this->assertStringContainsString('footer__colophon', $html);
        $this->assertStringContainsString('BY BREBO', $html);
    }
}
