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
     * Dit is de énige schakelaar die op de vlag reageert: `robots.txt` is een
     * statisch bestand in `public/` en staat op elke omgeving open. Een header
     * hoort hier ook thuis, want `Disallow:` houdt crawlen tegen maar niet het
     * indexeren van een URL die elders opduikt; `noindex` doet dat wel.
     *
     * Gevolg voor staging: crawlen mag daar, indexeren niet. Wil je crawlen ook
     * dichtzetten, dan moet dat per omgeving in `public/robots.txt` en niet
     * hier.
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
