<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\User;
use App\Services\AccountAccess;
use App\Services\PersonAccessPolicy;
use App\Services\PersonService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request, PersonAccessPolicy $policy): View
    {
        abort_unless($policy->canRead($request->user()), 403);

        $people = Person::query()
            ->orderByDesc('created_at')
            ->limit(101)
            ->get(['id', 'person_type', 'name', 'created_by_user_id']);

        return view('people.index', ['people' => $people->take(100), 'hasMore' => $people->count() > 100, 'searchPerformed' => false]);
    }

    public function search(Request $request, PersonAccessPolicy $policy): View|RedirectResponse
    {
        abort_unless($policy->canRead($request->user()), 403);
        $name = $request->input('name');
        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 255) {
            return back()->withErrors(['name' => 'Informe o nome exato a consultar.'])->withInput([]);
        }

        $people = app(PersonService::class)->findMatchingNames($name);

        return view('people.index', ['people' => $people, 'hasMore' => false, 'searchPerformed' => true]);
    }

    public function create(Request $request, PersonAccessPolicy $policy): View
    {
        abort_unless($policy->canCreate($request->user()), 403);

        return view('people.form', ['person' => null]);
    }

    public function store(Request $request, PersonAccessPolicy $policy, PersonService $people): RedirectResponse
    {
        abort_unless($policy->canCreate($request->user()), 403);

        try {
            $person = $people->create($request->user(), $request->all(), $request->boolean('confirm_duplicate'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput([]);
        }

        return redirect()->route('people.show', $person)->with('status', 'Pessoa cadastrada.');
    }

    public function show(Request $request, int $personId, PersonAccessPolicy $policy, AccountAccess $access): View
    {
        abort_unless($policy->canRead($request->user()), 404);
        $person = Person::query()->findOrFail($personId);

        return view('people.show', [
            'person' => $person->load(['documents', 'phones', 'whatsappIdentifiers']),
            'canEdit' => $policy->canUpdate($request->user(), $person),
            'canCreateProcess' => $access->allows($request->user(), 'processes', 'create'),
            'canDelete' => $policy->canDelete($request->user(), $person),
        ]);
    }

    public function edit(Request $request, int $personId, PersonAccessPolicy $policy): View
    {
        abort_unless($policy->canRead($request->user()), 404);
        $person = Person::query()->findOrFail($personId);
        abort_unless($policy->canUpdate($request->user(), $person), 404);

        return view('people.form', ['person' => $person->load(['documents', 'phones', 'whatsappIdentifiers'])]);
    }

    public function update(Request $request, int $personId, PersonAccessPolicy $policy, PersonService $people): RedirectResponse
    {
        abort_unless($policy->canRead($request->user()), 404);
        $person = Person::query()->findOrFail($personId);
        abort_unless($policy->canUpdate($request->user(), $person), 404);

        try {
            $people->update($request->user(), $person, $request->all(), $request->boolean('confirm_duplicate'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput([]);
        }

        return redirect()->route('people.show', $person)->with('status', 'Pessoa atualizada.');
    }

    public function destroy(Request $request, int $personId, PersonAccessPolicy $policy, PersonService $people): RedirectResponse
    {
        abort_unless($policy->canRead($request->user()), 404);
        abort_unless($people->delete($request->user(), $personId), 404);

        return redirect()->route('people.index')->with('status', 'A Pessoa foi removida do cadastro operacional.');
    }

    public function lookupAddress(Request $request, PersonAccessPolicy $policy, PersonService $people): JsonResponse
    {
        $user = $request->user();
        abort_unless($policy->canCreate($user), 403);

        return $this->performAddressLookup($request, $user, $people);
    }

    public function lookupAddressForPerson(Request $request, int $personId, PersonAccessPolicy $policy, PersonService $people): JsonResponse
    {
        $user = $request->user();
        abort_unless($policy->canRead($user), 404);
        $person = Person::query()->findOrFail($personId);
        abort_unless($policy->canUpdate($user, $person), 404);

        return $this->performAddressLookup($request, $user, $people);
    }

    private function performAddressLookup(Request $request, User $user, PersonService $people): JsonResponse
    {

        try {
            $validated = Validator::make($request->only('cep'), [
                'cep' => ['required', 'string', 'regex:/\A[0-9]{5}-?[0-9]{3}\z/'],
            ])->validate();
        } catch (ValidationException) {
            return response()->json(['found' => false, 'message' => 'CEP inválido. Preencha o endereço manualmente.'], 422);
        }
        $cep = preg_replace('/\D/', '', $validated['cep']) ?? '';
        abort_unless(strlen($cep) === 8, 422);

        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)->get("https://viacep.com.br/ws/{$cep}/json/");
            $data = $response->successful() ? $response->json() : null;
        } catch (ConnectionException) {
            $data = null;
        }

        if (! is_array($data) || ($data['erro'] ?? false) === true) {
            $people->recordAddressLookupFailure($user);

            return response()->json(['found' => false, 'message' => 'Não foi possível localizar o CEP. Preencha o endereço manualmente.']);
        }

        $address = [
            'street' => $this->boundedString($data['logradouro'] ?? null, 255),
            'district' => $this->boundedString($data['bairro'] ?? null, 160),
            'city' => $this->boundedString($data['localidade'] ?? null, 160),
            'state' => $this->boundedString($data['uf'] ?? null, 2),
        ];

        if ($address['street'] === '' || $address['city'] === '' || ! preg_match('/\A[A-Z]{2}\z/', $address['state'])) {
            $people->recordAddressLookupFailure($user);

            return response()->json(['found' => false, 'message' => 'Não foi possível localizar o CEP. Preencha o endereço manualmente.']);
        }

        return response()->json(['found' => true, 'address' => $address]);
    }

    private function boundedString(mixed $value, int $length): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $length) : '';
    }
}
