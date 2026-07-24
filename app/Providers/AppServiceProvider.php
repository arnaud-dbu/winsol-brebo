<?php

namespace App\Providers;

use App\Listeners\AddDefaultBlueprintTabs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Statamic\Events\BlueprintSaved;
use Statamic\Events\CollectionSaved;
use Statamic\Fieldtypes\Sets;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ImageCompressor::class, function () {
            return new \App\Services\ImageCompressor(
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

        Event::listen(BlueprintSaved::class, AddDefaultBlueprintTabs::class);
        Event::listen(CollectionSaved::class, AddDefaultBlueprintTabs::class);

        Event::listen(
            \Statamic\Events\AssetUploaded::class,
            \App\Listeners\CompressUploadedAsset::class,
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
        \App\Listeners\ClearSitemapCache::class,
    ];
}
