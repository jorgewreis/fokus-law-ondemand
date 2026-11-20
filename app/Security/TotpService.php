<?php

namespace App\Security;

use InvalidArgumentException;

final class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD_SECONDS = 30;

    private const DIGITS = 6;

    private const MAX_WINDOW = 10;

    /**
     * Generate a cryptographically random, unpadded Base32 TOTP secret.
     */
    public function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 10 || $bytes > 128) {
            throw new InvalidArgumentException('TOTP secret length must be between 10 and 128 bytes.');
        }

        return $this->encodeBase32(random_bytes($bytes));
    }

    /**
     * Build an authenticator-compatible RFC 6238 provisioning URI.
     */
    public function provisioningUri(string $issuer, string $account, string $secret): string
    {
        if ($issuer === '' || $account === '') {
            throw new InvalidArgumentException('TOTP issuer and account must not be empty.');
        }

        $this->decodeBase32($secret);
        $secret = strtoupper($secret);

        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label.'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD_SECONDS;
    }

    /**
     * Verify a six-digit SHA-1 TOTP code using a bounded time window.
     */
    public function verify(string $secret, string $code, int $unixTimestamp, int $window = 1): bool
    {
        return $this->matchingCounter($secret, $code, $unixTimestamp, $window) !== null;
    }

    /** Return the highest matching time counter, or null when the code is invalid. */
    public function matchingCounter(string $secret, string $code, int $unixTimestamp, int $window = 1): ?int
    {
        if ($unixTimestamp < 0 || $window < 0 || $window > self::MAX_WINDOW || preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
            return null;
        }

        try {
            $key = $this->decodeBase32($secret);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (strlen($key) < 10) {
            return null;
        }

        $counter = intdiv($unixTimestamp, self::PERIOD_SECONDS);
        $matchedCounter = null;

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidateCounter = $counter + $offset;

            if ($candidateCounter < 0) {
                continue;
            }

            if (hash_equals($this->codeForCounter($key, $candidateCounter), $code)) {
                $matchedCounter = max($matchedCounter ?? 0, $candidateCounter);
            }
        }

        return $matchedCounter;
    }

    private function codeForCounter(string $key, int $counter): string
    {
        $message = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
        $digest = hash_hmac('sha1', $message, $key, true);
        $offset = ord($digest[19]) & 0x0F;
        $binary = unpack('Nvalue', substr($digest, $offset, 4));
        $number = $binary['value'] & 0x7FFFFFFF;
        $modulus = 10 ** self::DIGITS;

        return str_pad((string) ($number % $modulus), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function encodeBase32(string $bytes): string
    {
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        foreach (unpack('C*', $bytes) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 0x1F];
            }

            $buffer = $bits === 0 ? 0 : $buffer & ((1 << $bits) - 1);
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $encoded;
    }

    private function decodeBase32(string $secret): string
    {
        $secret = strtoupper($secret);

        if ($secret === '' || preg_match('/\A[A-Z2-7]+\z/D', $secret) !== 1) {
            throw new InvalidArgumentException('TOTP secret must be unpadded Base32.');
        }

        $decoded = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($secret) as $character) {
            $value = strpos(self::ALPHABET, $character);

            if ($value === false) {
                throw new InvalidArgumentException('TOTP secret contains an invalid Base32 character.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
                $buffer = $bits === 0 ? 0 : $buffer & ((1 << $bits) - 1);
            }
        }

        if ($bits >= 5 || ($bits > 0 && $buffer !== 0)) {
            throw new InvalidArgumentException('TOTP secret has an invalid Base32 encoding.');
        }

        return $decoded;
    }
}
