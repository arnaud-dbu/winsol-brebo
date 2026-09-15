<?php

namespace App\Modifiers;

use Illuminate\Support\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Modifiers\Modifier;

/**
 * Zet het pad van een brochure om naar de versie in de taal van de bezoeker.
 *
 * De brochurekaart op een productpagina linkt naar `/brochures?brochure=<pad>`.
 * Dat `brochure`-veld staat alleen op de Nederlandse producten, dus de Franse
 * en Engelse erven het Nederlandse pad. In de Franse bibliotheek bestaat dat
 * pad niet — die draagt `..._fr.pdf` — en dus stond er niets aangevinkt en
 * begon elke Franstalige bezoeker vanaf een productpagina met een leeg
 * formulier.
 *
 * De koppeling loopt via het `id` van het item, want dat is wél gelijk over de
 * talen heen. Een pad dat nergens voorkomt gaat ongewijzigd terug: dan
 * selecteert er niets, net als voorheen.
 */
class BrochureForSite extends Modifier
{
    public function index($value, $params, $context)
    {
        $pad = trim((string) $value);

        if ($pad === '') {
            return $pad;
        }

        $huidige = $this->items(Site::current()->handle());

        // Al de juiste taal: niets te doen.
        if ($huidige->firstWhere('file', $pad)) {
            return $pad;
        }

        foreach (Site::all() as $site) {
            $item = $this->items($site->handle())->firstWhere('file', $pad);

            if ($item && ($vertaald = $huidige->firstWhere('id', $item['id'] ?? null))) {
                return $vertaald['file'];
            }
        }

        return $pad;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function items(string $site)
    {
        $set = GlobalSet::findByHandle('brochure_library');

        return collect($set?->in($site)?->get('items') ?? []);
    }
}
