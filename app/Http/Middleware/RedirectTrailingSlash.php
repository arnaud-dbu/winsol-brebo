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
        // Eén leidende slash, altijd: `getPathInfo()` laat een pad als
        // `//evil.example.com/` ongemoeid, en `redirect()->to()` leest zo'n
        // string als protocol-relatieve URL en stuurt door naar die host.
        $path = '/'.ltrim($request->getPathInfo(), '/');

        if (! $request->isMethodCacheable() || $path === '/' || ! str_ends_with($path, '/')) {
            return $next($request);
        }

        // `getQueryString()` sorteert parameters alfabetisch en voegt `=` toe
        // aan waardeloze sleutels; dat verandert een campagne-URL die met een
        // slash gedeeld is. De ruwe querystring blijft ongewijzigd.
        $query = $request->server->get('QUERY_STRING');

        return redirect()->to(rtrim($path, '/').($query ? '?'.$query : ''), 301);
    }
}
