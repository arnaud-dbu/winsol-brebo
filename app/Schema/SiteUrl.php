<?php

namespace App\Schema;

use Statamic\Facades\Site;

class SiteUrl
{
    public static function absolute(string $path): string
    {
        $base = rtrim(Site::current()->absoluteUrl(), '/');
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? $base.'/' : $base.$path;
    }
}
