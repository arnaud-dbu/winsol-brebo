<?php

namespace App\Jobs;

use App\Listeners\SendSubmissionToErp;
use Statamic\Forms\SendEmail;

/**
 * De mail van een formulier, behalve voor een aanvraag die de ERP al
 * aanvaardde: die staat in de Berichtenbak, en een mail erbij is dubbel werk.
 */
class SendFormEmail extends SendEmail
{
    public function handle(): void
    {
        if ($this->submission->getSupplement(SendSubmissionToErp::DELIVERED)) {
            return;
        }

        parent::handle();
    }
}
