<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Het demo-ERP toont namen, telefoonnummers en foto's die bezoekers van de
 * testsite achterlaten. Het scherm opent daarom alleen met de sleutel uit
 * LEADBOT_DEMO_KEY. Zonder sleutel is het enkel lokaal bereikbaar.
 */
class LeadbotDemoKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('leadbot.demo_key');
        $given = (string) ($request->query('key') ?? $request->header('X-Demo-Key'));

        $allowed = $expected === ''
            ? app()->environment('local')
            : hash_equals($expected, $given);

        abort_unless($allowed, 404);

        return $next($request);
    }
}
