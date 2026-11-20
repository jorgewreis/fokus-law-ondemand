<?php

namespace App\Services;

use App\Contracts\OAuthTokenRefreshMutex;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CacheOAuthTokenRefreshMutex implements OAuthTokenRefreshMutex
{
    private const LOCK_SECONDS = 120;

    private const LOCK_WAIT_SECONDS = 30;

    public function synchronized(string $credentialId, Closure $callback): mixed
    {
        $store = (string) config('services.oauth.lock_store', config('cache.default'));
        $driver = config("cache.stores.{$store}.driver");

        if (! in_array($driver, ['database', 'redis', 'memcached', 'dynamodb'], true)) {
            throw new RuntimeException('OAuth refresh requires a shared cache lock store.');
        }

        try {
            return Cache::store($store)
                ->lock('oauth-credential-refresh:'.$credentialId, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('Timed out waiting for the OAuth credential refresh lock.', previous: $exception);
        }
    }
}
