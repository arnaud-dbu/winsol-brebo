<?php

namespace App\Console\Commands;

use App\Services\Leadbot\Assistant;
use App\Services\Leadbot\FaqWriter;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Haalt uit uitgeschreven telefoongesprekken (bv. de transcripties uit
 * Telavox) de vragen die klanten stellen en de antwoorden die Winsol geeft,
 * en zet ze als concept in de FAQ. Een mens kijkt ze na en publiceert ze in
 * de CP; zolang een item concept is, gebruikt de assistent het niet.
 *
 * Leest .txt-, .md- en .json-bestanden in een map, één gesprek per bestand.
 */
class LeadbotFaqFromTranscripts extends Command
{
    protected $signature = 'leadbot:faq-from-transcripts
        {path : Map met transcripties}
        {--limit=0 : Hoogstens zoveel bestanden verwerken (0 = alle)}
        {--dry-run : Alleen tonen wat er gevonden wordt}';

    protected $description = 'Leid FAQ-concepten af uit transcripties van klantgesprekken';

    public function handle(Assistant $assistant, FaqWriter $writer): int
    {
        if (! $assistant->usesModel()) {
            $this->error('Hiervoor is ANTHROPIC_API_KEY nodig.');

            return self::FAILURE;
        }

        $files = iterator_to_array(Finder::create()->files()->in($this->argument('path'))->name(['*.txt', '*.md', '*.json'])->sortByName(), false);

        if ((int) $this->option('limit') > 0) {
            $files = array_slice($files, 0, (int) $this->option('limit'));
        }

        [$found, $created] = [0, 0];

        foreach ($files as $file) {
            $this->line("<comment>{$file->getFilename()}</comment>");

            foreach ($assistant->faqFromTranscript($file->getContents()) as $item) {
                $found++;

                if ($writer->exists($item['question'])) {
                    $this->line("  = {$item['question']} (bestaat al)");

                    continue;
                }

                if (! $this->option('dry-run')) {
                    $writer->create($item['question'], $item['answer'], $item['category'], 'telavox', published: false);
                }

                $created++;
                $this->line("  + {$item['question']}");
            }
        }

        $this->info("Gevonden: {$found}, nieuw als concept: {$created}. Kijk ze na onder Collecties > Veelgestelde vragen.");

        return self::SUCCESS;
    }
}
