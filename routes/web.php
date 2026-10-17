<?php

use App\Http\Controllers\Leadbot\DemoController;
use App\Http\Controllers\Leadbot\WidgetController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\LeadbotDemoKey;
use App\Http\Middleware\LeadbotEnabled;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// `robots.txt` staat als bestand in `public/` en niet als route hier. Het
// standaard nginx-recept voor Laravel serveert dat pad rechtstreeks van schijf,
// zonder `try_files`, waardoor een route op dit pad een 404 meekrijgt. Het
// bestand is daarmee de enige bron; `SITE_INDEXABLE` stuurt alleen nog de
// `X-Robots-Tag` in `NoIndexHeader`.

// RFC 9116. `Expires` wordt berekend en niet ingetypt: een verlopen datum
// maakt het bestand ongeldig, en een bestand dat één keer per jaar met de hand
// bijgewerkt moet worden is een bestand dat verloopt.
Route::get('.well-known/security.txt', function () {
    return response(view('security', [
        'site_url' => rtrim((string) config('app.url'), '/'),
        'expires' => now()->addYear()->startOfDay()->toIso8601ZuluString(),
    ]), 200, ['Content-Type' => 'text/plain']);
});

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemap_{handle}.xml', [SitemapController::class, 'collection'])->where('handle', '[a-z0-9_]+')->name('sitemap.collection');
Route::get('sitemap_taxonomies.xml', [SitemapController::class, 'taxonomies'])->name('sitemap.taxonomies');

// De website-assistent; zie config/leadbot.php. De widget haalt zijn token
// zelf op via `session`, want de pagina's komen uit de statische cache. Zonder
// CSRF op deze routes om dezelfde reden; de throttle vangt misbruik op. Een
// aanvraag gaat niet via deze routes maar via het gewone formulier-endpoint,
// mét CSRF en spamfilter.
Route::prefix('leadbot')
    ->middleware(LeadbotEnabled::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->group(function () {
        Route::get('session', [WidgetController::class, 'session'])->middleware('throttle:30,1')->name('leadbot.session');
        Route::post('ask', [WidgetController::class, 'ask'])->middleware('throttle:20,1')->name('leadbot.ask');
        Route::post('ask/stream', [WidgetController::class, 'stream'])->middleware('throttle:20,1')->name('leadbot.stream');
        Route::post('extract', [WidgetController::class, 'extract'])->middleware('throttle:10,1')->name('leadbot.extract');
        Route::get('helpdesk', [WidgetController::class, 'helpdesk'])->name('leadbot.helpdesk');
        Route::post('feedback', [WidgetController::class, 'feedback'])->middleware('throttle:20,1')->name('leadbot.feedback');

        Route::prefix('demo')->middleware(LeadbotDemoKey::class)->group(function () {
            Route::get('/', [DemoController::class, 'show'])->name('leadbot.demo');
            Route::get('state', [DemoController::class, 'state'])->name('leadbot.demo.state');
            Route::post('reset', [DemoController::class, 'reset'])->name('leadbot.demo.reset');
            Route::post('questions/{id}', [DemoController::class, 'answer'])->name('leadbot.demo.answer');
            Route::post('requests/{id}/status', [DemoController::class, 'status'])->name('leadbot.demo.status');
        });
    });
