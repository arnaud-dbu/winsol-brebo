<?php

namespace App\Services\Leadbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;

/**
 * Schrijft vragen en antwoorden naar de FAQ-collectie in Statamic. Daar
 * kan Winsol ze nakijken en aanpassen, en de assistent kent ze meteen.
 *
 * Elk item ontstaat in het Nederlands. De assistent antwoordt zelf in de
 * taal van de bezoeker; een vertaling in de CP is welkom maar niet nodig.
 */
class FaqWriter
{
    public const CATEGORIES = ['offerte', 'producten', 'plaatsing', 'herstelling', 'showroom', 'premies'];

    public function create(string $question, string $answer, string $category = 'producten', string $source = 'handmatig', bool $published = true, ?string $keywords = null): string
    {
        $entry = Entry::make()
            ->collection('faq')
            ->locale('nl')
            ->slug($this->uniqueSlug($question))
            ->published($published)
            ->data(array_filter([
                'title' => Str::limit(trim($question), 240, ''),
                'answer' => trim($answer),
                'category' => in_array($category, self::CATEGORIES, true) ? $category : 'producten',
                'source' => $source,
                'keywords' => $keywords,
            ]));

        $entry->save();
        Cache::forget(Knowledge::CACHE_KEY);

        return $entry->id();
    }

    /**
     * Bestaat deze vraag al, op hoofdletters, leestekens en accenten na?
     */
    public function exists(string $question): bool
    {
        $needle = $this->normalize($question);

        return Entry::query()
            ->where('collection', 'faq')
            ->get()
            ->contains(fn ($entry) => $this->normalize((string) $entry->value('title')) === $needle);
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));
    }

    private function uniqueSlug(string $question): string
    {
        $base = Str::limit(Str::slug($question), 80, '') ?: 'vraag';
        $slug = $base;
        $suffix = 2;

        while (Entry::query()->where('collection', 'faq')->where('slug', $slug)->count() > 0) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
