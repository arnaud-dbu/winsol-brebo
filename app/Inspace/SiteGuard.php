<?php

namespace App\Inspace;

use Illuminate\Validation\ValidationException;
use Statamic\Facades\Site;

class SiteGuard
{
    /**
     * @throws ValidationException
     */
    public function resolve(?string $requested): string
    {
        $default = Site::default()->handle();

        if ($requested === null || $requested === $default) {
            return $default;
        }

        if (Site::get($requested) === null) {
            throw ValidationException::withMessages([
                'site' => 'Onbekende site. Beschikbaar: '.Site::all()->map->handle()->implode(', ').'.',
            ]);
        }

        return $requested;
    }
}
