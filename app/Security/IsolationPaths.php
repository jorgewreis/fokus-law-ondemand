<?php

namespace App\Security;

final class IsolationPaths
{
    public const DEFAULT = '/home/other-app,/home/other-site';

    /**
     * @return list<string>
     */
    public static function parse(?string $value, string $default = self::DEFAULT): array
    {
        $raw = $value === null || trim($value) === '' ? $default : $value;
        $paths = [];

        foreach (explode(',', $raw) as $path) {
            $path = trim($path);
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
