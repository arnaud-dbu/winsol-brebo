<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoIndexHeader
{
    /**
     * Houdt een niet-indexeerbare omgeving uit de zoekresultaten.
     *
     * Een header en niet alleen `robots.txt`, om twee redenen. Het standaard
     * nginx-recept voor Laravel bevat `location = /robots.txt` zonder
     * `try_files`, waardoor dat pad een 404 teruggeeft en een zoekmachine het
     * leest als "er is geen robots.txt". En `Disallow:` houdt crawlen tegen
     * maar niet het indexeren van een URL die elders opduikt; `noindex` doet
     * dat wel.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('app.indexable')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
