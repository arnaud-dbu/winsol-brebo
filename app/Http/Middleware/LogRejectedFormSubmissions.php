<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Statamic\Forms\Form;
use Symfony\Component\HttpFoundation\Response;

class LogRejectedFormSubmissions
{
    /**
     * Legt een geweigerde inzending vast: welk formulier, welke velden en of
     * er bestanden bij zaten. Statamic bewaart en verstuurt bij een weigering
     * niets, en een browser vult een bestandsveld nooit opnieuw in. Zonder dit
     * log valt achteraf niet na te gaan of een klant zijn foto kwijtraakte.
     *
     * Alleen de veldnamen, geen waarden: die horen niet in een logbestand.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->routeIs('statamic.forms.submit') || ! $request->hasSession()) {
            return $response;
        }

        $form = $request->route('form');
        $handle = $form instanceof Form ? $form->handle() : (string) $form;
        $errors = $request->session()->get('errors')?->getBag('form.'.$handle);

        if (! $errors || $errors->isEmpty()) {
            return $response;
        }

        Log::warning('Inzending geweigerd bij de validatie.', [
            'formulier' => $handle,
            'velden' => array_values(array_unique(array_map(
                fn (string $key): string => explode('.', $key)[0],
                $errors->keys(),
            ))),
            'bestanden' => count(Arr::flatten($request->allFiles())),
        ]);

        return $response;
    }
}
