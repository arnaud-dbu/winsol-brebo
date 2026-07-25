<?php

namespace Tests\Feature\Sections;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

abstract class SectionTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register the isolated test views directory with the view factory
        // This directory is git-ignored and isolated from the tracked resources/views tree
        $testViewsPath = storage_path('framework/testing/views');
        @mkdir($testViewsPath, 0755, true);
        View::addLocation($testViewsPath);
    }

    protected function render(string $template, array $context = []): string
    {
        // Create a temporary view file in an isolated directory
        // This keeps the tracked resources/views tree clean and prevents file watcher churn
        $testViewsPath = storage_path('framework/testing/views');

        $id = 'test_' . uniqid();
        $tempFile = $testViewsPath . '/' . $id . '.antlers.html';
        File::put($tempFile, $template);

        try {
            return (string) view($id, $context)->render();
        } finally {
            File::delete($tempFile);
        }
    }
}
