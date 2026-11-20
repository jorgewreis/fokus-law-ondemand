<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** CNJ NNNNNNN-DD.AAAA.J.TR.OOOO validation and digits-only storage value. */
class CnjNumber
{
    public function normalize(string $value): string
    {
        $value = trim($value);
        if (preg_match('/\A[0-9]{7}-[0-9]{2}\.[0-9]{4}\.[0-9]\.[0-9]{2}\.[0-9]{4}\z/', $value) === 1) {
            $digits = str_replace(['-', '.'], '', $value);
        } elseif (preg_match('/\A[0-9]{20}\z/', $value) === 1) {
            $digits = $value;
        } else {
            throw ValidationException::withMessages(['cnj' => 'Informe um número CNJ válido.']);
        }

        $checkDigits = substr($digits, 0, 7).substr($digits, 9).'00';
        $remainder = 0;
        foreach (str_split($checkDigits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }
        $expectedCheckDigits = str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT);

        if (substr($digits, 7, 2) !== $expectedCheckDigits) {
            throw ValidationException::withMessages(['cnj' => 'Informe um número CNJ válido.']);
        }

        return $digits;
    }
}
