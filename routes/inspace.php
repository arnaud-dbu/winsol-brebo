<?php

use App\Http\Controllers\Inspace\MediaController;
use App\Http\Controllers\Inspace\PageController;
use App\Http\Controllers\Inspace\SchemaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/inspace/v1')
    // throttle vóór token: een ongeldig token moet ook meetellen, anders telt
    // een brute-force van foute tokens nooit mee tegen de limiet (elke poging
    // eindigt al bij `inspace.token` vóórdat de throttle ooit draait).
    ->middleware(['throttle:inspace', 'inspace.token'])
    ->group(function () {
        Route::get('schema', SchemaController::class);
        Route::get('pages', [PageController::class, 'index']);
        Route::get('pages/{id}', [PageController::class, 'show']);

        Route::middleware('inspace.revisions')->group(function (): void {
            Route::post('pages', [PageController::class, 'store']);
            Route::patch('pages/{id}', [PageController::class, 'update']);
            Route::post('media', [MediaController::class, 'store']);
        });
    });
