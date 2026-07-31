<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\TestCase;

class MultisiteTest extends TestCase
{
    public function test_there_is_exactly_one_site_and_it_is_dutch(): void
    {
        $sites = Site::all();

        $this->assertCount(1, $sites);
        $this->assertSame('nl', Site::default()->handle());
        $this->assertSame('nl_BE', Site::default()->locale());
    }

    public function test_existing_entries_still_resolve_after_the_conversion(): void
    {
        $home = Entry::query()->where('collection', 'pages')->where('slug', 'home')->first();

        $this->assertNotNull($home, 'De home-entry is de conversie niet overleefd');
        $this->assertSame('nl', $home->locale());
    }

    public function test_content_files_moved_into_the_site_folder(): void
    {
        $this->assertFileExists(base_path('content/collections/pages/nl/home.md'));
    }
}
