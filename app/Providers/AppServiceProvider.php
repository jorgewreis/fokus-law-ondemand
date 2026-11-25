<?php

namespace App\Providers;

use App\Contracts\OAuthTokenRefreshMutex;
use App\Dashboard\ExampleUnitBoard;
use App\Dashboard\UnitBoard;
use App\Services\CacheOAuthTokenRefreshMutex;
use App\Support\PublicOrigin;
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
        $this->app->bind(UnitBoard::class, ExampleUnitBoard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::useOrigin(PublicOrigin::from(
            (string) config('app.url'),
            (string) config('app.path_prefix'),
        ));
    }
}
