<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use App\Services\SecrecyGrants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecrecyAdminController extends Controller
{
    public function index(SyntheticDatabaseGuard $guard): View
    {
        $guard->assertAllowed();
        $codes = DB::table('secrecy_catalog')->orderBy('code')->get();
        $catalogComplete = $codes->count() === 6 && $codes->values()->every(fn ($row, int $index): bool => (int) $row->code === $index && $row->label === "CNJ DataJud {$index}");

        return view('admin.secrecy.index', [
            'codes' => $codes,
            'catalogComplete' => $catalogComplete,
            'users' => User::query()->where('is_synthetic_admin', false)->with('secrecyGrants')->orderBy('email')->get(),
        ]);
    }

    public function grant(Request $request, User $user, SecrecyGrants $grants, SyntheticDatabaseGuard $guard): RedirectResponse
    {
        $guard->assertAllowed();
        $data = $request->validate(['secrecy' => ['required', 'integer', 'between:0,5'], 'operation' => ['required', 'in:grant,revoke']]);
        $grants->set($request->user(), $user, (int) $data['secrecy'], $data['operation'] === 'grant');

        return back()->with('status', $data['operation'] === 'grant' ? 'Sigilo concedido à conta.' : 'Concessão de sigilo revogada.');
    }
}
