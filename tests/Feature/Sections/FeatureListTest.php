<?php

namespace Tests\Feature\Sections;

class FeatureListTest extends SectionTestCase
{
    public function test_renders_one_item_per_feature(): void
    {
        $html = $this->render('{{ partial:featureList :items="features" }}', [
            'features' => [
                ['label' => 'Automatische lamellen'],
                ['label' => 'Bediening via app'],
                ['label' => 'Belgisch maatwerk'],
            ],
        ]);

        $this->assertStringContainsString('feature-list', $html);
        $this->assertSame(3, substr_count($html, '<li'));
        $this->assertStringContainsString('Belgisch maatwerk', $html);
    }

    public function test_renders_nothing_without_items(): void
    {
        $this->assertSame('', trim($this->render('{{ partial:featureList }}')));
    }
}
