<?php

namespace App\Providers;

use App\Listeners\AddDefaultBlueprintTabs;
use App\Listeners\ClearSitemapCache;
use App\Listeners\CompressUploadedAsset;
use App\Services\ImageCompressor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Statamic\Events\AssetUploaded;
use Statamic\Events\BlueprintSaved;
use Statamic\Events\CollectionSaved;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Fieldtypes\Sets;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Ingevuld wanneer het `range`-veld van een product niet naar een
     * bestaande range wijst. Een lege waarde zou het middelste routesegment
     * laten wegvallen, waarna `/aanbod/{{ range_slug }}/{{ slug }}` samenvalt
     * met de rangeroute `/aanbod/{slug}` en het product stil een HTTP 200 met
     * de rangepagina teruggeeft. Met deze slug — die geen enkele range draagt
     * — staat de fout in de URL en geeft de route een 404.
     */
    public const UNRESOLVED_RANGE_SLUG = 'range-onbekend';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageCompressor::class, function () {
            return new ImageCompressor(
                maxWidth: (int) config('image-compression.max_width'),
                jpegQuality: (int) config('image-compression.jpeg_quality'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Sets::useIcons('icons', resource_path('svg/icons/regular'));

        // Het `range`-veld is een entries-veld en levert een id, geen slug. De route
        // heeft de slug nodig, dus die wordt hier afgeleid.
        Collection::computed('products', 'range_slug', function (\Statamic\Contracts\Entries\Entry $entry): string {
            $id = Arr::first(Arr::wrap($entry->get('range')));

            return ($id ? Entry::find($id)?->slug() : null) ?? self::UNRESOLVED_RANGE_SLUG;
        });

        Event::listen(BlueprintSaved::class, AddDefaultBlueprintTabs::class);
        Event::listen(CollectionSaved::class, AddDefaultBlueprintTabs::class);

        Event::listen(
            AssetUploaded::class,
            CompressUploadedAsset::class,
        );

        View::share('cookie_consent', $this->loadCookieConsent());
        View::share('font_faces', config('fonts.fonts', []));
    }

    private function loadCookieConsent(): array
    {
        $locale = app()->getLocale();
        $path = base_path("lang/{$locale}/cookie-consent.json");

        if (! file_exists($path)) {
            $path = base_path('lang/nl/cookie-consent.json');
        }

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    protected $subscribe = [
        ClearSitemapCache::class,
    ];
}
