<?php

namespace App\Providers;

use App\Contracts\OAuthTokenRefreshMutex;
use App\Dashboard\ExampleUnitBoard;
use App\Dashboard\UnitBoard;
use App\Services\CacheOAuthTokenRefreshMutex;
use App\Support\PublicOrigin;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
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
        TrustProxies::at(config('trustedproxy.proxies'));
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $origin = PublicOrigin::from(
            (string) config('app.url'),
            (string) config('app.path_prefix'),
        );
        URL::useOrigin($origin);
        if (str_starts_with($origin, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
