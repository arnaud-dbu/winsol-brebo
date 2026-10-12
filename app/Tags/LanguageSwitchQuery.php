<?php

namespace App\Tags;

use Illuminate\Support\Arr;
use Statamic\Tags\Tags;

/**
 * De querystring die de taalknop meeneemt naar de andere taal.
 *
 *     /brochures?brochure=…&utm_source=x  →  ?brochure=…
 *
 * Alleen parameters die we zelf kennen reizen mee. Een `utm_`-parameter in
 * onze eigen links telt de taalwissel als een nieuw bezoek uit die campagne,
 * en elke andere querystring wordt in de statische cache een extra pagina.
 */
class LanguageSwitchQuery extends Tags
{
    private const CARRIED_PARAMETERS = ['brochure', 'product'];

    public function index(): string
    {
        $query = http_build_query(Arr::only(request()->query(), self::CARRIED_PARAMETERS));

        return $query === '' ? '' : '?'.$query;
    }
}
