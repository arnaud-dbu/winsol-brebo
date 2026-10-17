<?php

namespace App\Services\Leadbot;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

/**
 * Wat de assistent weet, en hoe hij het terugvindt.
 *
 * De site is de bron: productgroepen, producten, de servicepagina's, de
 * adviesartikels, de showrooms, de realisaties en de FAQ-collectie. Wie in
 * het control panel een tekst aanpast, past daarmee ook het antwoord van de
 * assistent aan; na elke opslag vervalt de index (zie AppServiceProvider).
 *
 * Bij elke vraag gaan alleen de best passende stukken mee naar het model,
 * niet de hele site. Zo blijft een antwoord snel en betaalbaar, ook als de
 * FAQ straks honderden vragen uit de telefoongesprekken bevat. Het zoeken is
 * BM25 over de tekst in alle drie de talen samen: een Franse vraag over
 * "volets" vindt zo ook de pagina die in het Nederlands over rolluiken gaat.
 */
class Knowledge
{
    public const CACHE_KEY = 'leadbot.knowledge';

    /** Pagina's met inhoud die een klantvraag kan beantwoorden. */
    private const PAGES = ['service', 'over-ons', 'offerte', 'simuleer-je-lening', 'contact', 'brochures', 'realisaties', 'aanbod'];

    /** Velden die geen lopende tekst bevatten. */
    private const SKIP_KEYS = [
        'id', 'image', 'images', 'media', 'video', 'brochure', 'brochures', 'type', 'attrs', 'blueprint', 'template',
        'seo_noindex', 'updated_by', 'updated_at', 'range', 'range_categories', 'icon', 'link', 'url', 'origin',
        'latitude', 'longitude', 'order', 'marks', 'author', 'slug', 'date', 'source', 'category', 'promo',
    ];

    private const STOPWORDS = [
        'de', 'het', 'een', 'en', 'van', 'voor', 'met', 'op', 'in', 'is', 'dat', 'die', 'je', 'jullie', 'ik', 'mijn', 'wat',
        'hoe', 'kan', 'kun', 'kunt', 'kunnen', 'wordt', 'worden', 'zijn', 'ook', 'bij', 'aan', 'om', 'te', 'er', 'niet',
        'maar', 'als', 'of', 'dan', 'nog', 'wel', 'heb', 'hebt', 'heeft', 'hebben', 'welke', 'waar', 'wanneer', 'graag',
        'les', 'des', 'une', 'est', 'pour', 'avec', 'que', 'qui', 'dans', 'vous', 'votre', 'vos', 'sur', 'pas', 'aussi',
        'the', 'and', 'for', 'with', 'you', 'your', 'are', 'can', 'what', 'how', 'does', 'this', 'that', 'have',
    ];

    /**
     * Alle documenten, met per document de tekst per taal en de url per taal.
     *
     * @return array<string, array{id: string, type: string, title: array<string, string>, urls: array<string, string>, text: array<string, string>, terms: array<string, int>, length: int}>
     */
    public function documents(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            $documents = [];

            foreach ($this->sourceEntries() as $entry) {
                $document = $this->fromEntry($entry);

                if ($document !== null) {
                    $documents[$document['id']] = $document;
                }
            }

            $documents['realisaties:overzicht'] = $this->realisationsDocument();

            foreach ($documents as &$document) {
                $tokens = $this->tokens(implode(' ', [
                    str_repeat(implode(' ', $document['title']).' ', 3),
                    implode(' ', $document['text']),
                ]));
                $document['terms'] = array_count_values($tokens);
                $document['length'] = count($tokens);
            }

            return $documents;
        });
    }

    /**
     * De best passende documenten voor een vraag, in volgorde van relevantie.
     *
     * @return array<int, array{id: string, type: string, title: string, url: string, text: string, score: float}>
     */
    public function search(string $query, string $locale, int $limit = 8, ?string $type = null): array
    {
        $documents = collect($this->documents())->when($type, fn ($all) => $all->where('type', $type));
        $terms = array_unique($this->tokens($query));

        if ($terms === [] || $documents->isEmpty()) {
            return [];
        }

        $count = $documents->count();
        $averageLength = max(1, $documents->avg('length'));
        $frequencies = [];

        foreach ($terms as $term) {
            $frequencies[$term] = $documents->filter(fn ($document) => isset($document['terms'][$term]))->count();
        }

        return $documents
            ->map(function (array $document) use ($terms, $frequencies, $count, $averageLength) {
                $score = 0.0;

                foreach ($terms as $term) {
                    $frequency = $document['terms'][$term] ?? 0;

                    if ($frequency === 0) {
                        continue;
                    }

                    $idf = log(1 + ($count - $frequencies[$term] + 0.5) / ($frequencies[$term] + 0.5));
                    $score += $idf * ($frequency * 2.2) / ($frequency + 1.2 * (0.25 + 0.75 * $document['length'] / $averageLength));
                }

                return [...$document, 'score' => $score];
            })
            ->filter(fn ($document) => $document['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($document) => $this->localize($document, $locale))
            ->values()
            ->all();
    }

    /**
     * Alle FAQ-items in één taal, voor de helpdesk in de widget.
     *
     * @return array<int, array{id: string, question: string, answer: string, category: string, keywords: string}>
     */
    public function faq(string $locale): array
    {
        return Entry::query()
            ->where('collection', 'faq')
            ->where('site', 'nl')
            ->whereStatus('published')
            ->get()
            ->sortBy(fn (EntryContract $entry) => [$entry->value('category'), $entry->value('title')])
            ->map(function (EntryContract $entry) use ($locale) {
                $localized = $entry->in($locale) ?? $entry;

                return [
                    'id' => $entry->id(),
                    'question' => (string) $localized->value('title'),
                    'answer' => (string) $localized->value('answer'),
                    'category' => (string) ($entry->value('category') ?? 'producten'),
                    'keywords' => (string) $localized->value('keywords'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * De drie showrooms, met openingsuren als weekrooster zodat de widget
     * zelf kan uitrekenen of een showroom nu open is. Dat moet in de browser
     * gebeuren: productie serveert de pagina's uit de statische cache.
     *
     * @return array<int, array{slug: string, name: string, address: string, phone: string, maps: string, lat: float, lng: float, hours: array<int, array<string, mixed>>, schedule: array<int, array{0: string, 1: string}|string|null>}>
     */
    public function showrooms(): array
    {
        return Entry::query()
            ->where('collection', 'locations')
            ->where('site', 'nl')
            ->orderBy('order')
            ->get()
            ->map(fn (EntryContract $entry) => [
                'slug' => $entry->slug(),
                'name' => (string) $entry->get('name'),
                'address' => trim($entry->get('street').' '.$entry->get('number').', '.$entry->get('postal_code').' '.$entry->get('city')),
                'phone' => (string) $entry->get('phone'),
                'maps' => 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($entry->get('latitude').','.$entry->get('longitude')),
                'lat' => (float) $entry->get('latitude'),
                'lng' => (float) $entry->get('longitude'),
                'hours' => $entry->get('opening_hours') ?? [],
                'schedule' => $this->schedule($entry->get('opening_hours') ?? []),
            ])
            ->values()
            ->all();
    }

    /**
     * De productgroepen in de taal van de bezoeker. `handle` is de slug in
     * die taal: precies wat de formulieren opslaan (zie RangeCheckboxes).
     *
     * @return array<int, array{id: string, handle: string, title: string, url: string}>
     */
    public function ranges(string $locale): array
    {
        return Entry::query()
            ->where('collection', 'ranges')
            ->where('site', $locale)
            ->whereStatus('published')
            ->get()
            ->sortBy(fn (EntryContract $entry) => $entry->value('order'))
            ->map(fn (EntryContract $entry) => [
                'id' => $entry->root()->id(),
                'handle' => $entry->slug(),
                'title' => (string) $entry->value('title'),
                'url' => (string) $entry->url(),
            ])
            ->values()
            ->all();
    }

    /**
     * Producten per productgroep, om een productpagina te herkennen en het
     * product vooraf aan te vinken zoals de offerteknop dat doet.
     *
     * @return array<int, array{id: string, range: string, title: string, text: string, url: string}>
     */
    public function products(string $locale): array
    {
        $rangeSlugs = collect($this->ranges($locale))->pluck('handle', 'id');

        return Entry::query()
            ->where('collection', 'products')
            ->where('site', $locale)
            ->whereStatus('published')
            ->get()
            ->map(fn (EntryContract $entry) => [
                'id' => $entry->id(),
                'range' => (string) ($rangeSlugs[collect($entry->value('range'))->first()] ?? ''),
                'title' => (string) $entry->value('title'),
                'text' => (string) $entry->value('text'),
                'url' => (string) $entry->url(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{file: string, label: string}>
     */
    public function brochures(string $locale): array
    {
        $set = GlobalSet::findByHandle('brochure_library')?->in($locale);

        return collect($set?->get('items') ?? [])
            ->filter(fn ($item) => ! empty($item['file']))
            ->map(fn ($item) => ['file' => $item['file'], 'label' => (string) $item['label']])
            ->values()
            ->all();
    }

    /**
     * Wat in elk gesprek geldt, los van de vraag: wie Winsol Brebo is, de
     * productgroepen en de showrooms. Vast van vorm, zodat de prompt-cache
     * het over gesprekken heen hergebruikt.
     */
    public function essentials(): string
    {
        $lines = ['<productgroepen>'];

        foreach ($this->ranges('nl') as $range) {
            $lines[] = "- {$range['title']} ({$range['url']})";
        }

        $lines[] = '</productgroepen>';
        $lines[] = '<showrooms>';

        foreach ($this->showrooms() as $showroom) {
            $hours = collect($showroom['hours'])->map(fn ($row) => "{$row['day']}: {$row['time']}")->implode('; ');
            $lines[] = "- {$showroom['name']}, {$showroom['address']}, tel. {$showroom['phone']}. Openingsuren: {$hours}.";
        }

        $lines[] = '</showrooms>';

        return implode("\n", $lines);
    }

    /**
     * Woorden voor het zoeken: kleine letters, zonder accenten, zonder
     * stopwoorden, en met een grove afkapping van meervouden zodat "ramen"
     * en "raam" of "screens" en "screen" elkaar vinden.
     *
     * @return array<int, string>
     */
    public function tokens(string $text): array
    {
        preg_match_all('/[a-z0-9]+/', Str::lower(Str::ascii($text)), $matches);

        $tokens = [];

        foreach ($matches[0] as $word) {
            if (strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }

            $tokens[] = $this->stem($word);
        }

        return $tokens;
    }

    private function stem(string $word): string
    {
        if (strlen($word) <= 4) {
            return $word;
        }

        foreach (['ingen', 'en', 'es', 's', 'e'] as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) - strlen($suffix) >= 4) {
                return substr($word, 0, -strlen($suffix));
            }
        }

        return $word;
    }

    /**
     * @return Collection<int, EntryContract>
     */
    private function sourceEntries(): Collection
    {
        return Entry::query()
            ->whereIn('collection', ['ranges', 'products', 'pages', 'articles', 'locations', 'faq'])
            ->where('site', 'nl')
            ->whereStatus('published')
            ->get()
            ->filter(fn (EntryContract $entry) => $entry->collectionHandle() !== 'pages' || in_array($entry->slug(), self::PAGES, true))
            ->values();
    }

    /**
     * @return array{id: string, type: string, title: array<string, string>, urls: array<string, string>, text: array<string, string>}|null
     */
    private function fromEntry(EntryContract $entry): ?array
    {
        $type = match ($entry->collectionHandle()) {
            'articles' => 'artikel',
            'locations' => 'showroom',
            'faq' => 'faq',
            'ranges' => 'productgroep',
            'products' => 'product',
            default => 'pagina',
        };

        $title = [];
        $urls = [];
        $text = [];

        foreach (['nl', 'fr', 'en'] as $site) {
            $localized = $site === 'nl' ? $entry : $entry->in($site);

            if (! $localized) {
                continue;
            }

            // Een localisatie draagt alleen de velden die vertaald werden en
            // erft de rest; die rest staat al in de Nederlandse versie.
            $body = $type === 'faq'
                ? (string) $localized->value('answer')."\n".$localized->value('keywords')
                : $this->plainText($localized->data()->all());

            if (trim($body) === '') {
                continue;
            }

            $title[$site] = (string) ($localized->value('title') ?? $localized->value('name'));
            $text[$site] = Str::limit($body, 3500, '');

            if ($type !== 'faq' && $type !== 'showroom' && $localized->url()) {
                $urls[$site] = $localized->url();
            }
        }

        if ($text === []) {
            return null;
        }

        return [
            'id' => $entry->collectionHandle().':'.$entry->slug(),
            'type' => $type,
            'title' => $title,
            'urls' => $urls,
            'text' => $text,
        ];
    }

    /**
     * Een overzicht van de realisaties per productgroep. De realisaties zelf
     * dragen alleen een titel en een foto; samen vertellen ze wel waar Winsol
     * Brebo al plaatste en wat.
     *
     * @return array{id: string, type: string, title: array<string, string>, urls: array<string, string>, text: array<string, string>}
     */
    private function realisationsDocument(): array
    {
        $ranges = collect($this->ranges('nl'))->keyBy('id');
        $grouped = Entry::query()
            ->where('collection', 'realisaties')
            ->where('site', 'nl')
            ->whereStatus('published')
            ->get()
            ->groupBy(fn (EntryContract $entry) => $ranges[collect($entry->value('range'))->first()]['title'] ?? 'Overige');

        $lines = ["Winsol Brebo toont {$grouped->flatten()->count()} realisaties op de website, gefilterd per productgroep."];

        foreach ($grouped as $range => $entries) {
            $lines[] = "{$range} ({$entries->count()}): ".$entries->map(fn ($entry) => $entry->value('title'))->take(25)->implode('; ').'.';
        }

        $page = Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'realisaties')->first();

        return [
            'id' => 'realisaties:overzicht',
            'type' => 'pagina',
            'title' => ['nl' => 'Realisaties', 'fr' => 'Réalisations', 'en' => 'Projects'],
            'urls' => collect(['nl', 'fr', 'en'])
                ->mapWithKeys(fn ($site) => [$site => ($site === 'nl' ? $page : $page?->in($site))?->url()])
                ->filter()
                ->all(),
            'text' => ['nl' => implode("\n", $lines)],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{id: string, type: string, title: string, url: string, text: string, score: float}
     */
    private function localize(array $document, string $locale): array
    {
        return [
            'id' => $document['id'],
            'type' => $document['type'],
            'title' => $document['title'][$locale] ?? $document['title']['nl'] ?? reset($document['title']),
            'url' => $document['urls'][$locale] ?? $document['urls']['nl'] ?? '',
            'text' => $document['text'][$locale] ?? $document['text']['nl'] ?? reset($document['text']),
            'score' => round($document['score'], 3),
        ];
    }

    /**
     * Zet de ingetypte openingsuren om naar een rooster per weekdag
     * (0 = zondag). Een dag is een [van, tot]-paar, 'appointment' of null.
     *
     * @param  array<int, array{day?: string, time?: string}>  $rows
     * @return array<int, array{0: string, 1: string}|string|null>
     */
    private function schedule(array $rows): array
    {
        $days = [
            'maandag' => [1], 'dinsdag' => [2], 'woensdag' => [3], 'donderdag' => [4], 'vrijdag' => [5],
            'zaterdag' => [6], 'zondag' => [0], 'di - vr' => [2, 3, 4, 5], 'ma - vr' => [1, 2, 3, 4, 5],
        ];
        $schedule = array_fill(0, 7, null);

        foreach ($rows as $row) {
            $label = Str::lower(trim($row['day'] ?? ''));
            $time = Str::lower(trim($row['time'] ?? ''));

            if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $time, $match)) {
                $value = [str_pad($match[1], 5, '0', STR_PAD_LEFT), str_pad($match[2], 5, '0', STR_PAD_LEFT)];
            } elseif (str_contains($time, 'afspraak')) {
                $value = 'appointment';
            } else {
                $value = null;
            }

            foreach ($days[$label] ?? [] as $day) {
                $schedule[$day] = $value;
            }
        }

        return $schedule;
    }

    /**
     * Alle lopende tekst uit een entry, in documentvolgorde. Bard-velden
     * leveren geneste nodes op; daar zit de tekst in de `text`-bladeren.
     *
     * @param  array<mixed>  $data
     */
    private function plainText(array $data): string
    {
        $lines = [];

        array_walk_recursive($data, function ($value, $key) use (&$lines) {
            if (! is_string($value) || in_array($key, self::SKIP_KEYS, true)) {
                return;
            }

            $value = trim(strip_tags($value));

            if (str_word_count($value) < 3 || Str::startsWith($value, ['http', '/', 'entry::', 'statamic://'])) {
                return;
            }

            $lines[] = $value;
        });

        return implode("\n", array_unique($lines));
    }
}
