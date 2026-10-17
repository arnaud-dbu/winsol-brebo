<?php

namespace App\Listeners;

use App\Services\Leadbot\DemoStore;
use App\Services\Leadbot\SubmissionReader;
use Statamic\Events\SubmissionCreated;

/**
 * Zet elke opgeslagen inzending in het nagebootste ERP-scherm van de demo,
 * zowel die van de formulieren op de pagina als die van de assistent. Zo
 * ziet wie de demo volgt beide kanalen naast elkaar binnenkomen, zoals ze
 * straks in module 01 van de ERP samenkomen.
 */
class RecordLeadbotDemo
{
    public function __construct(private DemoStore $store, private SubmissionReader $reader) {}

    public function handle(SubmissionCreated $event): void
    {
        if (! config('leadbot.enabled')) {
            return;
        }

        $this->store->addSubmission($event->submission, $this->reader->read($event->submission));
    }
}
