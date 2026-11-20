<?php

namespace App\Providers;

use App\Contracts\OAuthTokenRefreshMutex;
use App\Services\CacheOAuthTokenRefreshMutex;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OAuthTokenRefreshMutex::class, CacheOAuthTokenRefreshMutex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::useOrigin((string) config('app.url'));
    }
}
