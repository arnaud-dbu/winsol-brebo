<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Het prototype van de website-assistent bestaat alleen waar het aan staat.
 * Elders geven de routes een 404, alsof ze er niet zijn.
 */
class LeadbotEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('leadbot.enabled'), 404);

        return $next($request);
    }
}
