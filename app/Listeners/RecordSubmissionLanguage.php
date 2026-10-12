<?php

namespace App\Listeners;

use Illuminate\Support\Facades\URL;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\Site;

/**
 * De taal van een aanvraag is de site van de pagina waarop het formulier
 * stond, met dezelfde regel waarmee Statamic\Http\Controllers\FormController
 * de taal van de mails kiest. Zo spreken de opgeslagen aanvraag en de mails
 * elkaar nooit tegen. Een meegepost veld `language` wordt overschreven.
 */
class RecordSubmissionLanguage
{
    public function handle(FormSubmitted $event): void
    {
        $site = Site::findByUrl(URL::previous()) ?? Site::default();

        $event->submission->set('language', $site->handle());
    }
}
