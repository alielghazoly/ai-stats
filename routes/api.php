<?php

use App\Http\Controllers\AskController;
use App\Http\Middleware\LogAiRequests;
use App\Http\Middleware\ServiceTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([LogAiRequests::class, 'throttle:ai'])
    ->group(function () {
        Route::post('/ask', AskController::class);
    });
