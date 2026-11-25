<?php

namespace Tests\Feature;

use App\AccountRequestStatus;
use App\Models\AccountRequest;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyntheticAccountRequestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_one_unconfirmed_request_without_creating_an_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('account_requests', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('account_requests', [
            'display_name' => '[SINTÉTICO] Solicitação de conta pendente',
            'email' => 'solicitacao.sintetica@fokuslaw.test',
            'status' => AccountRequestStatus::PendingEmailConfirmation->value,
            'email_confirmed_at' => null,
        ]);

        $request = AccountRequest::query()->sole();

        $this->assertSame(AccountRequestStatus::PendingEmailConfirmation, $request->status);
        $this->assertTrue($request->expires_at->isFuture());
    }

    public function test_home_page_identifies_the_synthetic_environment_and_its_limits(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('A pauta do dia', false)
            ->assertSee('Não insira dados pessoais, processos reais ou informações do PJe.')
            ->assertSee('Números de exemplo');
    }
}
