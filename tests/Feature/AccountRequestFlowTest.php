<?php

namespace Tests\Feature;

use App\AccountRequestStatus;
use App\Mail\ConfirmAccountRequestEmail;
use App\Models\AccountRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_sends_a_signed_confirmation_without_creating_an_account(): void
    {
        Mail::fake();

        $this->post(route('account-requests.store'), [
            'display_name' => 'Pessoa Sintética',
            'email' => 'synthetic.user@example.test',
        ])->assertRedirect(route('account-requests.create'))
            ->assertSessionHas('status', 'Solicitação registrada. Neste ambiente sintético, mensagens não são entregues; a confirmação é validada por testes simulados.');

        $request = AccountRequest::query()->sole();

        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $request->status);
        $this->assertNull($request->email_confirmed_at);
        $this->assertDatabaseCount('users', 0);
        Mail::assertSent(ConfirmAccountRequestEmail::class, fn (ConfirmAccountRequestEmail $mail): bool => $mail->hasTo('synthetic.user@example.test')
            && str_contains($mail->confirmationUrl, 'signature='));
    }

    public function test_confirmation_url_uses_the_configured_origin_instead_of_the_request_host(): void
    {
        Mail::fake();
        $canonicalOrigin = rtrim((string) config('app.url'), '/');

        $this->withServerVariables(['HTTP_HOST' => 'attacker.invalid'])
            ->post(route('account-requests.store'), [
                'display_name' => 'Pessoa Sintética',
                'email' => 'synthetic.user@example.test',
            ])
            ->assertRedirect($canonicalOrigin.'/account-requests/create');

        Mail::assertSent(ConfirmAccountRequestEmail::class, fn (ConfirmAccountRequestEmail $mail): bool => str_starts_with($mail->confirmationUrl, $canonicalOrigin.'/')
            && ! str_contains($mail->confirmationUrl, 'attacker.invalid'));
    }

    public function test_signed_get_does_not_confirm_until_the_person_submits_the_form(): void
    {
        $accountRequest = AccountRequest::factory()->create();
        $confirmationUrl = URL::temporarySignedRoute(
            'account-requests.confirm.show',
            $accountRequest->expires_at,
            ['accountRequest' => $accountRequest->getKey()],
        );

        $this->get($confirmationUrl)->assertOk()->assertSee('Confirmar meu e-mail');

        $accountRequest->refresh();
        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $accountRequest->status);
        $this->assertNull($accountRequest->email_confirmed_at);

        $this->post($confirmationUrl)
            ->assertRedirect(route('account-requests.create'))
            ->assertSessionHas('status', 'E-mail confirmado. Sua solicitação aguarda análise; nenhum acesso foi liberado.');

        $accountRequest->refresh();
        $this->assertSame(AccountRequestStatus::PendingDeveloperApproval, $accountRequest->status);
        $this->assertNotNull($accountRequest->email_confirmed_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_and_replayed_links_do_not_change_the_request(): void
    {
        $accountRequest = AccountRequest::factory()->create();
        $confirmationUrl = URL::temporarySignedRoute(
            'account-requests.confirm.show',
            $accountRequest->expires_at,
            ['accountRequest' => $accountRequest->getKey()],
        );

        $this->get($confirmationUrl.'&extra=invalid')->assertForbidden();
        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $accountRequest->refresh()->status);

        $this->post($confirmationUrl)->assertRedirect(route('account-requests.create'));
        $this->post($confirmationUrl)->assertGone();
        $this->assertSame(AccountRequestStatus::PendingDeveloperApproval, $accountRequest->refresh()->status);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_get_marks_the_request_expired_without_confirming_it(): void
    {
        $accountRequest = AccountRequest::factory()->create();
        $confirmationUrl = URL::temporarySignedRoute(
            'account-requests.confirm.show',
            $accountRequest->expires_at,
            ['accountRequest' => $accountRequest->getKey()],
        );

        $this->travelTo($accountRequest->expires_at->addSecond());

        $this->get($confirmationUrl)->assertGone();
        $this->assertSame(AccountRequestStatus::Expired, $accountRequest->refresh()->status);
        $this->assertNull($accountRequest->display_name);
        $this->assertNull($accountRequest->email);
        $this->assertNull($accountRequest->email_confirmed_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_post_marks_the_request_expired_without_confirming_it(): void
    {
        $accountRequest = AccountRequest::factory()->create();
        $confirmationUrl = URL::temporarySignedRoute(
            'account-requests.confirm.show',
            $accountRequest->expires_at,
            ['accountRequest' => $accountRequest->getKey()],
        );

        $this->travelTo($accountRequest->expires_at->addSecond());

        $this->post($confirmationUrl)->assertGone();

        $this->assertSame(AccountRequestStatus::Expired, $accountRequest->refresh()->status);
        $this->assertNull($accountRequest->display_name);
        $this->assertNull($accountRequest->email);
        $this->assertNull($accountRequest->email_confirmed_at);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_hourly_expiry_command_scrubs_all_old_pending_requests(): void
    {
        $expiredConfirmation = AccountRequest::factory()->create([
            'submitted_at' => now()->subDays(8),
            'expires_at' => now()->subSecond(),
        ]);
        $expiredApproval = AccountRequest::factory()->create([
            'status' => AccountRequestStatus::PendingDeveloperApproval,
            'email_confirmed_at' => now()->subDays(7),
            'submitted_at' => now()->subDays(8),
            'expires_at' => now()->subSecond(),
        ]);
        $activeRequest = AccountRequest::factory()->create();

        $this->artisan('account-requests:expire')
            ->expectsOutput('Expired 2 account requests.')
            ->assertExitCode(0);

        foreach ([$expiredConfirmation, $expiredApproval] as $accountRequest) {
            $accountRequest->refresh();

            $this->assertSame(AccountRequestStatus::Expired, $accountRequest->status);
            $this->assertNull($accountRequest->display_name);
            $this->assertNull($accountRequest->email);
        }

        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $activeRequest->refresh()->status);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_email_can_submit_a_new_independent_request(): void
    {
        Mail::fake();

        $expiredRequest = AccountRequest::factory()->create([
            'email' => 'synthetic.user@example.test',
            'submitted_at' => now()->subDays(8),
            'expires_at' => now()->subSecond(),
        ]);

        $this->post(route('account-requests.store'), [
            'display_name' => 'Novo Pedido Sintético',
            'email' => $expiredRequest->email,
        ])->assertRedirect(route('account-requests.create'));

        $expiredRequest->refresh();
        $this->assertSame(AccountRequestStatus::Expired, $expiredRequest->status);
        $this->assertNull($expiredRequest->display_name);
        $this->assertNull($expiredRequest->email);

        $replacement = AccountRequest::query()->where('email', 'synthetic.user@example.test')->sole();
        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $replacement->status);
        $this->assertSame('Novo Pedido Sintético', $replacement->display_name);
        $this->assertDatabaseCount('users', 0);
    }
}
