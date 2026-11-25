<?php

namespace App\Http\Controllers;

use App\AccountRequestStatus;
use App\Mail\ConfirmAccountRequestEmail;
use App\Models\AccountRequest;
use App\Services\AccountAudit;
use App\Services\AccountRequestLifecycle;
use App\Support\MailTransport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'matricula' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\\-]{0,31}$/'],
        ], [
            'display_name.required' => 'Informe o nome completo.',
            'email.required' => 'Informe o e-mail institucional.',
            'email.email' => 'Informe um e-mail válido.',
            'matricula.regex' => 'A matrícula deve ter apenas letras, números, ponto ou hífen.',
            'matricula.max' => 'A matrícula deve ter no máximo 32 caracteres.',
        ]);

        $email = Str::lower(trim($validated['email']));
        $matricula = trim((string) ($validated['matricula'] ?? ''));
        $matricula = $matricula === '' ? null : $matricula;

        if (! MailTransport::configured()) {
            return $this->requestRedirect($request)->withInput()->withErrors([
                'email' => 'O envio de e-mail não está configurado. Defina MAIL_MAILER como smtp ou log. A solicitação não foi registrada.',
            ]);
        }

        $mailer = MailTransport::name();

        $accountRequestLifecycle->expirePending($email);

        $accountRequest = AccountRequest::query()->where('email', $email)->first();

        if ($accountRequest === null) {
            $expiresAt = now()->addDays(7);

            try {
                $accountRequest = DB::transaction(fn (): AccountRequest => AccountRequest::query()->create([
                    'display_name' => trim($validated['display_name']),
                    'email' => $email,
                    'matricula' => $matricula,
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

            try {
                Mail::mailer($mailer)->to($email)->send(new ConfirmAccountRequestEmail($confirmationUrl));
            } catch (\Throwable $exception) {
                report($exception);

                return $this->requestRedirect($request)->withErrors([
                    'email' => 'A solicitação foi registrada, mas a confirmação não pôde ser enviada. Tente de novo quando o e-mail estiver disponível.',
                ]);
            }
        }

        return $this->requestRedirect($request)->with('status', 'Solicitação registrada. Neste ambiente sintético, mensagens não são entregues; a confirmação é validada por testes simulados.');
    }

    public function showConfirmation(Request $request, AccountRequest $accountRequest, AccountRequestLifecycle $accountRequestLifecycle): Response
    {
        $this->assertValidConfirmationSignature($request, $accountRequest);
        $accountRequestLifecycle->expirePending($accountRequest->email);
        $accountRequest->refresh();

        abort_unless($accountRequest->status === AccountRequestStatus::PendingEmailConfirmation, 410);

        return response()->view('account-requests.confirm', [
            'accountRequest' => $accountRequest,
            'confirmationUrl' => $request->fullUrl(),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function confirm(Request $request, AccountRequest $accountRequest, AccountRequestLifecycle $accountRequestLifecycle): RedirectResponse
    {
        $this->assertValidConfirmationSignature($request, $accountRequest);
        $accountRequestLifecycle->expirePending($accountRequest->email);
        $accountRequest->refresh();

        abort_unless($accountRequest->status === AccountRequestStatus::PendingEmailConfirmation, 410);

        DB::transaction(function () use ($accountRequest): void {
            $locked = AccountRequest::query()->lockForUpdate()->findOrFail($accountRequest->getKey());
            abort_unless(
                $locked->status === AccountRequestStatus::PendingEmailConfirmation
                    && $locked->expires_at->isFuture(),
                410,
            );

            $locked->forceFill([
                'status' => AccountRequestStatus::PendingDeveloperApproval,
                'email_confirmed_at' => now(),
            ])->save();

            app(AccountAudit::class)->record(null, 'account_request.email_confirmed', 'account_request', $locked->getKey());
        });

        return redirect()->route('account-requests.create')->with('status', 'E-mail confirmado. Sua solicitação aguarda análise; nenhum acesso foi liberado.');
    }

    private function requestRedirect(Request $request): RedirectResponse
    {
        $target = $request->input('return') === 'landing'
            ? route('home')
            : route('account-requests.create');

        return redirect($target);
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
