<?php

namespace Tests\Feature\Sections;

use Statamic\Facades\Antlers;
use Tests\TestCase;

abstract class SectionTestCase extends TestCase
{
    protected function render(string $template, array $context = []): string
    {
        return (string) Antlers::parse($template, $context);
    }
}
