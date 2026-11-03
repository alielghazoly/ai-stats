<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the main (read-only) database connection from config/ai.php
        config(['database.connections.' . config('ai.db_connection') => config('ai.db')]);
    }

    public function boot(): void
    {
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute((int) config('ai.rate_limit_per_minute'))
                ->by($request->input('user_ref') ?: $request->ip());
        });
    }
}
