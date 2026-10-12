<?php

namespace App\Schema;

use Statamic\Contracts\Globals\Variables;
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
        $socials = (array) (self::globals()?->get('socials') ?? []);

        return [
            '@type' => 'Organization',
            '@id' => self::id(),
            'name' => Site::current()->name(),
            'url' => SiteUrl::absolute('/'),
            'logo' => SiteUrl::file('logo-square.png'),
            // Uit de vestigingen en niet uit de globals: het nummer hoort bij
            // de showroom die opneemt. Stond het op twee plaatsen, dan zou een
            // gewijzigde centrale er maar op één bijgewerkt worden.
            'telephone' => LocationsSchema::phones(),
            'email' => self::email(),
            'sameAs' => self::sameAs($socials),
        ];
    }

    /**
     * Het algemene adres. De vestigingen hebben geen eigen adres en dragen
     * daarom ook dit.
     */
    public static function email(): string
    {
        $contact = (array) (self::globals()?->get('contact') ?? []);

        return trim((string) ($contact['email'] ?? ''));
    }

    private static function globals(): ?Variables
    {
        return GlobalSet::findByHandle('globals')?->inCurrentSite();
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
