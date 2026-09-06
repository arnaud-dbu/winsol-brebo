<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML as Yaml;

trait CreatesTemporaryContent
{
    /** @var list<string> */
    private array $temporaryEntryIds = [];

    /**
     * Isoleert de bestanden van de assets-container. Haal de container daarna
     * met `find()` op en nooit met `make()->save()` — dat schrijft
     * `content/assets/{handle}.yaml` terug naar de werkkopie.
     *
     * `Statamic\Assets\AssetContainerContents` cachet zijn bestandslijst onder
     * `asset-list-contents-assets` in de gedeelde `file_testing`-store (zie
     * tests/bootstrap.php); `Storage::fake()` vervangt de disk, niet die cache.
     * Blijft die cache staan, dan kan `asset()` in een latere test een asset
     * uit een eerdere test teruggeven waarvan het bestand niet meer op de
     * verse fake disk staat.
     *
     * Kaal `Cache::forget()` zonder herstel is geen optie: dat forceert een
     * herberekening zodra deze of een latere test de container bevraagt, en
     * die herberekening leest op dat moment de (nog) actieve fake-disk. Die
     * — voor de rest van de suite onjuiste — lijst wordt daarna via
     * `remember()` teruggeschreven naar diezelfde gedeelde `file_testing`-
     * store en overleeft deze test. Empirisch bevestigd: dat corrumpeert
     * elke latere, niet-gefakete test die een echte asset-breedte nodig
     * heeft (27 errors, 32 failures verspreid over ongerelateerde secties,
     * bijvoorbeeld `App\Tags\Img::srcsetWidths()` dat een `null`-breedte
     * kreeg voor een productielogo). Vandaar dat de oorspronkelijke waarde
     * hier bewaard en na afloop van de test teruggezet wordt, in plaats van
     * enkel vergeten.
     */
    protected function fakeAssetDisk(): void
    {
        Storage::fake('r2');

        $key = 'asset-list-contents-assets';
        $original = Cache::get($key);

        Cache::forget($key);

        $this->beforeApplicationDestroyed(function () use ($key, $original): void {
            if ($original === null) {
                Cache::forget($key);
            } else {
                Cache::forever($key, $original);
            }
        });
    }

    /**
     * De opruiming hangt aan `beforeApplicationDestroyed()` en niet aan een
     * `tearDown()` in de testklasse: die wordt overgeslagen zodra een test er
     * geen definieert of vroegtijdig afbreekt, en het residu — bijvoorbeeld
     * een product zonder range — blijft dan in de getrackte `content/`-map
     * achter, waar het de contentbrede controles van de volgende test laat
     * falen.
     *
     * @param  array<string, mixed>  $data
     */
    protected function temporaryEntry(string $collection, string $slug, array $data): EntryContract
    {
        if ($this->temporaryEntryIds === []) {
            $this->beforeApplicationDestroyed($this->deleteTemporaryEntries(...));
        }

        $entry = Entry::make()
            ->collection($collection)
            ->locale('nl')
            ->slug($slug)
            ->data($data);

        $entry->save();

        $this->temporaryEntryIds[] = $entry->id();

        return $entry;
    }

    /**
     * Zet de acht nieuwsartikels neer die tot 05-09-2026 als dummy-inhoud in
     * `content/collections/articles` stonden. Jimmy liet ze verwijderen omdat
     * het testdata was, maar de weergavetests van de nieuwskaart, de
     * detailpagina, de themafilter en de schema-markup hebben wél echte
     * artikels nodig — met een thema, een datum, een bard-body met kop, lijst,
     * video en een inline beeld. Ze staan daarom nu in tests/fixtures/articles
     * en worden per test tijdelijk aangemaakt.
     *
     * @return array<string, EntryContract> gesleuteld op slug
     */
    protected function seedArticles(): array
    {
        $entries = [];

        foreach (glob(base_path('tests/fixtures/articles/*.md')) as $bestand) {
            // Bestandsnaam is `JJJJ-MM-DD.slug.md`, net als in de collectie.
            [$datum, $slug] = explode('.', basename($bestand, '.md'), 2);

            $frontmatter = preg_replace('/^---\n(.*?)\n---.*$/s', '$1', file_get_contents($bestand));
            $data = Yaml::parse($frontmatter);

            // Het vaste id uit de fixture niet overnemen: `temporaryEntry()`
            // laat Statamic er zelf een maken en ruimt op dat id weer op.
            unset($data['id']);

            $entry = $this->temporaryEntry('articles', $slug, $data);
            $entry->date($datum)->save();

            $entries[$slug] = $entry;
        }

        return $entries;
    }

    protected function deleteTemporaryEntries(): void
    {
        foreach ($this->temporaryEntryIds as $id) {
            Entry::find($id)?->delete();
        }

        $this->temporaryEntryIds = [];
    }
}
