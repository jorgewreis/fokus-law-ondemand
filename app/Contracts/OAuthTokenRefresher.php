<?php

namespace App\Contracts;

use App\Data\OAuthTokenSet;

interface OAuthTokenRefresher
{
    /**
     * Exchange a refresh token using a provider-specific adapter.
     *
     * Implementations must not log or expose token values in exceptions and must
     * apply a bounded network timeout shorter than the refresh lock lease.
     */
    public function refresh(string $providerKey, string $refreshToken): OAuthTokenSet;
}
