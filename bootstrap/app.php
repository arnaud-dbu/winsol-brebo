<?php

use App\Http\Middleware\InspaceRevisionsGuard;
use App\Http\Middleware\InspaceToken;
use App\Http\Middleware\NoIndexHeader;
use App\Http\Middleware\RedirectTrailingSlash;
use App\Http\Middleware\SecurityHeaders;
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
        // horen op een afgeschermde omgeving uit de index te blijven. Dat
        // geldt net zo voor de beveiligingsheaders.
        //
        // Het schema staat hier al correct op https: TLS termineert op
        // dezelfde host als php-fpm, dus `$request->secure()` klopt zonder
        // `trustProxies`. Komt er ooit een CDN of load balancer vóór de site,
        // dan moet `trustProxies` alsnog geconfigureerd worden — anders stopt
        // HSTS geruisloos met versturen en downgrade de trailing-slash-
        // omleiding bezoekers stilzwijgend naar `http://`.
        //
        // NoIndexHeader en SecurityHeaders staan vóór RedirectTrailingSlash:
        // die laatste retourneert een 301 zonder `$next` aan te roepen, dus
        // alleen middleware die er vóór staat wikkelt zich nog om die respons
        // heen.
        $middleware->append(NoIndexHeader::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(RedirectTrailingSlash::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
