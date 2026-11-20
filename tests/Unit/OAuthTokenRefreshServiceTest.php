<?php

namespace Tests\Unit;

use App\Contracts\OAuthTokenRefresher;
use App\Contracts\OAuthTokenRefreshMutex;
use App\Data\OAuthTokenSet;
use App\Exceptions\OAuthRefreshTokenRevoked;
use App\Models\OAuthCredential;
use App\Services\OAuthTokenRefreshService;
use Closure;
use Fiber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class OAuthTokenRefreshServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
        config(['services.oauth.lock_store' => 'database']);
        config(['cache.stores.database.connection' => null]);
        Cache::purge('database');
    }

    public function test_refreshes_at_the_five_minute_boundary_and_persists_rotated_tokens_encrypted(): void
    {
        $credential = $this->credential(expiresAt: now()->addMinutes(5));
        $refresher = Mockery::mock(OAuthTokenRefresher::class);
        $refresher->shouldReceive('refresh')->once()->with('provider-x', 'old-refresh')
            ->andReturn(new OAuthTokenSet('new-access', 'new-refresh', 3600));

        $updated = $this->service($refresher)->refreshIfExpiring($credential);
        $stored = $updated->getRawOriginal();

        $this->assertSame('new-access', $updated->access_token);
        $this->assertSame('new-refresh', $updated->refresh_token);
        $this->assertTrue($updated->expires_at->isAfter(now()->addMinutes(59)));
        $this->assertNotSame('new-access', $stored['access_token']);
        $this->assertNotSame('new-refresh', $stored['refresh_token']);
        $this->assertSame('new-access', Crypt::decryptString($stored['access_token']));
        $this->assertSame('new-refresh', Crypt::decryptString($stored['refresh_token']));
    }

    public function test_does_not_refresh_a_token_with_more_than_five_minutes_remaining(): void
    {
        $credential = $this->credential(expiresAt: now()->addMinutes(5)->addSecond());
        $refresher = Mockery::mock(OAuthTokenRefresher::class);
        $refresher->shouldNotReceive('refresh');

        $updated = $this->service($refresher)->refreshIfExpiring($credential);

        $this->assertSame('old-access', $updated->access_token);
        $this->assertNull($updated->refreshed_at);
    }

    public function test_competing_calls_recheck_the_persisted_expiry_and_only_refresh_once(): void
    {
        $credential = $this->credential(expiresAt: now()->addMinutes(4));
        $staleCopy = $credential->replicate();
        $staleCopy->setAttribute($credential->getKeyName(), $credential->getKey());
        $refresher = Mockery::mock(OAuthTokenRefresher::class);
        $refresher->shouldReceive('refresh')->once()->andReturnUsing(function (): OAuthTokenSet {
            Fiber::suspend();

            return new OAuthTokenSet('shared-access', 'shared-refresh', 3600);
        });
        $service = $this->service($refresher, new class implements OAuthTokenRefreshMutex
        {
            private bool $locked = false;

            /** @var list<Fiber> */
            private array $waiting = [];

            public function synchronized(string $credentialId, Closure $callback): mixed
            {
                if ($this->locked) {
                    $this->waiting[] = Fiber::getCurrent();
                    Fiber::suspend();

                    return $callback();
                }

                $this->locked = true;

                try {
                    return $callback();
                } finally {
                    $this->locked = false;

                    foreach ($this->waiting as $fiber) {
                        if ($fiber->isSuspended()) {
                            $fiber->resume();
                        }
                    }

                    $this->waiting = [];
                }
            }
        });

        $firstFiber = new Fiber(fn (): OAuthCredential => $service->refreshIfExpiring($credential));
        $secondFiber = new Fiber(fn (): OAuthCredential => $service->refreshIfExpiring($staleCopy));
        $firstFiber->start();
        $secondFiber->start();
        $firstFiber->resume();

        $first = $firstFiber->getReturn();
        $second = $secondFiber->getReturn();

        $this->assertSame('shared-access', $first->access_token);
        $this->assertSame('shared-access', $second->access_token);
        $this->assertSame('shared-refresh', $second->refresh_token);
    }

    public function test_marks_a_revoked_refresh_token_and_requires_reauthorization(): void
    {
        $credential = $this->credential(expiresAt: now()->addMinute());
        $refresher = Mockery::mock(OAuthTokenRefresher::class);
        $refresher->shouldReceive('refresh')->once()->andThrow(new OAuthRefreshTokenRevoked);

        try {
            $this->service($refresher)->refreshIfExpiring($credential);
            $this->fail('Expected a revoked refresh token to require reauthorization.');
        } catch (OAuthRefreshTokenRevoked) {
            $this->assertNotNull($credential->fresh()->revoked_at);
            $this->assertNull($credential->fresh()->refresh_token);
        }
    }

    public function test_missing_refresh_token_marks_credential_for_reauthorization_without_calling_adapter(): void
    {
        $credential = $this->credential(expiresAt: now()->addMinute());
        $credential->forceFill(['refresh_token' => null])->save();
        $refresher = Mockery::mock(OAuthTokenRefresher::class);
        $refresher->shouldNotReceive('refresh');

        try {
            $this->service($refresher)->refreshIfExpiring($credential);
            $this->fail('Expected a missing refresh token to require reauthorization.');
        } catch (OAuthRefreshTokenRevoked) {
            $this->assertNotNull($credential->fresh()->revoked_at);
        }
    }

    private function credential(Carbon $expiresAt): OAuthCredential
    {
        return OAuthCredential::query()->create([
            'credential_key' => 'integration-test',
            'provider_key' => 'provider-x',
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'expires_at' => $expiresAt,
        ]);
    }

    private function service(OAuthTokenRefresher $refresher, ?OAuthTokenRefreshMutex $mutex = null): OAuthTokenRefreshService
    {
        return new OAuthTokenRefreshService($refresher, $mutex ?? app(OAuthTokenRefreshMutex::class));
    }
}
