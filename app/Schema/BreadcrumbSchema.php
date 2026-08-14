<?php

namespace App\Schema;

use Statamic\Facades\Entry;

class BreadcrumbSchema
{
    /**
     * @return array<string, mixed>|null
     */
    public static function node(string $uri, string $currentTitle): ?array
    {
        $uri = '/'.trim($uri, '/');

        if ($uri === '/') {
            return null;
        }

        $items = [['name' => 'Home', 'item' => SiteUrl::absolute('/')]];

        $segments = explode('/', trim($uri, '/'));
        $last = count($segments) - 1;
        $path = '';
        $currentTitle = trim($currentTitle);

        foreach ($segments as $index => $segment) {
            $path .= '/'.$segment;

            $items[] = [
                'name' => $index === $last && $currentTitle !== ''
                    ? $currentTitle
                    : self::titleFor($path, $segment),
                'item' => SiteUrl::absolute($path),
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => SiteUrl::absolute($uri).'#breadcrumb',
            'itemListElement' => array_map(
                fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['item'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }

    /**
     * Een tussenliggend niveau heeft meestal een echte entry (`/aanbod`).
     * Zo niet, dan is de slug het beste dat we hebben.
     */
    private static function titleFor(string $path, string $segment): string
    {
        $entry = Entry::findByUri($path);

        if ($entry && trim((string) $entry->get('title')) !== '') {
            return (string) $entry->get('title');
        }

        return ucfirst(str_replace('-', ' ', $segment));
    }
}
