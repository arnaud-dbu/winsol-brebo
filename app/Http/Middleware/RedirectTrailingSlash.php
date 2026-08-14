<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectTrailingSlash
{
    /**
     * Stuurt `/pad/` door naar `/pad`.
     *
     * Laravel negeert een afsluitende slash bij het matchen van routes, dus
     * beide varianten gaven een 200. De canonical wees al naar de versie zonder
     * slash, waardoor het duplicate-contentrisico gedekt was — maar Google
     * crawlt intussen wel twee URL's voor dezelfde pagina.
     *
     * Alleen GET en HEAD: een 301 op een POST laat de browser opnieuw
     * versturen zonder body, waarmee een formulier stilzwijgend leegloopt.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (! $request->isMethodCacheable() || $path === '/' || ! str_ends_with($path, '/')) {
            return $next($request);
        }

        $query = $request->getQueryString();

        return redirect()->to(rtrim($path, '/').($query ? '?'.$query : ''), 301);
    }
}
