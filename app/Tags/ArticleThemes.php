<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

class ArticleThemes extends Tags
{
    protected static $handle = 'article_themes';

    /**
     * De thema's waaraan minstens één gepubliceerd artikel hangt, ontdubbeld en
     * alfabetisch op titel. Voedt het filter op /nieuws, zodat een klik
     * nooit een lege grid oplevert en het filter meegroeit met de content.
     */
    public function index(): array
    {
        return Entry::query()
            ->where('collection', 'articles')
            ->where('published', true)
            ->get()
            ->flatMap(fn ($article) => $article->augmentedValue('theme')->value() ? [$article->augmentedValue('theme')->value()] : [])
            ->filter()
            ->unique(fn ($theme) => $theme->slug())
            ->sortBy(fn ($theme) => $theme->get('title'))
            ->map(fn ($theme) => [
                'slug' => $theme->slug(),
                'title' => $theme->get('title'),
            ])
            ->values()
            ->all();
    }
}
