<?php

namespace Tests\Feature;

use Tests\TestCase;

class GitAutomationConfigTest extends TestCase
{
    public function test_git_automation_leaves_customer_data_and_passwords_out_of_the_repo(): void
    {
        $paths = config('statamic.git.paths');

        $this->assertNotContains(storage_path('forms'), $paths);
        $this->assertNotContains(base_path('users'), $paths);
        $this->assertContains(base_path('content'), $paths);
    }

    public function test_git_automation_only_commits_paths_that_git_does_not_ignore(): void
    {
        $ignored = collect(config('statamic.git.paths'))
            ->map(fn (string $path) => str_replace(base_path().'/', '', $path))
            ->filter(fn (string $path) => $this->isIgnoredByGit($path));

        $this->assertEmpty($ignored, 'Genegeerd door .gitignore: '.$ignored->implode(', '));
    }

    private function isIgnoredByGit(string $path): bool
    {
        exec('git -C '.escapeshellarg(base_path()).' check-ignore -q '.escapeshellarg($path), result_code: $exitCode);

        return $exitCode === 0;
    }
}
