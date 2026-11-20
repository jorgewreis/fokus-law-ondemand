<?php

namespace Tests\Unit;

use App\Security\TotpService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totp = new TotpService;
    }

    public function test_generated_secret_is_random_unpadded_base32_with_expected_entropy_length(): void
    {
        $first = $this->totp->generateSecret();
        $second = $this->totp->generateSecret();

        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_secret_generation_rejects_unsafe_lengths(): void
    {
        foreach ([0, 9, 129] as $bytes) {
            try {
                $this->totp->generateSecret($bytes);
                $this->fail('Expected the invalid secret length to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[DataProvider('rfc6238Sha1Vectors')]
    public function test_verifies_rfc_6238_sha1_test_vectors(int $timestamp, string $sixDigitCode): void
    {
        $this->assertTrue($this->totp->verify($this->rfcSecret(), $sixDigitCode, $timestamp, 0));
    }

    public static function rfc6238Sha1Vectors(): array
    {
        return [
            '59 seconds' => [59, '287082'],
            '1111111109 seconds' => [1111111109, '081804'],
            '1111111111 seconds' => [1111111111, '050471'],
            '1234567890 seconds' => [1234567890, '005924'],
            '2000000000 seconds' => [2000000000, '279037'],
            '20000000000 seconds' => [20000000000, '353130'],
        ];
    }

    public function test_accepts_only_codes_inside_the_configured_window(): void
    {
        $secret = $this->rfcSecret();

        $this->assertTrue($this->totp->verify($secret, '287082', 30, 1));
        $this->assertTrue($this->totp->verify($secret, '287082', 60, 1));
        $this->assertFalse($this->totp->verify($secret, '287082', 90, 1));
        $this->assertTrue($this->totp->verify($secret, '287082', 59, 0));
        $this->assertFalse($this->totp->verify($secret, '287082', 60, 0));
    }

    public function test_rejects_malformed_codes_secrets_timestamps_and_windows(): void
    {
        $secret = $this->rfcSecret();

        foreach (['28708', '1287082', ' 287082', '+28708', '12a082'] as $code) {
            $this->assertFalse($this->totp->verify($secret, $code, 59));
        }

        foreach (['', 'ABC0', 'ABC=', 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJR'] as $invalidSecret) {
            $this->assertFalse($this->totp->verify($invalidSecret, '287082', 59));
        }

        $this->assertFalse($this->totp->verify('MY', '287082', 59));
        $this->assertFalse($this->totp->verify($secret, '287082', -1));
        $this->assertFalse($this->totp->verify($secret, '287082', 59, -1));
        $this->assertFalse($this->totp->verify($secret, '287082', 59, 11));
    }

    public function test_generates_a_correctly_encoded_authenticator_uri(): void
    {
        $uri = $this->totp->provisioningUri('Fokus Law', 'jorge@example.test', $this->rfcSecret());

        $this->assertSame(
            'otpauth://totp/Fokus%20Law%3Ajorge%40example.test?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Fokus%20Law&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }

    public function test_rejects_empty_provisioning_identity_or_invalid_secret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->totp->provisioningUri('', 'jorge@example.test', $this->rfcSecret());
    }

    private function rfcSecret(): string
    {
        return 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    }
}
