<?php

namespace App\Inspace;

use RuntimeException;

/**
 * Een assets-veld (bv. `image`, `meta_image`) kreeg een referentie die niet
 * naar een bestaand asset wijst — noch als `id` uit `POST /media`, noch als
 * `url`. Zonder deze guard slaat `EntryWriter` de rauwe referentie gewoon op:
 * het fieldtype verwacht een containerpad, dus die waarde augment tot `null`
 * en de pagina publiceert stil zonder beeld.
 */
class UnresolvableAssetException extends RuntimeException
{
    public function __construct(public readonly string $apiName, public readonly string $reference)
    {
        parent::__construct(sprintf(
            'De afbeelding "%s" voor veld "%s" bestaat niet. Upload hem eerst via POST /media en gebruik de teruggegeven id of url.',
            $reference,
            $apiName
        ));
    }
}
