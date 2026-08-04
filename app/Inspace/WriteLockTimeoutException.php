<?php

namespace App\Inspace;

use RuntimeException;

/**
 * Een andere schrijfactie op dezelfde collectie hield de lock langer dan de
 * wachttijd vast. Dit is een tijdelijke drukte-fout, geen clientfout: de
 * aanroeper mag het zonder wijzigingen opnieuw proberen.
 */
class WriteLockTimeoutException extends RuntimeException
{
    public function __construct(public readonly string $collection)
    {
        parent::__construct(sprintf(
            'Kon geen schrijflock op collectie "%s" krijgen binnen de wachttijd. Probeer het opnieuw.',
            $collection
        ));
    }
}
