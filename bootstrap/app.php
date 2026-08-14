<?php

use App\Http\Middleware\InspaceRevisionsGuard;
use App\Http\Middleware\InspaceToken;
use App\Http\Middleware\NoIndexHeader;
use App\Http\Middleware\RedirectTrailingSlash;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], __DIR__.'/../routes/inspace.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'inspace.token' => InspaceToken::class,
            'inspace.revisions' => InspaceRevisionsGuard::class,
        ]);

        // Globaal en niet alleen op `web`: ook de API en het control panel
        // horen op een afgeschermde omgeving uit de index te blijven.
        //
        // Append en niet prepend: de omleiding bouwt een absolute URL op basis
        // van het request, en pas ná Laravel's eigen globale middleware staat
        // het schema achter een proxy correct op https.
        $middleware->append(RedirectTrailingSlash::class);
        $middleware->append(NoIndexHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
