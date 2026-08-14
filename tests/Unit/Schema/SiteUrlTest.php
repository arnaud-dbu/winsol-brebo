<?php

namespace Tests\Unit\Schema;

use App\Schema\SiteUrl;
use Tests\TestCase;

class SiteUrlTest extends TestCase
{
    public function test_it_builds_an_absolute_url_without_double_slashes(): void
    {
        $this->assertSame(
            rtrim(config('app.url'), '/').'/aanbod/rolluiken',
            SiteUrl::absolute('/aanbod/rolluiken'),
        );
    }

    public function test_a_path_without_a_leading_slash_works_too(): void
    {
        $this->assertSame(
            SiteUrl::absolute('/aanbod'),
            SiteUrl::absolute('aanbod'),
        );
    }

    public function test_the_root_keeps_exactly_one_trailing_slash(): void
    {
        $this->assertSame(rtrim(config('app.url'), '/').'/', SiteUrl::absolute('/'));
    }
}
