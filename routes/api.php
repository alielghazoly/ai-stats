<?php

use App\Http\Controllers\AskController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Middleware\LogAiRequests;
use App\Http\Middleware\ServiceTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([LogAiRequests::class, ServiceTokenAuth::class, 'throttle:ai'])
    ->group(function () {
        Route::post('/ask', AskController::class);
    });

// تحميل الملفات المُصدَّرة: لينك موقّع ومؤقت من غير Bearer
Route::get('exports/{file}', ExportDownloadController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->name('exports.download');
