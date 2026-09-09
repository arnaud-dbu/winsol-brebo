<?php

namespace App\Http\Middleware;

use App\Services\LegacyRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet een adres van de oude site (`winsoldilbeek.be`) in één permanente hop om
 * naar zijn tegenhanger op de nieuwe site. `App\Services\LegacyRedirect` zoekt
 * de bestemming op; hier staat wat dat voor het verzoek betekent.
 *
 * Deze middleware draait vóór `RedirectTrailingSlash`: elk adres van de oude
 * site eindigt op een slash, dus anders werd het twee sprongen in plaats van
 * één. Alleen GET en HEAD, om dezelfde reden als daar: een 301 op een POST
 * laat de browser opnieuw versturen zonder body.
 */
class RedirectLegacyUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodCacheable()) {
            return $next($request);
        }

        $path = LegacyRedirect::normalise($request->getPathInfo());
        $legacy = in_array(
            strtolower($request->getHost()),
            config('legacy_redirects.hosts'),
            true
        );

        // Klimmen alleen op de oude host. Anders zou elk onbekend pad op de
        // nieuwe site naar een bovenliggende pagina omleiden in plaats van een
        // 404 te geven.
        $destination = LegacyRedirect::destinationFor($path, climb: $legacy);

        // Op de oude host gaat ook een adres dat nergens op uitkomt mee naar
        // de nieuwe site, met zijn eigen pad. Daar geeft het de nette 404 van
        // de nieuwe site: de bezoeker weet dan waar hij is en kan verder.
        // Doorlaten zou het oude domein de site laten serveren.
        $destination ??= $legacy ? $path : null;

        // Een regel die naar zichzelf wijst bestaat om op de oude host van
        // host te wisselen. Op de nieuwe host zou hij een lus opleveren.
        if ($destination === null || (! $legacy && $destination === $path)) {
            return $next($request);
        }

        // De ruwe querystring, niet `getQueryString()`: die sorteert en
        // normaliseert, en verandert daarmee een gedeelde campagnelink.
        $query = $request->server->get('QUERY_STRING');
        $host = $legacy ? config('legacy_redirects.target') : '';

        return redirect()->to($host.$destination.($query ? '?'.$query : ''), 301);
    }
}
