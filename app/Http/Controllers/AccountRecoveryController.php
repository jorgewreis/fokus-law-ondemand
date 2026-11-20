<?php

namespace App\Http\Controllers;

use App\Models\MfaRecoveryRequest;
use App\Models\User;
use App\Services\AccountAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountRecoveryController extends Controller
{
    public function create(): View
    {
        return view('account-recovery.create');
    }

    public function store(Request $request, AccountAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $user = User::query()->where('email', mb_strtolower(trim($validated['email'])))->first();

        if ($user !== null && $user->is_active && $user->mfa_enabled_at !== null && Hash::check($validated['password'], $user->password)) {
            DB::transaction(function () use ($user, $audit): void {
                $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $existing = MfaRecoveryRequest::query()
                    ->where('user_id', $locked->getKey())
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    $recoveryRequest = MfaRecoveryRequest::query()->create([
                        'user_id' => $locked->getKey(),
                        'status' => 'pending',
                        'requested_at' => now(),
                    ]);
                    $audit->record($locked, 'mfa.recovery_requested', 'mfa_recovery_request', $recoveryRequest->getKey(), 'pending');
                }
            });
        }

        return back()->with('status', 'Se os dados corresponderem a uma conta ativa, o pedido de recuperação foi registrado. Jorge confirmará o contato previamente conhecido antes de qualquer redefinição.');
    }
}
