<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicOriginProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::at(config('trustedproxy.proxies'));
        parent::tearDown();
    }

    public function test_origin_and_worker_requests_accept_the_public_signed_url(): void
    {
        config([
            'app.url' => 'https://ios1vcrime.com.br/novologin',
            'app.path_prefix' => 'novologin',
            'app.origin_host' => 'law.ios1vcrime.com.br',
        ]);
        URL::useOrigin('https://ios1vcrime.com.br/novologin');
        URL::forceScheme('https');
        TrustProxies::at(['127.0.0.1']);

        $accountRequest = AccountRequest::factory()->create();
        $signed = URL::temporarySignedRoute(
            'account-requests.confirm.show',
            $accountRequest->expires_at,
            ['accountRequest' => $accountRequest->getKey()],
        );

        $this->assertStringStartsWith('https://ios1vcrime.com.br/novologin/account-requests/', $signed);
        $query = parse_url($signed, PHP_URL_QUERY);
        $path = (string) parse_url($signed, PHP_URL_PATH);
        $appPath = preg_replace('#^/novologin#', '', $path) ?: '/';

        $this->withServerVariables(['HTTP_HOST' => 'law.ios1vcrime.com.br', 'HTTPS' => 'on'])
            ->get($appPath.'?'.$query)
            ->assertOk()
            ->assertSee('Confirmar meu e-mail');

        $this->withServerVariables(['HTTP_HOST' => 'law.ios1vcrime.com.br', 'HTTPS' => 'on'])
            ->withHeaders([
                'X-Forwarded-Host' => 'ios1vcrime.com.br',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Prefix' => '/novologin',
            ])
            ->get($appPath.'?'.$query)
            ->assertOk();
    }

    public function test_a_foreign_forwarded_host_does_not_replace_the_public_host(): void
    {
        config([
            'app.url' => 'https://ios1vcrime.com.br/novologin',
            'app.path_prefix' => 'novologin',
            'app.origin_host' => 'law.ios1vcrime.com.br',
        ]);
        URL::useOrigin('https://ios1vcrime.com.br/novologin');
        URL::forceScheme('https');
        TrustProxies::at(['127.0.0.1']);

        $accountRequest = AccountRequest::factory()->create();
        $signed = URL::temporarySignedRoute(
            'account-requests.confirm.show',
            $accountRequest->expires_at,
            ['accountRequest' => $accountRequest->getKey()],
        );
        $query = parse_url($signed, PHP_URL_QUERY);
        $path = (string) parse_url($signed, PHP_URL_PATH);
        $appPath = preg_replace('#^/novologin#', '', $path) ?: '/';

        $this->withServerVariables(['HTTP_HOST' => 'law.ios1vcrime.com.br'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'https'])
            ->get($appPath.'?'.$query)
            ->assertOk();
    }

    public function test_prefixed_health_check_responds(): void
    {
        $this->get('/novologin/up')->assertOk()->assertSee('ok');
    }

    public function test_phpsessid_is_not_a_session_cookie(): void
    {
        $response = $this->withCookie('PHPSESSID', 'legado')->get('/');

        $response->assertOk();
        $this->assertGuest();
        $names = collect($response->headers->getCookies())->map(fn ($cookie) => $cookie->getName());
        $this->assertFalse($names->contains('PHPSESSID'));
        $this->assertNotSame('PHPSESSID', config('session.cookie'));
        $this->assertSame('/novologin', config('session.path'));
        $this->assertNull(config('session.domain'));
    }
}
