<?php

namespace App\Http\Controllers;

use App\Models\AccountGrant;
use App\Models\AccountRequest;
use App\Models\MfaRecoveryRequest;
use App\Models\User;
use App\Services\AccountActivation;
use App\Services\AccountAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.accounts.index', [
            'requests' => AccountRequest::query()->where('status', 'pending_developer_approval')->orderBy('submitted_at')->get(),
            'users' => User::query()->with('grants')->where('is_synthetic_admin', false)->orderBy('email')->get(),
            'recoveryRequests' => MfaRecoveryRequest::query()->where('status', 'pending')->with('user')->orderBy('requested_at')->get(),
            'modules' => config('access.modules'),
            'actions' => config('access.actions'),
            'grantActions' => config('access.grantable'),
        ]);
    }

    public function approve(Request $request, AccountRequest $accountRequest, AccountActivation $activation): RedirectResponse
    {
        $activation->approve($accountRequest, $request->user());

        return back()->with('status', 'Solicitação aprovada. O convite foi emitido e vence em 24 horas; nenhuma permissão foi concedida.');
    }

    public function reject(Request $request, AccountRequest $accountRequest, AccountActivation $activation): RedirectResponse
    {
        $activation->reject($accountRequest, $request->user());

        return back()->with('status', 'Solicitação rejeitada.');
    }

    public function reissue(Request $request, User $user, AccountActivation $activation): RedirectResponse
    {
        $activation->reissue($user, $request->user());

        return back()->with('status', 'Novo convite emitido por 24 horas. O convite anterior foi invalidado.');
    }

    public function grants(Request $request, User $user, AccountAudit $audit): RedirectResponse
    {
        $allowedGrants = collect(config('access.grantable'))
            ->flatMap(fn (array $actions, string $module): array => array_map(fn (string $action): string => $module.'.'.$action, $actions))
            ->all();

        $validated = $request->validate([
            'grants' => ['sometimes', 'array', 'max:40'],
            'grants.*' => ['required', 'string', Rule::in($allowedGrants)],
        ]);

        $desired = collect($validated['grants'] ?? [])
            ->map(function (string $grant): array {
                [$module, $action] = explode('.', $grant, 2);

                return ['module' => $module, 'action' => $action];
            })
            ->unique(fn (array $grant): string => $grant['module'].'.'.$grant['action'])
            ->values();

        DB::transaction(function () use ($request, $user, $desired, $audit): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            abort_unless(! $locked->is_synthetic_admin, 403);

            $current = AccountGrant::query()->where('user_id', $locked->getKey())->get();
            $currentKeys = $current->map(fn (AccountGrant $grant): string => $grant->module.'.'.$grant->action)->all();
            $desiredKeys = $desired->map(fn (array $grant): string => $grant['module'].'.'.$grant['action'])->all();

            foreach ($current as $grant) {
                $key = $grant->module.'.'.$grant->action;
                if (! in_array($key, $desiredKeys, true)) {
                    $grant->delete();
                    $audit->record($request->user(), 'account_grant.revoked', 'user', $locked->getKey(), 'success', $grant->module, $grant->action);
                }
            }

            foreach ($desired as $grant) {
                $key = $grant['module'].'.'.$grant['action'];
                if (! in_array($key, $currentKeys, true)) {
                    AccountGrant::query()->create([
                        'user_id' => $locked->getKey(),
                        'module' => $grant['module'],
                        'action' => $grant['action'],
                        'granted_by_user_id' => $request->user()->getKey(),
                    ]);
                    $audit->record($request->user(), 'account_grant.granted', 'user', $locked->getKey(), 'success', $grant['module'], $grant['action']);
                }
            }
        });

        return back()->with('status', 'Concessões explícitas atualizadas.');
    }
}
