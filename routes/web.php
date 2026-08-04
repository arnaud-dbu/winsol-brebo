<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// De view wordt hier buiten Statamic's cascade gerenderd, dus `{{ config:… }}`
// is er leeg. Alles wat de template nodig heeft gaat daarom expliciet mee.
Route::get('robots.txt', function () {
    return response(view('robots', [
        'indexable' => (bool) config('app.indexable'),
        'site_url' => config('app.url'),
    ]), 200, ['Content-Type' => 'text/plain']);
});

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemap_{handle}.xml', [SitemapController::class, 'collection'])->where('handle', '[a-z0-9_]+')->name('sitemap.collection');
Route::get('sitemap_taxonomies.xml', [SitemapController::class, 'taxonomies'])->name('sitemap.taxonomies');
