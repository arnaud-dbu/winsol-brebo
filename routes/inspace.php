<?php

use App\Http\Controllers\Inspace\MediaController;
use App\Http\Controllers\Inspace\PageController;
use App\Http\Controllers\Inspace\SchemaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/inspace/v1')
    ->middleware(['inspace.token', 'throttle:inspace'])
    ->group(function () {
        Route::get('schema', SchemaController::class);
        Route::get('pages', [PageController::class, 'index']);
        Route::get('pages/{id}', [PageController::class, 'show']);
        Route::post('pages', [PageController::class, 'store']);
        Route::post('media', [MediaController::class, 'store']);
    });
