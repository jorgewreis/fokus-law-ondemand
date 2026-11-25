<?php

namespace App\Security;

final class IsolationAssessment
{
    public static function database(?bool $connected): string
    {
        if ($connected === null) {
            return 'skipped';
        }

        return $connected ? 'fail' : 'pass';
    }

    public static function path(bool $exists, bool $readable): string
    {
        if (! $exists) {
            return 'absent';
        }

        return $readable ? 'fail' : 'pass';
    }
}
