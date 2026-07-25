<?php

namespace Tests\Feature\Sections;

class PageBuilderTest extends SectionTestCase
{
    public function test_dispatches_every_set_to_its_partial(): void
    {
        $types = [
            'cta', 'cards', 'image_gallery', 'technical_details', 'ranges',
            'text', 'text_image', 'products', 'projects', 'features', 'grid_cta',
        ];

        $html = $this->render('{{ partial:pageBuilder }}', [
            'page_builder' => array_map(fn (string $type) => ['type' => $type], $types),
        ]);

        foreach ($types as $type) {
            $this->assertStringContainsString('data-section="'.$type.'"', $html, "Set {$type} is niet gerenderd");
        }
    }

    public function test_ignores_unknown_types(): void
    {
        $html = $this->render('{{ partial:pageBuilder }}', [
            'page_builder' => [['type' => 'does_not_exist']],
        ]);

        $this->assertStringNotContainsString('data-section', $html);
    }
}
