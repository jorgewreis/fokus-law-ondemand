<?php

namespace App\Services;

class PersonDocumentValidator
{
    public function validCpf(string $input): bool
    {
        if (! preg_match('/\A(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})\z/', $input)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $input) ?? '';
        if (strlen($digits) !== 11 || preg_match('/\A([0-9])\1{10}\z/', $digits)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $digits[$index] * (($position + 1) - $index);
            }
            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int) $digits[$position] !== $expected) {
                return false;
            }
        }

        return true;
    }

    public function validCnpj(string $input): bool
    {
        if (! preg_match('/\A(?:[0-9A-Za-z]{14}|[0-9A-Za-z]{2}\.[0-9A-Za-z]{3}\.[0-9A-Za-z]{3}\/[0-9A-Za-z]{4}-[0-9]{2})\z/', $input)) {
            return false;
        }

        $value = strtoupper(preg_replace('/[.\/\-\s]/', '', $input) ?? '');
        if (strlen($value) !== 14 || ! preg_match('/\A[A-Z0-9]{12}[0-9]{2}\z/', $value)) {
            return false;
        }

        $base = substr($value, 0, 12);
        $firstDigit = $this->cnpjDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        if ((int) $value[12] !== $firstDigit) {
            return false;
        }

        $secondDigit = $this->cnpjDigit($base.substr((string) $firstDigit, 0, 1), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return (int) $value[13] === $secondDigit;
    }

    /** @param list<int> $weights */
    private function cnpjDigit(string $value, array $weights): int
    {
        $sum = 0;
        foreach (str_split($value) as $index => $character) {
            $numeric = ctype_digit($character) ? (int) $character : ord($character) - 48;
            $sum += $numeric * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
