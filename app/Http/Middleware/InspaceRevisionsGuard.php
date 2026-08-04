<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\Collection;
use Symfony\Component\HttpFoundation\Response;

class InspaceRevisionsGuard
{
    /**
     * Met revisions aan maakt save() een working copy in plaats van te
     * publiceren. Nova zou dan denken dat het publiceerde terwijl er niets
     * live staat — stil falen op precies de plek waar het het duurst is.
     * Alleen schrijfacties lopen hierdoorheen: een GET raakt save() nooit,
     * dus die blijft ongemoeid.
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (array_keys(config('inspace.writable', [])) as $handle) {
            if (Collection::findByHandle($handle)?->revisionsEnabled()) {
                return response()->json([
                    'message' => "De collectie `{$handle}` heeft revisions aan staan. Zet `revisions: false` in de collectie, of haal hem uit config/inspace.php.",
                    'collection' => $handle,
                ], 503);
            }
        }

        return $next($request);
    }
}
