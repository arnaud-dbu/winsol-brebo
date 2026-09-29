<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Forms\Form;
use Symfony\Component\HttpFoundation\Response;

class IgnoreTextInFileFields
{
    /**
     * Een bestandsveld op een formulier aanvaardt alleen uploads. Tekst op die
     * plek laat de validatie van Statamic crashen: `MimesRule` zoekt elke
     * waarde die geen upload is op als asset, en `Asset::find(null)` is een
     * TypeError, dus een 500 in plaats van een foutmelding. Nog zo in
     * Statamic 6.34.
     *
     * De uploads zelf staan in de bestanden van het verzoek, niet in de
     * invoer, en blijven dus staan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $form = $request->route('form');

        if ($request->routeIs('statamic.forms.submit') && $form instanceof Form) {
            $form->blueprint()->fields()->all()
                ->filter(fn ($field): bool => in_array($field->fieldtype()->handle(), ['assets', 'files'], true))
                ->each(function ($field) use ($request): void {
                    $request->request->remove($field->handle());
                    $request->query->remove($field->handle());
                });
        }

        return $next($request);
    }
}
