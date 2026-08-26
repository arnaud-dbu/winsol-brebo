<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\TestCase;

class MultisiteTest extends TestCase
{
    /**
     * Drie talen sinds 26-08 (werkoverleg Jimmy): Nederlands op de root,
     * Frans onder /fr en Engels onder /en, met Nederlands als default.
     */
    public function test_there_are_three_sites_with_dutch_as_default(): void
    {
        $sites = Site::all();

        $this->assertCount(3, $sites);
        $this->assertSame('nl', Site::default()->handle());
        $this->assertSame('nl_BE', Site::default()->locale());
        $this->assertSame('/fr', Site::get('fr')->url());
        $this->assertSame('fr_BE', Site::get('fr')->locale());
        $this->assertSame('/en', Site::get('en')->url());
        $this->assertSame('en_GB', Site::get('en')->locale());
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
