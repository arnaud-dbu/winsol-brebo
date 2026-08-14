<?php

namespace Tests\Unit\Schema;

use App\Schema\SiteUrl;
use Statamic\Facades\Site;
use Tests\TestCase;

class SiteUrlTest extends TestCase
{
    /**
     * Toetst tegen `Site::current()->absoluteUrl()`, de bron die de
     * implementatie zelf leest. `config('app.url')` klopt daar nu toevallig
     * mee omdat `resources/sites.yaml` `url: /` heeft — verandert dat, dan
     * moet deze test dat volgen in plaats van eigenwijs `config('app.url')`
     * te blijven controleren.
     */
    public function test_it_builds_an_absolute_url_without_double_slashes(): void
    {
        $this->assertSame(
            rtrim(Site::current()->absoluteUrl(), '/').'/aanbod/rolluiken',
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
        $this->assertSame(rtrim(Site::current()->absoluteUrl(), '/').'/', SiteUrl::absolute('/'));
    }
}
