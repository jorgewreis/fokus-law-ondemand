<?php

namespace Tests\Feature;

use App\AccountRequestStatus;
use App\Mail\ConfirmAccountRequestEmail;
use App\Models\AccountRequest;
use App\Models\User;
use App\Support\PublicOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renders_example_dashboard_without_real_parties(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('A pauta do dia', false)
            ->assertSee('Réu A.')
            ->assertSee('Processo 0000xxx-xx')
            ->assertSee('Av. Exemplo, s/n — Centro')
            ->assertSee('(00) 0000-0000')
            ->assertSee('Números de exemplo')
            ->assertSee('Esqueci a senha')
            ->assertSee('Solicitar cadastro')
            ->assertSee('Ainda não tem acesso?')
            ->assertSee('Nome completo')
            ->assertSee('hero-courthouse.jpg', false)
            ->assertSee('hero-unit.jpg', false)
            ->assertDontSee('Mockup para aprovação')
            ->assertDontSee('ios1vcrime.com.br/', false);
    }

    public function test_prefixed_path_renders_the_same_landing(): void
    {
        $this->get('/novologin')
            ->assertOk()
            ->assertSee('A pauta do dia', false)
            ->assertSee('Réu E.');
    }

    public function test_public_origin_adds_the_novologin_prefix_once(): void
    {
        $this->assertSame(
            'https://ios1vcrime.com.br/novologin',
            PublicOrigin::from('https://ios1vcrime.com.br', 'novologin'),
        );
        $this->assertSame(
            'https://ios1vcrime.com.br/novologin',
            PublicOrigin::from('https://ios1vcrime.com.br/novologin/', 'novologin'),
        );
        $this->assertSame('http://localhost', PublicOrigin::from('http://localhost', ''));

        URL::useOrigin('http://localhost/novologin');

        $this->assertSame('http://localhost/novologin/login', route('login.store'));
    }

    public function test_responses_are_noindex_and_robots_disallows_everything(): void
    {
        $this->get('/')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/up')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('User-agent: *')
            ->assertSee('Disallow: /');

        $this->get('/novologin/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /');
    }

    public function test_content_security_policy_keeps_scripts_on_this_origin(): void
    {
        $policy = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("font-src 'self'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringNotContainsString('https://', $policy);
    }

    public function test_session_cookie_uses_its_own_name_and_the_novologin_path(): void
    {
        $this->assertSame('fokus_law_session', config('session.cookie'));
        $this->assertSame('/novologin', config('session.path'));

        config(['session.driver' => 'file']);

        $response = $this->get('/');
        $cookies = collect($response->headers->getCookies());

        $this->assertFalse($cookies->contains(fn ($cookie): bool => $cookie->getName() === 'PHPSESSID'));

        $session = $cookies->first(fn ($cookie): bool => $cookie->getName() === 'fokus_law_session');
        $this->assertNotNull($session);
        $this->assertSame('/novologin', $session->getPath());

        $xsrf = $cookies->first(fn ($cookie): bool => $cookie->getName() === 'XSRF-TOKEN');
        $this->assertNotNull($xsrf);
        $this->assertSame('/novologin', $xsrf->getPath());
    }

    public function test_login_accepts_matricula_without_mfa_and_still_requires_totp_when_enrolled(): void
    {
        $open = User::factory()->create([
            'email' => 'servidor@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);
        $open->forceFill([
            'matricula' => '123456',
            'is_active' => true,
            'mfa_enabled_at' => null,
            'mfa_reset_required' => false,
            'is_synthetic_admin' => false,
        ])->save();

        $this->post(route('login.store'), [
            'identifier' => '123456',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($open);

        auth()->logout();

        $protected = User::factory()->create([
            'email' => 'com-fator@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);
        $protected->forceFill([
            'matricula' => '654321',
            'is_active' => true,
            'mfa_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'mfa_enabled_at' => now(),
            'mfa_reset_required' => false,
        ])->save();

        $this->from(route('home'))->post(route('login.store'), [
            'identifier' => '654321',
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('home'))
            ->assertSessionHasErrors('auth');

        $this->assertGuest();
    }

    public function test_landing_cadastro_stores_matricula_and_sends_confirmation(): void
    {
        Mail::fake();

        $this->post(route('account-requests.store'), [
            'display_name' => 'Pessoa Sintética',
            'email' => 'cadastro.landing@example.test',
            'matricula' => '000111',
            'form' => 'cadastro',
            'return' => 'landing',
        ])->assertRedirect(route('home'))
            ->assertSessionHas('status');

        $request = AccountRequest::query()->where('email', 'cadastro.landing@example.test')->sole();
        $this->assertSame('000111', $request->matricula);
        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $request->status);
        $this->assertDatabaseCount('users', 0);
        Mail::assertSent(ConfirmAccountRequestEmail::class);
    }

    public function test_cadastro_fails_gracefully_when_mail_is_not_configured(): void
    {
        config(['mail.default' => 'sendmail']);

        $this->from(route('home'))->post(route('account-requests.store'), [
            'display_name' => 'Pessoa Sintética',
            'email' => 'sem-correio@example.test',
            'matricula' => '000222',
            'return' => 'landing',
        ])->assertRedirect(route('home'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('account_requests', 0);
    }
}
