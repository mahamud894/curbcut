<?php

namespace App\Providers;

use App\Scanning\NodeScanner;
use App\Scanning\Scanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Scanner::class, NodeScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('curbcut.api_rate_limit'))
            ->by($request->user()?->id ?: $request->ip()));
    }
}
