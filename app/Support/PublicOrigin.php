<?php

namespace App\Support;

final class PublicOrigin
{
    /**
     * Public base URL, including the /novologin prefix when it is configured.
     * APP_URL may already contain the prefix; it is not applied twice.
     */
    public static function from(string $appUrl, string $prefix): string
    {
        $origin = rtrim($appUrl, '/');
        $prefix = trim($prefix, '/');

        if ($prefix !== '' && ! str_ends_with($origin, '/'.$prefix)) {
            $origin .= '/'.$prefix;
        }

        return $origin;
    }
}
