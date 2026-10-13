<?php

namespace Tests\Concerns;

use App\Services\FormTimer;

/**
 * Een test die rechtstreeks naar een formulier post, heeft de pagina nooit
 * geladen. Zonder deze laadtijd weigert App\Listeners\RejectSpam hem stil.
 */
trait PassesFormTimer
{
    /**
     * @return array<string, string>
     */
    protected function formTimer(): array
    {
        return [FormTimer::FIELD => FormTimer::stamp(now()->subMinute())];
    }
}
