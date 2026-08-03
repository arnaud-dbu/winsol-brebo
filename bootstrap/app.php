<?php

use App\Http\Middleware\InspaceToken;
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
        $middleware->alias(['inspace.token' => InspaceToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
