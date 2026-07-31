<?php

namespace App\Console\Commands;

use App\Services\ContentValueScanner;
use App\Services\UsedAssetFinder;
use Illuminate\Console\Command;
use Statamic\Assets\Asset;

class ImageGaps extends Command
{
    protected $signature = 'winsol:image-gaps';

    protected $description = 'Somt elk content-veld op dat nog een placeholder of een gewatermerkte foto gebruikt';

    /**
     * Mappen waarin geen enkele foto hoort te blijven staan bij oplevering.
     * `placeholder/` is de afgesproken conventie, `dummy-images/` is wat de
     * demo-content werkelijk gebruikt — dat laatste ontbrak, waardoor deze
     * poort een schone site meldde terwijl elke pagina nog dummybeeld toonde.
     *
     * @var list<string>
     */
    private const PLACEHOLDER_PREFIXES = ['placeholder/', 'dummy-images/'];

    /**
     * Losse dummybestanden in de wortel van de container, die geen map hebben
     * om aan herkend te worden. Exact vergelijken en niet op voorvoegsel: een
     * `str_starts_with` op zulke korte namen zou een echte foto met dezelfde
     * beginletters meeslepen.
     *
     * @var list<string>
     */
    private const PLACEHOLDER_PATHS = ['dscf4033.jpg', 'test-1.jpg'];

    public function handle(ContentValueScanner $scanner, UsedAssetFinder $finder): int
    {
        $gaps = $this->placeholderRows($scanner);
        $watermarked = $this->watermarkedPaths($finder);

        if ($gaps === [] && $watermarked === []) {
            $this->info("Geen beeldgaten en geen gewatermerkte foto's in gebruik.");

            return self::SUCCESS;
        }

        // Geen $this->table(): die wikkelt cellen op basis van de
        // gedetecteerde terminalbreedte, en in een niet-interactieve run
        // (CI, of een sandbox waar `COLUMNS=0` in de omgeving staat) valt
        // die breedte terug op 0. Elke cel breekt dan over meerdere regels,
        // wat het pad onbruikbaar maakt als boodschappenlijst — en precies
        // dat scenario (niet-interactief, als poort in een script) is hoe
        // dit commando bedoeld is te draaien.
        if ($gaps !== []) {
            $this->line('Placeholders in gebruik — vervang de foto:');

            foreach ($gaps as [$source, $item, $path, $placeholder]) {
                $this->line("{$source} | {$item} | {$path} | {$placeholder}");
            }

            $this->warn(count($gaps).' beeldgaten open.');
        }

        if ($watermarked !== []) {
            $this->line('Watermerken in gebruik — draai winsol:clean-watermarks of vraag een schone versie aan:');

            foreach ($watermarked as $path) {
                $this->line("watermerk | {$path}");
            }

            $this->warn(count($watermarked)." foto's dragen nog een watermerk.");
        }

        return self::FAILURE;
    }

    /**
     * @return list<list<string>>
     */
    private function placeholderRows(ContentValueScanner $scanner): array
    {
        $rows = [];

        foreach ($scanner->values() as $value) {
            if ($this->isPlaceholder($value['value'])) {
                $rows[] = [$value['source'], $value['item'], $value['path'], $value['value']];
            }
        }

        return $rows;
    }

    /**
     * De tweede helft van de poort. `winsol:clean-watermarks` sluit ook met
     * exitcode 0 af wanneer élke foto overgeslagen is wegens een onbruikbaar
     * of verouderd watermerkvlak; zonder deze controle komt een site dus groen
     * door beide commando's met zichtbaar gewatermerkte foto's erop.
     *
     * @return list<string>
     */
    private function watermarkedPaths(UsedAssetFinder $finder): array
    {
        return $finder->assets()
            ->filter(fn (Asset $asset): bool => (bool) $asset->get('watermark'))
            ->map(fn (Asset $asset): string => $asset->path())
            ->values()
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
        $value = strtolower($value);

        if (in_array($value, self::PLACEHOLDER_PATHS, true)) {
            return true;
        }

        foreach (self::PLACEHOLDER_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
