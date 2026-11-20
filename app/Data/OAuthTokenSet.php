<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class OAuthTokenSet
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresInSeconds,
    ) {
        if (trim($this->accessToken) === '' || $this->expiresInSeconds < 1) {
            throw new InvalidArgumentException('The OAuth token response is invalid.');
        }

        if ($this->refreshToken !== null && trim($this->refreshToken) === '') {
            throw new InvalidArgumentException('The OAuth token response is invalid.');
        }
    }
}
