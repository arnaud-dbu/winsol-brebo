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

    /**
     * Een bestand in de webroot. Dat hangt niet onder het taalprefix van de
     * huidige site, zoals `/fr`.
     */
    public static function file(string $path): string
    {
        return rtrim(Site::default()->absoluteUrl(), '/').'/'.ltrim($path, '/');
    }
}
