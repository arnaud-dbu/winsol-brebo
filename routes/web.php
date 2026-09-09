<?php

use App\Http\Controllers\SitemapController;
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
