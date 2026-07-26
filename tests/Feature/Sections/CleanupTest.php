<?php

namespace Tests\Feature\Sections;

class CleanupTest extends SectionTestCase
{
    public function test_unused_starter_kit_files_are_gone(): void
    {
        foreach ([
            'views/partials/sections/reviews.antlers.html',
            'views/partials/sections/list.antlers.html',
            'views/partials/sections/images.antlers.html',
            'views/partials/sections/cases.antlers.html',
            'views/partials/sections/callToAction.antlers.html',
            'views/partials/sections/collapses.antlers.html',
            'views/partials/blockHeader.antlers.html',
            'fieldsets/collapses.yaml',
            'fieldsets/image_gallery.yaml',
            'fieldsets/steps.yaml',
            'fieldsets/stats.yaml',
            'css/components/collapse.css',
            'js/components/collapses.js',
        ] as $path) {
            $this->assertFileDoesNotExist(resource_path($path));
        }
    }
}
