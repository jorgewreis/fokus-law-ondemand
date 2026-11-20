<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PersonDocument;
use App\Models\PersonPhone;
use App\Models\PersonWhatsappIdentifier;
use App\Models\User;
use App\Security\SyntheticDatabaseGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PersonService
{
    private const DOCUMENT_TYPES = [
        'RG', 'CPF', 'CNPJ', 'INSCRICAO_ESTADUAL', 'PASSAPORTE', 'CNH',
        'TITULO_ELEITOR', 'PIS', 'CAD', 'MAT', 'CIN',
    ];

    private const PF_DOCUMENTS = ['RG', 'CPF', 'PASSAPORTE', 'CNH', 'TITULO_ELEITOR', 'PIS', 'MAT', 'CIN', 'CAD'];

    private const PJ_DOCUMENTS = ['CNPJ', 'INSCRICAO_ESTADUAL', 'CAD'];

    private const UF_CODES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function __construct(
        private AccountAudit $audit,
        private PersonDocumentValidator $documents,
        private BrazilianPhoneNumber $phones,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(User $actor, array $input, bool $confirmedNameDuplicate): Person
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        $validated = $this->validate($input, true);
        $this->validateTypeRules($validated);
        $duplicateName = $this->hasNameDuplicate($validated['name']);
        if ($duplicateName && ! $confirmedNameDuplicate) {
            throw ValidationException::withMessages(['name' => 'Há possível duplicidade de nome. Confirme para cadastrar uma Pessoa distinta.']);
        }

        return DB::transaction(function () use ($actor, $validated, $duplicateName): Person {
            $person = Person::query()->create([
                'created_by_user_id' => $actor->getKey(),
                'person_type' => $validated['person_type'],
                'name' => $validated['name'],
                'postal_code' => $this->digits($validated['address']['postal_code']),
                'street' => $validated['address']['street'],
                'address_number' => $validated['address']['number'],
                'city' => $validated['address']['city'],
                'state' => strtoupper($validated['address']['state']),
                'district' => $validated['address']['district'] ?? null,
                'complement' => $validated['address']['complement'] ?? null,
            ]);
            $this->replaceChildren($person, $validated);

            if ($duplicateName) {
                $this->audit->record($actor, 'person.duplicate_name.confirmed', 'person', $person->getKey(), 'success', 'people', 'create');
            }

            return $person->load(['documents', 'phones', 'whatsappIdentifiers']);
        });
    }

    /** @param array<string, mixed> $input */
    public function update(User $actor, Person $person, array $input, bool $confirmedNameDuplicate): Person
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        $validated = $this->validate($input, false, $person->person_type);
        $this->validateTypeRules($validated);
        $duplicateName = $this->hasNameDuplicate($validated['name'], $person->getKey());
        if ($duplicateName && ! $confirmedNameDuplicate) {
            throw ValidationException::withMessages(['name' => 'Há possível duplicidade de nome. Confirme para manter uma Pessoa distinta.']);
        }

        return DB::transaction(function () use ($actor, $person, $validated, $duplicateName): Person {
            $locked = Person::query()->lockForUpdate()->findOrFail($person->getKey());
            $locked->fill([
                'name' => $validated['name'],
                'postal_code' => $this->digits($validated['address']['postal_code']),
                'street' => $validated['address']['street'],
                'address_number' => $validated['address']['number'],
                'city' => $validated['address']['city'],
                'state' => strtoupper($validated['address']['state']),
                'district' => $validated['address']['district'] ?? null,
                'complement' => $validated['address']['complement'] ?? null,
            ])->save();
            $this->replaceChildren($locked, $validated);

            if ($duplicateName) {
                $this->audit->record($actor, 'person.duplicate_name.confirmed', 'person', $locked->getKey(), 'success', 'people', 'update');
            }

            return $locked->load(['documents', 'phones', 'whatsappIdentifiers']);
        });
    }

    public function recordAddressLookupFailure(User $actor): void
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();
        $this->audit->record($actor, 'person.address.lookup_failed', null, null, 'failure', 'people', 'create');
    }

    public function delete(User $actor, int $personId): bool
    {
        app(SyntheticDatabaseGuard::class)->assertAllowed();

        return DB::transaction(function () use ($actor, $personId): bool {
            $currentActor = User::query()->lockForUpdate()->find($actor->getKey());
            $person = Person::query()->lockForUpdate()->find($personId);
            if ($currentActor === null || $person === null || ! app(PersonAccessPolicy::class)->canDelete($currentActor, $person)) {
                return false;
            }

            $this->audit->record($currentActor, 'person.deleted', null, null, 'success', 'people', 'delete');
            $person->whatsappIdentifiers()->delete();
            $person->phones()->delete();
            $person->documents()->delete();
            $person->delete();

            return true;
        });
    }

    private function validate(array $input, bool $creating, ?string $personType = null): array
    {
        if (isset($input['address']['state']) && is_string($input['address']['state'])) {
            $input['address']['state'] = strtoupper($input['address']['state']);
        }
        if (isset($input['documents']) && is_array($input['documents'])) {
            foreach ($input['documents'] as &$document) {
                if (isset($document['issuing_state']) && is_string($document['issuing_state'])) {
                    $document['issuing_state'] = strtoupper($document['issuing_state']);
                }
            }
            unset($document);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'array'],
            'address.postal_code' => ['required', 'string', 'regex:/\A[0-9]{5}-?[0-9]{3}\z/'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.number' => ['required', 'string', 'max:64'],
            'address.city' => ['required', 'string', 'max:160'],
            'address.state' => ['required', 'string', 'size:2', 'in:'.implode(',', self::UF_CODES)],
            'address.district' => ['nullable', 'string', 'max:160'],
            'address.complement' => ['nullable', 'string', 'max:160'],
            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['required', 'string', 'in:'.implode(',', self::DOCUMENT_TYPES), 'distinct'],
            'documents.*.number' => ['required', 'string', 'max:255'],
            'documents.*.issuing_authority' => ['nullable', 'string', 'max:120'],
            'documents.*.issuing_state' => ['nullable', 'string', 'size:2', 'in:'.implode(',', self::UF_CODES)],
            'phones' => ['nullable', 'array'],
            'phones.*.number' => ['required', 'string', 'max:40'],
            'phones.*.category' => ['required', 'string', 'in:Celular,Fixo'],
            'phones.*.is_primary' => ['sometimes', 'boolean'],
            'whatsapp' => ['nullable', 'array'],
            'whatsapp.*.type' => ['required', 'string', 'in:phone,username'],
            'whatsapp.*.value' => ['required', 'string', 'max:255'],
            'whatsapp.*.user_key' => ['nullable', 'string', 'max:255'],
            'whatsapp.*.is_primary' => ['sometimes', 'boolean'],
        ];
        if ($creating) {
            $rules['person_type'] = ['required', 'string', 'in:PF,PJ'];
        } else {
            $rules['person_type'] = ['prohibited'];
        }

        $validated = Validator::make($input, $rules)->validate();
        $validated['person_type'] = $personType ?? $validated['person_type'];
        $validated['documents'] ??= [];
        $validated['phones'] ??= [];
        $validated['whatsapp'] ??= [];

        if (strlen($this->digits($validated['address']['postal_code'])) !== 8) {
            throw ValidationException::withMessages(['address.postal_code' => 'Informe um CEP com oito dígitos.']);
        }

        $phonePrimaryCount = collect($validated['phones'])->filter(fn (array $phone): bool => filter_var($phone['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();
        if (($validated['phones'] !== [] && $phonePrimaryCount !== 1) || ($validated['phones'] === [] && $phonePrimaryCount > 0)) {
            throw ValidationException::withMessages(['phones' => 'Quando houver telefone, marque exatamente um como principal.']);
        }

        $whatsappPrimaryCount = collect($validated['whatsapp'])->filter(fn (array $item): bool => filter_var($item['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();
        if (($validated['whatsapp'] !== [] && $whatsappPrimaryCount !== 1) || ($validated['whatsapp'] === [] && $whatsappPrimaryCount > 0)) {
            throw ValidationException::withMessages(['whatsapp' => 'Quando houver identificador WhatsApp, marque exatamente um como principal.']);
        }

        return $validated;
    }

    private function validateTypeRules(array $validated): void
    {
        $allowed = $validated['person_type'] === 'PF' ? self::PF_DOCUMENTS : self::PJ_DOCUMENTS;
        foreach ($validated['documents'] as $index => $document) {
            if (! in_array($document['type'], $allowed, true)) {
                throw ValidationException::withMessages(["documents.{$index}.type" => 'O tipo documental não se aplica à classificação desta Pessoa.']);
            }
            if ($document['type'] === 'CPF' && ! $this->documents->validCpf($document['number'])) {
                throw ValidationException::withMessages(["documents.{$index}.number" => 'Informe um CPF com formato e dígitos verificadores válidos.']);
            }
            if ($document['type'] === 'CNPJ' && ! $this->documents->validCnpj($document['number'])) {
                throw ValidationException::withMessages(["documents.{$index}.number" => 'Informe um CNPJ com formato e dígitos verificadores válidos.']);
            }
        }

        foreach ($validated['phones'] as $index => $phone) {
            $this->phones->normalize($phone['number'], $phone['category']);
        }
        foreach ($validated['whatsapp'] as $index => $item) {
            if ($item['type'] === 'phone') {
                $this->phones->normalizeWhatsapp($item['value']);
                if (isset($item['user_key']) && trim($item['user_key']) !== '') {
                    throw ValidationException::withMessages(["whatsapp.{$index}.user_key" => 'A chave do usuário se aplica a identificadores por nome de usuário.']);
                }
            }
        }
    }

    /** @return Collection<int, Person> */
    public function findMatchingNames(string $name): Collection
    {
        $normalized = $this->normalizeNameForComparison($name);

        return Person::query()->orderBy('name')->get(['id', 'person_type', 'name', 'created_by_user_id'])
            ->filter(fn (Person $person): bool => $this->normalizeNameForComparison($person->name) === $normalized)
            ->values();
    }

    private function hasNameDuplicate(string $name, ?int $exceptId = null): bool
    {
        return $this->findMatchingNames($name)->contains(fn (Person $person): bool => (int) $person->getKey() !== $exceptId);
    }

    private function normalizeNameForComparison(string $name): string
    {
        $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', trim($name)) ?? trim($name);

        return mb_strtolower(trim($collapsed), 'UTF-8');
    }

    private function replaceChildren(Person $person, array $validated): void
    {
        $person->whatsappIdentifiers()->delete();
        $person->documents()->delete();
        $person->phones()->delete();

        foreach ($validated['documents'] as $document) {
            PersonDocument::query()->create([
                'person_id' => $person->getKey(),
                'type' => $document['type'],
                'number' => $document['number'],
                'issuing_authority' => $document['issuing_authority'] ?? null,
                'issuing_state' => isset($document['issuing_state']) ? strtoupper($document['issuing_state']) : null,
            ]);
        }

        $phoneIdsByDigits = [];
        foreach ($validated['phones'] as $index => $phone) {
            $model = PersonPhone::query()->create([
                'person_id' => $person->getKey(),
                'number' => $this->phones->normalize($phone['number'], $phone['category']),
                'category' => $phone['category'],
                'is_primary' => filter_var($phone['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
            $phoneIdsByDigits[$model->number] = $model->getKey();
        }

        foreach ($validated['whatsapp'] as $item) {
            $value = $item['type'] === 'phone'
                ? $this->phones->normalizeWhatsapp($item['value'])
                : $item['value'];
            PersonWhatsappIdentifier::query()->create([
                'person_id' => $person->getKey(),
                'type' => $item['type'],
                'value' => $value,
                'user_key' => $item['user_key'] ?? null,
                'phone_id' => $item['type'] === 'phone' ? ($phoneIdsByDigits[$value] ?? null) : null,
                'is_primary' => filter_var($item['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
