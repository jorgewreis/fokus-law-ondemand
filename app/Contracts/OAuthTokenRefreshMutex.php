<?php

namespace App\Contracts;

use Closure;

interface OAuthTokenRefreshMutex
{
    public function synchronized(string $credentialId, Closure $callback): mixed;
}
