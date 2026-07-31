<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;

class ImageGaps extends Command
{
    protected $signature = 'winsol:image-gaps';

    protected $description = 'Somt elk content-veld op dat nog naar een placeholder wijst';

    private const PLACEHOLDER_PREFIX = 'placeholder/';

    /**
     * Een bard-afbeeldingsnode bewaart zijn pad niet als kaal pad, maar als
     * `asset::<container>::<pad>` (zie Statamic\Fieldtypes\Bard\ImageNode).
     * Zonder dit voorvoegsel eraf te halen mist een kale prefix-check elk
     * beeldgat dat via een bard-veld ingevoegd is.
     */
    private const ASSET_ID_PREFIX_PATTERN = '/^asset::[^:]+::/';

    public function handle(): int
    {
        $rows = [
            ...$this->entryRows(),
            ...$this->globalRows(),
            ...$this->termRows(),
        ];

        if ($rows === []) {
            $this->info('Geen beeldgaten.');

            return self::SUCCESS;
        }

        // Geen $this->table(): die wikkelt cellen op basis van de
        // gedetecteerde terminalbreedte, en in een niet-interactieve run
        // (CI, of een sandbox waar `COLUMNS=0` in de omgeving staat) valt
        // die breedte terug op 0. Elke cel breekt dan over meerdere regels,
        // wat het pad onbruikbaar maakt als boodschappenlijst — en precies
        // dat scenario (niet-interactief, als poort in een script) is hoe
        // dit commando bedoeld is te draaien.
        foreach ($rows as [$source, $item, $path, $placeholder]) {
            $this->line("{$source} | {$item} | {$path} | {$placeholder}");
        }
        $this->warn(count($rows).' beeldgaten open.');

        return self::FAILURE;
    }

    /**
     * @return list<list<string>>
     */
    private function entryRows(): array
    {
        $rows = [];

        foreach (Entry::query()->get() as $entry) {
            $rows = [...$rows, ...$this->rowsFor($entry->collectionHandle(), $entry->slug(), $entry->data()->all())];
        }

        return $rows;
    }

    /**
     * Een entry-only scan ziet nooit het `meta_image`-veld van het
     * `seo`-globalset: dat hangt niet aan een entry maar aan de site.
     *
     * @return list<list<string>>
     */
    private function globalRows(): array
    {
        $rows = [];

        foreach (GlobalVariables::all() as $variables) {
            $rows = [...$rows, ...$this->rowsFor("global:{$variables->handle()}", $variables->locale(), $variables->data()->all())];
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function termRows(): array
    {
        $rows = [];

        foreach (Term::query()->get() as $term) {
            $rows = [...$rows, ...$this->rowsFor("taxonomy:{$term->taxonomyHandle()}", $term->slug(), $term->data()->all())];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<list<string>>
     */
    private function rowsFor(string $source, string $item, array $data): array
    {
        $rows = [];

        foreach ($data as $handle => $value) {
            foreach ($this->placeholders($value, (string) $handle) as $hit) {
                $rows[] = [$source, $item, $hit['path'], $hit['value']];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{path: string, value: string}>
     */
    private function placeholders(mixed $value, string $path): array
    {
        if (is_string($value)) {
            return $this->isPlaceholder($value) ? [['path' => $path, 'value' => $this->stripAssetIdPrefix($value)]] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn (mixed $item, int|string $key): array => $this->placeholders($item, $this->extendPath($path, $key, $item)))
            ->all();
    }

    /**
     * Vergelijkt hoofdletterongevoelig: `winsol:import-images` saneert de
     * bestandsnaam wél maar de mapnaam niet (zie de docblock bij
     * `ImportImages::sanitizedPath()`), dus een map als `Placeholder/` is
     * evengoed een open beeldgat als `placeholder/`.
     */
    private function isPlaceholder(string $value): bool
    {
        return str_starts_with(strtolower($this->stripAssetIdPrefix($value)), self::PLACEHOLDER_PREFIX);
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
