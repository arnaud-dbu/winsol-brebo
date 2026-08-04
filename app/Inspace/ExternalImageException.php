<?php

namespace App\Inspace;

use RuntimeException;

class ExternalImageException extends RuntimeException
{
    public function __construct(public readonly string $src)
    {
        parent::__construct(sprintf(
            'De afbeelding %s hoort niet bij deze site. Upload hem eerst via POST /media en gebruik de teruggegeven id.',
            $src
        ));
    }
}
