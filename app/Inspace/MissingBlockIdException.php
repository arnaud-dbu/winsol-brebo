<?php

namespace App\Inspace;

use RuntimeException;

/**
 * Een set zonder attrs.id is een opslagdefect, geen clientfout: bard-frontmatter
 * wordt met de hand geschreven en kan het row-id missen dat de converter nodig
 * heeft om de set later terug te vinden. Dit mag nooit stil verdwijnen achter
 * een lege id, en de client mag er niet de schuld van krijgen.
 */
class MissingBlockIdException extends RuntimeException
{
    public function __construct(public readonly ?string $blockType)
    {
        parent::__construct(sprintf(
            'Set van type %s mist attrs.id in de opslag. Herstel de content — dit is geen clientfout.',
            $blockType ?? '(onbekend)'
        ));
    }
}
