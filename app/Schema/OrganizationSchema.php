<?php

namespace App\Schema;

use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class OrganizationSchema
{
    /**
     * Alleen een URL waarvan de host bij het platform past telt mee. Dat vangt
     * placeholders als https://test.be zonder een lijst met placeholder-hosts
     * bij te houden, die toch veroudert.
     *
     * @var array<string, list<string>>
     */
    private const SOCIAL_HOSTS = [
        'facebook' => ['facebook.com'],
        'instagram' => ['instagram.com'],
        'linkedin' => ['linkedin.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
    ];

    public static function id(): string
    {
        return SiteUrl::absolute('/').'#organization';
    }

    /**
     * @return array<string, mixed>
     */
    public static function node(): array
    {
        $globals = GlobalSet::findByHandle('globals')?->inCurrentSite();
        $contact = (array) ($globals?->get('contact') ?? []);
        $socials = (array) ($globals?->get('socials') ?? []);

        return [
            '@type' => 'Organization',
            '@id' => self::id(),
            'name' => Site::current()->name(),
            'url' => SiteUrl::absolute('/'),
            'telephone' => trim((string) ($contact['phone'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'sameAs' => self::sameAs($socials),
        ];
    }

    /**
     * @param  array<string, mixed>  $socials
     * @return list<string>
     */
    public static function sameAs(array $socials): array
    {
        $urls = [];

        foreach (self::SOCIAL_HOSTS as $platform => $allowedHosts) {
            $url = trim((string) ($socials[$platform] ?? ''));

            if ($url === '') {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host);

            foreach ($allowedHosts as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    $urls[] = $url;
                    break;
                }
            }
        }

        return $urls;
    }
}
