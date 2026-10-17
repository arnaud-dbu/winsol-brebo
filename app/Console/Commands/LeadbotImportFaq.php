<?php

namespace App\Console\Commands;

use App\Services\Leadbot\FaqWriter;
use Illuminate\Console\Command;

/**
 * Laadt vragen en antwoorden in de FAQ-collectie, bijvoorbeeld een export
 * uit Telavox of een lijst die Winsol zelf bijhoudt.
 *
 * CSV met een kopregel (question;answer;category;keywords, scheidingsteken
 * ; of ,) of JSON: een lijst objecten met dezelfde sleutels. Alleen question
 * en answer zijn verplicht. Een vraag die al bestaat, wordt overgeslagen.
 */
class LeadbotImportFaq extends Command
{
    protected $signature = 'leadbot:import-faq
        {file : Pad naar een .csv- of .json-bestand}
        {--source=telavox : Bron die bij elk item komt te staan}
        {--draft : Als concept importeren, om eerst na te kijken in de CP}
        {--dry-run : Alleen tonen wat er zou gebeuren}';

    protected $description = 'Importeer vragen en antwoorden in de FAQ van de website-assistent';

    public function handle(FaqWriter $writer): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Bestand niet gevonden: {$path}");

            return self::FAILURE;
        }

        $rows = str_ends_with(strtolower($path), '.json') ? $this->json($path) : $this->csv($path);
        [$created, $skipped] = [0, 0];

        foreach ($rows as $row) {
            $question = trim((string) ($row['question'] ?? $row['vraag'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? $row['antwoord'] ?? ''));

            if ($question === '' || $answer === '' || $writer->exists($question)) {
                $skipped++;

                continue;
            }

            if (! $this->option('dry-run')) {
                $writer->create(
                    $question,
                    $answer,
                    (string) ($row['category'] ?? $row['categorie'] ?? 'producten'),
                    (string) $this->option('source'),
                    ! $this->option('draft'),
                    ($row['keywords'] ?? $row['zoekwoorden'] ?? null) ?: null,
                );
            }

            $created++;
            $this->line("  + {$question}");
        }

        $this->info(($this->option('dry-run') ? 'Zou importeren' : 'Geïmporteerd').": {$created}, overgeslagen: {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function json(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn ($column) => strtolower(trim($column, " \t\n\r\0\x0B\u{FEFF}\"")), str_getcsv($first, $delimiter));
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }

        fclose($handle);

        return $rows;
    }
}
