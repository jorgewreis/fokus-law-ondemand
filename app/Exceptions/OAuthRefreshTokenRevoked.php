<?php

namespace App\Exceptions;

use RuntimeException;

final class OAuthRefreshTokenRevoked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The OAuth refresh token was revoked and authorization is required again.');
    }
}
