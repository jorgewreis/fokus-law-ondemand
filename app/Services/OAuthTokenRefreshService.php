<?php

namespace App\Services;

use App\Contracts\OAuthTokenRefresher;
use App\Contracts\OAuthTokenRefreshMutex;
use App\Exceptions\OAuthRefreshTokenRevoked;
use App\Models\OAuthCredential;

class OAuthTokenRefreshService
{
    private const REFRESH_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly OAuthTokenRefresher $refresher,
        private readonly OAuthTokenRefreshMutex $mutex,
    ) {}

    /**
     * Return a current credential, refreshing it when it expires within five minutes.
     *
     * The credential is reloaded only after acquiring the shared lock so concurrent
     * callers observe the winner's rotated token instead of refreshing stale data.
     */
    public function refreshIfExpiring(OAuthCredential $credential): OAuthCredential
    {
        return $this->mutex->synchronized((string) $credential->getKey(), function () use ($credential): OAuthCredential {
            $fresh = OAuthCredential::query()->findOrFail($credential->getKey());

            if ($fresh->revoked_at !== null) {
                throw new OAuthRefreshTokenRevoked;
            }

            if ($fresh->refresh_token === null || trim($fresh->refresh_token) === '') {
                $fresh->forceFill(['revoked_at' => now()])->save();

                throw new OAuthRefreshTokenRevoked;
            }

            if ($fresh->expires_at->isAfter(now()->addMinutes(self::REFRESH_WINDOW_MINUTES))) {
                return $fresh;
            }

            try {
                $tokens = $this->refresher->refresh($fresh->provider_key, $fresh->refresh_token);
            } catch (OAuthRefreshTokenRevoked $exception) {
                $fresh->forceFill([
                    'refresh_token' => null,
                    'revoked_at' => now(),
                ])->save();

                throw $exception;
            }

            $fresh->forceFill([
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken ?? $fresh->refresh_token,
                'expires_at' => now()->addSeconds($tokens->expiresInSeconds),
                'refreshed_at' => now(),
                'revoked_at' => null,
            ])->save();

            return $fresh->refresh();
        });
    }
}
