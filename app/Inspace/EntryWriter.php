<?php

namespace App\Inspace;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Asset;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fields\Blueprint;

class EntryWriter
{
    public function __construct(private readonly EntryMapper $mapper) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{entry: EntryContract, warnings: list<string>, existing: bool}
     *
     * @throws WriteLockTimeoutException
     */
    public function create(string $collection, array $payload): array
    {
        return $this->locked($collection, function () use ($collection, $payload): array {
            $existing = $this->findByExternalId($collection, $payload['external_id'] ?? null);

            if ($existing !== null) {
                return ['entry' => $existing, 'warnings' => [], 'existing' => true];
            }

            $entry = Entry::make()
                ->collection($collection)
                ->locale(Collection::findByHandle($collection)->sites()->first())
                ->slug($this->uniqueSlug($collection, $payload['slug'] ?? Str::slug($payload['title'])))
                ->date($payload['date'] ?? now());

            $warnings = $this->apply($entry, $collection, $payload);

            $entry->published(($payload['status'] ?? 'draft') === 'published');
            $entry->save();

            return ['entry' => $entry, 'warnings' => $warnings, 'existing' => false];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{entry: EntryContract, warnings: list<string>, existing: bool}
     *
     * @throws WriteLockTimeoutException
     */
    public function update(EntryContract $entry, array $payload): array
    {
        $collection = $entry->collectionHandle();

        return $this->locked($collection, function () use ($entry, $collection, $payload): array {
            $warnings = $this->apply($entry, $collection, $payload);

            if (array_key_exists('slug', $payload) && $payload['slug'] !== null) {
                $entry->slug($this->uniqueSlug($collection, $payload['slug'], $entry->id()));
            }

            if (array_key_exists('date', $payload) && $payload['date'] !== null) {
                $entry->date($payload['date']);
            }

            if (array_key_exists('status', $payload)) {
                $entry->published($payload['status'] === 'published');
            }

            $entry->save();

            return ['entry' => $entry, 'warnings' => $warnings, 'existing' => false];
        });
    }

    /**
     * Zet elk gemapt veld weg op basis van het écht geconfigureerde
     * blueprint-veldtype (`$field->type()`), niet op basis van de API-naam.
     * Spiegelt `EntryMapper::read()`: die twee moeten in de pas blijven lopen
     * omdat de API-namen uit config komen en dus geen betrouwbare schakelaar
     * zijn — een collectie met een ander veld op de API-naam `image` zou een
     * naam-gebaseerde `match` alsnog fout wegschrijven.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function apply(EntryContract $entry, string $collection, array $payload): array
    {
        $mapping = $this->mapper->mapping($collection);
        $contentApiName = $this->mapper->contentApiName($collection);
        $blueprint = Collection::findByHandle($collection)->entryBlueprint();
        $warnings = [];

        foreach ($payload as $apiName => $value) {
            if ($apiName === 'status' || $apiName === 'slug' || $apiName === 'date') {
                continue;
            }

            if ($apiName === 'external_id') {
                // Over HTTP komt een lege string hier nooit binnen: de
                // globale `ConvertEmptyStringsToNull`-middleware zet 'm al om
                // naar null vóór de request de controller bereikt. Deze
                // normalisatie is dus puur defensief voor een aanroeper die
                // `create()`/`update()` rechtstreeks aanroept (buiten die
                // middleware om). Zonder deze guard is het gevolg niet zomaar
                // "een lege string als duplicaatsleutel": `Query\Builder::
                // filterTestEquals()` vergelijkt met `strtolower($item ?? '')
                // === strtolower($value ?? '')`, dus `where('external_id',
                // '')` matcht daarmee élke entry die het veld nooit heeft
                // gezet (dus vrijwel elk bestaand artikel) — empirisch
                // bevestigd toen een reproductie hiervan zonder guard tijdens
                // testopruiming een echt, bestaand artikel wegverwijderde.
                $entry->set('external_id', $value === '' ? null : $value);

                continue;
            }

            $handle = $mapping[$apiName] ?? null;

            if ($handle === null) {
                continue;
            }

            if ($apiName === $contentApiName) {
                [$nodes, $blockWarnings] = $this->content($entry, $collection, (array) $value);
                $entry->set($handle, $nodes);
                $warnings = array_merge($warnings, $blockWarnings);

                continue;
            }

            $entry->set($handle, $this->normalize($blueprint, $apiName, $handle, $value));
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @throws UnresolvableAssetException
     */
    private function normalize(Blueprint $blueprint, string $apiName, string $handle, mixed $value): mixed
    {
        $field = $blueprint->field($handle);

        return match ($field?->type()) {
            'assets' => $value === null ? null : [$this->assetPath($apiName, (string) $value)],
            'terms' => $value === null ? null : [$value],
            'toggle' => (bool) $value,
            default => $value,
        };
    }

    /**
     * Een assets-veld accepteert twee vormen: de `id` (`container::pad`) die
     * `POST /media` teruggeeft, en de `url` uit datzelfde antwoord —
     * `Asset::find()` herkent en vertaalt allebei. Het fieldtype zelf
     * verwacht geen van beide, maar het containerpad; die vertaling gebeurt
     * hier, niet in `MediaController`, want een inline `<img>` in `content`
     * heeft juist wél de id-vorm nodig (zie `ImageResolver`).
     *
     * @throws UnresolvableAssetException
     */
    private function assetPath(string $apiName, string $reference): string
    {
        $asset = Asset::find($reference);

        if ($asset === null) {
            throw new UnresolvableAssetException($apiName, $reference);
        }

        return (string) $asset->path();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function content(EntryContract $entry, string $collection, array $blocks): array
    {
        $contentHandle = (string) config("inspace.writable.{$collection}.content_field");

        $field = Collection::findByHandle($collection)->entryBlueprint()->field($contentHandle);

        $sanitizer = new HtmlSanitizer((new HtmlWhitelist)->of($field));
        $images = new ImageResolver;
        $links = new LinkResolver;
        $warnings = [];

        // `$sanitizer` en `$images` legen hun eigen `warnings()` bij elke
        // aanroep. De transform-haak wordt per gewijzigd tekstblok apart
        // aangeroepen, dus zonder deze eigen accumulatie zou alleen de
        // waarschuwing van het láátst verwerkte blok overleven en gaan de
        // waarschuwingen van eerdere blokken stilzwijgend verloren.
        $converter = new BlockConverter($field, function (string $html) use ($sanitizer, $images, $links, &$warnings): string {
            $clean = $sanitizer->clean($html);
            $warnings = array_merge($warnings, $sanitizer->warnings());

            $withAssets = $images->toAssetRefs($links->toStatamic($clean));
            $warnings = array_merge($warnings, $images->warnings());

            return $withAssets;
        });

        $nodes = $converter->toProsemirror($blocks, (array) $entry->get($contentHandle, []));

        return [$nodes, array_values(array_unique($warnings))];
    }

    private function findByExternalId(string $collection, ?string $externalId): ?EntryContract
    {
        // Zelfde reden als in apply(): over HTTP is een lege string hier
        // onbereikbaar (ConvertEmptyStringsToNull maakt er al null van), maar
        // een rechtstreekse aanroep van create() zonder deze guard zou
        // `where('external_id', '')` laten matchen op élke entry zonder dat
        // veld (zie de toelichting in apply()) — dus mogelijk een willekeurig
        // bestaand artikel teruggeven als "duplicaat".
        if ($externalId === null || $externalId === '') {
            return null;
        }

        return Entry::query()
            ->where('collection', $collection)
            ->where('external_id', $externalId)
            ->first();
    }

    private function uniqueSlug(string $collection, string $desired, ?string $ignoreId = null): string
    {
        $base = Str::slug($desired);

        // Een titel die alleen uit leestekens bestaat slugify't naar een lege
        // string. Zonder val terug op een willekeurige waarde zou de eerste
        // zo'n entry slug "" krijgen (kapotte url) en elke volgende er zonder
        // enige suffix-poging óók in blijven hangen, want Entry::query() vindt
        // dan zelden een bestaande entry met slug "" om tegen te suffixen.
        if ($base === '') {
            $base = Str::lower(Str::random(8));
        }

        $slug = $base;
        $suffix = 1;

        while (($found = Entry::query()->where('collection', $collection)->where('slug', $slug)->first()) !== null
            && $found->id() !== $ignoreId) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Statamic is flat-file met een Stache-index; parallelle schrijfacties
     * kunnen die in de knoop leggen. Tien seconden wachten en dan opgeven is
     * beter dan een half geschreven index — maar `Lock::block()` gooit dan
     * `LockTimeoutException`, en die mag de aanroeper niet als een kale 500
     * bereiken. `create()` en `update()` nestelen elkaar niet: geen van beide
     * roept de ander aan binnen zijn eigen `locked()`-callback, dus dezelfde
     * request kan zichzelf hier niet blokkeren. Voegt een latere taak een
     * upsert toe die beide combineert, dan moet die de vergrendeling delen
     * in plaats van een tweede keer aan te vragen.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws WriteLockTimeoutException
     */
    private function locked(string $collection, callable $callback): mixed
    {
        try {
            return Cache::lock("inspace:write:{$collection}", 30)->block(10, $callback);
        } catch (LockTimeoutException) {
            throw new WriteLockTimeoutException($collection);
        }
    }
}
