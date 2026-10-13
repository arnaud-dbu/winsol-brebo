<?php

namespace App\Tags;

use App\Services\FormTimer;
use Statamic\Tags\Tags;

/**
 * Een tag en geen variabele uit een view composer: het formulier staat in
 * {{ nocache }}, en alleen een tag draait daar bij elk bezoek opnieuw. Een
 * variabele zou bevriezen op het moment dat de statische cache de pagina
 * bewaarde.
 *
 * Na een foutmelding blijft de eerste laadtijd staan. Anders begint de klok
 * opnieuw, en wie zijn fout meteen verbetert, wordt als te snel geweigerd.
 */
class FormTimerField extends Tags
{
    protected static $handle = 'form_timer';

    public function index(): string
    {
        $previous = old(FormTimer::FIELD);
        $stamp = FormTimer::secondsSinceLoad($previous) === null ? FormTimer::stamp() : $previous;

        return '<input type="hidden" name="'.FormTimer::FIELD.'" value="'.e($stamp).'" />';
    }
}
