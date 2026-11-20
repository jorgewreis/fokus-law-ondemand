<?php

namespace Database\Seeders;

use App\AccountRequestStatus;
use App\Models\AccountRequest;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Database\Seeder;

class SyntheticAccountRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        AccountRequest::query()->firstOrCreate(
            ['email' => 'solicitacao.sintetica@fokuslaw.test'],
            [
                'display_name' => '[SINTÉTICO] Solicitação de conta pendente',
                'status' => AccountRequestStatus::PendingEmailConfirmation,
                'email_confirmed_at' => null,
                'submitted_at' => now(),
                'expires_at' => now()->addDays(7),
            ],
        );
    }
}
