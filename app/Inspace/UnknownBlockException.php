<?php

namespace App\Inspace;

use RuntimeException;

class UnknownBlockException extends RuntimeException
{
    public function __construct(public readonly ?string $blockId, ?string $blockType = null)
    {
        $message = $blockType !== null
            ? sprintf(
                'Onbekend bloktype: %s. Enkel "text" en de geregistreerde sets van dit veld zijn geldig.',
                $blockType
            )
            : sprintf(
                'Onbekend blok-id: %s. Stuur opaque blokken ongewijzigd terug zoals je ze kreeg.',
                $blockId ?? '(ontbreekt)'
            );

        parent::__construct($message);
    }
}
