<?php

namespace App\Inspace;

use RuntimeException;

class UnknownBlockException extends RuntimeException
{
    public function __construct(public readonly ?string $blockId)
    {
        parent::__construct(sprintf(
            'Onbekend blok-id: %s. Stuur opaque blokken ongewijzigd terug zoals je ze kreeg.',
            $blockId ?? '(ontbreekt)'
        ));
    }
}
