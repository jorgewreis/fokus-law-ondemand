<?php

namespace App\Support;

final class MailTransport
{
    /** @var list<string> */
    public const DELIVERABLE = ['smtp', 'log', 'array'];

    public static function name(): string
    {
        return (string) config('mail.default');
    }

    public static function configured(): bool
    {
        return in_array(self::name(), self::DELIVERABLE, true);
    }
}
