<?php

namespace App\Services;

use Statamic\Facades\Entry;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;

/**
 * Levert elke tekstwaarde uit de content die een assetpad kan zijn, met de
 * plek waar hij staat.
 *
 * Bestaat om te voorkomen dat `winsol:image-gaps` en `UsedAssetFinder` dezelfde
 * vraag — welke paden gebruikt de content? — elk anders beantwoorden. Liep de
 * ene wel over globals en de andere niet, dan rapporteert de ene poort een
 * beeldgat dat het saneringscommando niet eens ziet.
 */
class ContentValueScanner
{
    /**
     * Een bard-afbeeldingsnode bewaart zijn pad niet als kaal pad, maar als
     * `asset::<container>::<pad>` (zie Statamic\Fieldtypes\Bard\ImageNode).
     * Zonder dit voorvoegsel eraf te halen is de waarde geen pad waar een
     * container iets mee kan.
     */
    private const ASSET_ID_PREFIX_PATTERN = '/^asset::[^:]+::/';

    /**
     * @return list<array{source: string, item: string, path: string, value: string}>
     */
    public function values(): array
    {
        return [
            ...$this->entryValues(),
            ...$this->globalValues(),
            ...$this->termValues(),
        ];
    }

    /**
     * @return list<array{source: string, item: string, path: string, value: string}>
     */
    private function entryValues(): array
    {
        $values = [];

        foreach (Entry::query()->get() as $entry) {
            $values = [...$values, ...$this->valuesFor($entry->collectionHandle(), $entry->slug(), $entry->data()->all())];
        }

        return $values;
    }

    /**
     * Een entry-only scan ziet nooit het `meta_image`-veld van het
     * `seo`-globalset: dat hangt niet aan een entry maar aan de site.
     *
     * @return list<array{source: string, item: string, path: string, value: string}>
     */
    private function globalValues(): array
    {
        $values = [];

        foreach (GlobalVariables::all() as $variables) {
            $values = [...$values, ...$this->valuesFor("global:{$variables->handle()}", $variables->locale(), $variables->data()->all())];
        }

        return $values;
    }

    /**
     * @return list<array{source: string, item: string, path: string, value: string}>
     */
    private function termValues(): array
    {
        $values = [];

        foreach (Term::query()->get() as $term) {
            $values = [...$values, ...$this->valuesFor("taxonomy:{$term->taxonomyHandle()}", $term->slug(), $term->data()->all())];
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{source: string, item: string, path: string, value: string}>
     */
    private function valuesFor(string $source, string $item, array $data): array
    {
        $values = [];

        foreach ($data as $handle => $value) {
            foreach ($this->strings($value, (string) $handle) as $hit) {
                $values[] = ['source' => $source, 'item' => $item, 'path' => $hit['path'], 'value' => $hit['value']];
            }
        }

        return $values;
    }

    /**
     * @return list<array{path: string, value: string}>
     */
    private function strings(mixed $value, string $path): array
    {
        if (is_string($value)) {
            return [['path' => $path, 'value' => $this->stripAssetIdPrefix($value)]];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn (mixed $item, int|string $key): array => $this->strings($item, $this->extendPath($path, $key, $item)))
            ->all();
    }

    private function stripAssetIdPrefix(string $value): string
    {
        return preg_replace(self::ASSET_ID_PREFIX_PATTERN, '', $value) ?? $value;
    }

    /**
     * Een replicator- of bard-set draagt altijd een `type`-sleutel (het
     * sethandle), dus die neemt de indexnotatie over zodra die aanwezig is —
     * anders zegt de veldnaam `page_builder` niets over wélke van de twaalf
     * secties op een pagina het gat bevat.
     */
    private function extendPath(string $path, int|string $key, mixed $item): string
    {
        if (is_array($item) && is_string($item['type'] ?? null)) {
            return "{$path}[{$key}:{$item['type']}]";
        }

        return is_int($key) ? "{$path}[{$key}]" : "{$path}.{$key}";
    }
}
