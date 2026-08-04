<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InspaceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $label = $this->labelFor($request);

        if ($label === null) {
            return response()->json(['message' => 'Ontbrekend of ongeldig token.'], 401);
        }

        $request->attributes->set('inspace_token_label', $label);

        return $next($request);
    }

    /**
     * Losstaand van `handle()` zodat de `inspace`-rate limiter (die vóór deze
     * middleware in de route draait, zie `routes/inspace.php`) hetzelfde
     * tokenlabel kan afleiden zonder van de attribute-side-effect van
     * `handle()` af te hangen — die heeft op dat moment nog niet gedraaid.
     */
    public function labelFor(Request $request): ?string
    {
        $presented = $request->bearerToken();

        return $presented === null ? null : $this->match($presented);
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
