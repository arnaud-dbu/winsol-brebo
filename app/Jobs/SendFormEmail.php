<?php

namespace App\Jobs;

use App\Listeners\SendSubmissionToErp;
use Statamic\Forms\SendEmail;

/**
 * De mail van een formulier, behalve de melding van een aanvraag die de ERP al
 * aanvaardde: die staat in de Berichtenbak, en een melding erbij is dubbel
 * werk. De bevestiging aan de klant vertrekt wel.
 *
 * Een melding is elke mail die niet naar de aanvrager zelf gaat. Aan `to` en
 * niet aan `id` of plaats in de lijst: een id is in de CP te hernoemen, en dan
 * zou de melding stil weer naast de Berichtenbak vertrekken.
 */
class SendFormEmail extends SendEmail
{
    public function handle(): void
    {
        if ($this->isNotification() && $this->submission->getSupplement(SendSubmissionToErp::DELIVERED)) {
            return;
        }

        parent::handle();
    }

    private function isNotification(): bool
    {
        return ($this->config['to'] ?? null) !== '{{ email }}';
    }
}
