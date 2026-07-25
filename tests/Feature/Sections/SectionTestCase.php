<?php

namespace Tests\Feature\Sections;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

abstract class SectionTestCase extends TestCase
{
    protected function render(string $template, array $context = []): string
    {
        // Create a temporary view file in the resources/views directory
        // This allows the view resolver to find and render partials
        $id = 'test_' . uniqid();
        $tempFile = resource_path('views/' . $id . '.antlers.html');
        File::put($tempFile, $template);

        try {
            return (string) view($id, $context)->render();
        } finally {
            File::delete($tempFile);
        }
    }
}
