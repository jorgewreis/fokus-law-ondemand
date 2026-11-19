<?php

namespace App\Http\Controllers;

use App\AccountRequestStatus;
use App\Mail\ConfirmAccountRequestEmail;
use App\Models\AccountRequest;
use App\Services\AccountRequestLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountRequestController extends Controller
{
    public function create(): View
    {
        return view('account-requests.create');
    }

    public function store(Request $request, AccountRequestLifecycle $accountRequestLifecycle): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if (config('mail.default') !== 'array') {
            abort(503, 'Este fluxo sintético só aceita o transporte array; e-mail real e links em logs estão desativados.');
        }

        $accountRequestLifecycle->expirePending($email);

        $accountRequest = AccountRequest::query()->where('email', $email)->first();

        if ($accountRequest === null) {
            $expiresAt = now()->addDays(7);

            try {
                $accountRequest = DB::transaction(fn (): AccountRequest => AccountRequest::query()->create([
                    'display_name' => trim($validated['display_name']),
                    'email' => $email,
                    'status' => AccountRequestStatus::PendingEmailConfirmation,
                    'submitted_at' => now(),
                    'expires_at' => $expiresAt,
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                if (! AccountRequest::query()->where('email', $email)->exists()) {
                    throw $exception;
                }

                $accountRequest = null;
            }
        }

        if ($accountRequest?->status === AccountRequestStatus::PendingEmailConfirmation && $accountRequest->expires_at->isFuture()) {
            $confirmationUrl = URL::temporarySignedRoute(
                'account-requests.confirm.show',
                $accountRequest->expires_at,
                ['accountRequest' => $accountRequest->getKey()],
            );

            Mail::mailer('array')->to($email)->send(new ConfirmAccountRequestEmail($confirmationUrl));
        }

        return redirect()->route('account-requests.create')->with('status', 'Solicitação registrada. Neste ambiente sintético, mensagens não são entregues; a confirmação é validada por testes simulados.');
    }

    public function showConfirmation(Request $request, AccountRequest $accountRequest, AccountRequestLifecycle $accountRequestLifecycle): View
    {
        $this->assertValidConfirmationSignature($request, $accountRequest);
        $accountRequestLifecycle->expirePending($accountRequest->email);
        $accountRequest->refresh();

        abort_unless($accountRequest->status === AccountRequestStatus::PendingEmailConfirmation, 410);

        return view('account-requests.confirm', [
            'accountRequest' => $accountRequest,
            'confirmationUrl' => $request->fullUrl(),
        ]);
    }

    public function confirm(Request $request, AccountRequest $accountRequest, AccountRequestLifecycle $accountRequestLifecycle): RedirectResponse
    {
        $this->assertValidConfirmationSignature($request, $accountRequest);
        $accountRequestLifecycle->expirePending($accountRequest->email);
        $accountRequest->refresh();

        abort_unless($accountRequest->status === AccountRequestStatus::PendingEmailConfirmation, 410);

        $now = now();

        $updated = AccountRequest::query()
            ->whereKey($accountRequest->getKey())
            ->where('status', AccountRequestStatus::PendingEmailConfirmation->value)
            ->where('expires_at', '>', $now)
            ->update([
                'status' => AccountRequestStatus::PendingDeveloperApproval->value,
                'email_confirmed_at' => $now,
                'updated_at' => $now,
            ]);

        abort_unless($updated === 1, 410);

        return redirect()->route('account-requests.create')->with('status', 'E-mail confirmado. Sua solicitação aguarda análise; nenhum acesso foi liberado.');
    }

    private function assertValidConfirmationSignature(Request $request, AccountRequest $accountRequest): void
    {
        $expires = $request->query('expires');

        abort_unless(
            is_string($expires)
                && ctype_digit($expires)
                && (int) $expires === $accountRequest->expires_at->getTimestamp()
                && URL::hasCorrectSignature($request),
            403,
        );
    }
}
