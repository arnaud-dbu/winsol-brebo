<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Statamic\Facades\Entry;

class UsedAssetFinder
{
    /**
     * Alle assetpaden waar minstens een entryveld naar wijst.
     *
     * De waarden worden uit de ruwe data gehaald in plaats van uit de
     * augmented velden: een assetsveld bewaart daar simpelweg zijn pad, en zo
     * hoeft er niet per blueprint uitgezocht te worden welk veld een asset is.
     *
     * @return Collection<int, string>
     */
    public function paths(): Collection
    {
        return Entry::query()->get()
            ->flatMap(fn ($entry) => $this->extract($entry->data()->all()))
            ->unique()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function extract(mixed $value): array
    {
        if (is_string($value)) {
            return preg_match('/\.(jpe?g|png|webp|pdf)$/i', $value) ? [$value] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)->flatMap(fn ($item) => $this->extract($item))->all();
    }
}
