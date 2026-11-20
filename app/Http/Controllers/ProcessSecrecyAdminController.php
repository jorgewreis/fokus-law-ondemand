<?php

namespace App\Http\Controllers;

use App\Security\SyntheticDatabaseGuard;
use App\Services\ProcessSecrecyClassification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProcessSecrecyAdminController extends Controller
{
    public function index(SyntheticDatabaseGuard $databaseGuard): View
    {
        $databaseGuard->assertAllowed();

        // Classification UI exposes only internal identifiers, never CNJ or case data.
        return view('admin.process-secrecy.index', [
            'processes' => DB::table('court_processes')->orderBy('id')->limit(100)->get(['id', 'class_code', 'competency_code', 'secrecy_code']),
            'codes' => DB::table('secrecy_catalog')->orderBy('code')->get(['code', 'label']),
        ]);
    }

    public function update(Request $request, int $processId, ProcessSecrecyClassification $classification): RedirectResponse
    {
        $data = $request->validate(['secrecy_code' => ['required', 'integer', 'between:0,5']]);
        $classification->classify($request->user(), $processId, (int) $data['secrecy_code']);

        return back()->with('status', 'A classificação foi processada.');
    }
}
