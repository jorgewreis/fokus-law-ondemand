<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\PersonAccountLinking;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PersonAccountLinkAdminController extends Controller
{
    public function index(SyntheticDatabaseGuard $databaseGuard): View
    {
        $databaseGuard->assertAllowed();

        return view('admin.person-account-links.index', [
            'people' => Person::query()->whereNull('user_id')->orderBy('name')->limit(100)->get(['id', 'name', 'person_type']),
            'users' => User::query()->where('is_active', true)->where('is_synthetic_admin', false)
                ->whereNotIn('id', Person::query()->select('user_id')->whereNotNull('user_id'))
                ->orderBy('name')->limit(100)->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request, PersonAccountLinking $linking): RedirectResponse
    {
        try {
            $data = Validator::make($request->all(), [
                'person_id' => ['required', 'integer', 'min:1'],
                'user_id' => ['required', 'integer', 'min:1'],
            ])->validate();
            $linked = $linking->link($request->user(), (int) $data['person_id'], (int) $data['user_id']);
        } catch (AuthorizationException) {
            abort(404);
        }
        if (! $linked) {
            return back()->withErrors(['link' => 'Não foi possível concluir o vínculo. Verifique as opções e tente novamente.']);
        }

        return back()->with('status', 'Vínculo Conta–Pessoa registrado.');
    }
}
