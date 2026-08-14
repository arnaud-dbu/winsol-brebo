<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Zet de headers die de browser vertellen wat hij namens deze site niet
     * mag doen.
     *
     * Ze staan hier en niet in de nginx-config omdat die buiten de repo valt:
     * op deze manier verhuizen ze mee naar elke omgeving en zijn ze te toetsen.
     * `X-Frame-Options` en `X-Content-Type-Options` komen wél van de server en
     * blijven daar; de verouderde `X-XSS-Protection` die de server erbij stuurt
     * is alleen daar weg te halen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // RFC 6797: over een onbeveiligde verbinding hoort HSTS niet verstuurd
        // te worden. Lokaal draait de site op http.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
