<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SitemapController;

Route::get('robots.txt', function () {
    return response(view('robots'), 200, ['Content-Type' => 'text/plain']);
});

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemap_{handle}.xml', [SitemapController::class, 'collection'])->where('handle', '[a-z0-9_]+')->name('sitemap.collection');
Route::get('sitemap_taxonomies.xml', [SitemapController::class, 'taxonomies'])->name('sitemap.taxonomies');
