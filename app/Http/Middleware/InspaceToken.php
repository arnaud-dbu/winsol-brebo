<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InspaceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $presented = $request->bearerToken();

        if ($presented === null || ($label = $this->match($presented)) === null) {
            return response()->json(['message' => 'Ontbrekend of ongeldig token.'], 401);
        }

        $request->attributes->set('inspace_token_label', $label);

        return $next($request);
    }

    /**
     * hash_equals en geen ===: die laatste breekt af op het eerste
     * afwijkende byte en lekt daarmee de lengte van het geldige prefix.
     */
    private function match(string $presented): ?string
    {
        $hash = hash('sha256', $presented);

        foreach (config('inspace.tokens', []) as $label => $known) {
            if (hash_equals((string) $known, $hash)) {
                return (string) $label;
            }
        }

        return null;
    }
}
