<?php

namespace Tests\Feature;

use App\Models\AccountAuditEvent;
use App\Models\AccountGrant;
use App\Models\CourtProcess;
use App\Models\Person;
use App\Models\PersonDocument;
use App\Models\PersonPhone;
use App\Models\PersonProcess;
use App\Models\PersonWhatsappIdentifier;
use App\Models\User;
use App\Services\BrazilianPhoneNumber;
use App\Services\PersonAccessPolicy;
use App\Services\PersonDocumentValidator;
use App\Services\ProcessUnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PeopleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function account(array $grants = [], bool $admin = false): User
    {
        $grantor = User::factory()->create();
        $grantor->forceFill(['is_synthetic_admin' => true])->save();
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()->subDay()])->save();
        foreach ($grants as [$module, $action]) {
            AccountGrant::query()->create(['user_id' => $user->getKey(), 'module' => $module, 'action' => $action, 'granted_by_user_id' => $grantor->getKey()]);
        }

        return $user;
    }

    private function mfa(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.mfa_verified_at' => now()->timestamp]);
    }

    /** @return array<string, mixed> */
    private function validPayload(string $name = 'Pessoa Sintética'): array
    {
        return [
            'person_type' => 'PF',
            'name' => $name,
            'address' => ['postal_code' => '45653-240', 'street' => 'Rua Sintética', 'number' => '10', 'city' => 'Ilhéus', 'state' => 'BA', 'district' => 'Centro', 'complement' => 'Sala 1'],
            'documents' => [['type' => 'CPF', 'number' => '529.982.247-25', 'issuing_authority' => null, 'issuing_state' => null]],
            'phones' => [['number' => '(73) 99123-4567', 'category' => 'Celular', 'is_primary' => '1']],
            'whatsapp' => [['type' => 'phone', 'value' => '73 99123-4567', 'user_key' => '', 'is_primary' => '1']],
        ];
    }

    public function test_person_module_requires_explicit_read_and_create_grants(): void
    {
        $this->get(route('people.create'))->assertRedirect(route('login.create'));
        $this->post(route('people.search'), ['name' => 'Nome privado'])->assertRedirect(route('login.create'));
        $withoutGrant = $this->account();
        $this->mfa($withoutGrant)->get(route('people.index'))->assertForbidden();

        $createWithoutRead = $this->account([['people', 'create']]);
        $this->mfa($createWithoutRead)->get(route('people.create'))->assertForbidden();
        $this->mfa($createWithoutRead)->post(route('people.store'), $this->validPayload())->assertForbidden();

        $reader = $this->account([['people', 'read']]);
        $this->mfa($reader)->get(route('people.index'))->assertOk();
        $this->mfa($reader)->get(route('people.create'))->assertForbidden();
    }

    public function test_create_stores_encrypted_documents_and_normalized_phone_with_audit_metadata_only(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        $response = $this->mfa($actor)->post(route('people.store'), $this->validPayload());

        $response->assertRedirect();
        $person = Person::query()->firstOrFail();
        $this->assertSame('PF', $person->person_type);
        $this->assertSame('45653240', $person->postal_code);
        $this->assertSame('529.982.247-25', $person->documents()->firstOrFail()->number);
        $this->assertSame('73991234567', $person->phones()->firstOrFail()->number);
        $this->assertSame($person->phones()->firstOrFail()->getKey(), $person->whatsappIdentifiers()->firstOrFail()->phone_id);
        $this->assertDatabaseHas('person_documents', ['person_id' => $person->getKey(), 'type' => 'CPF']);

        $storedNumber = DB::table('person_documents')->where('person_id', $person->getKey())->value('number');
        $this->assertNotSame('529.982.247-25', $storedNumber);
        $this->assertSame('529.982.247-25', Crypt::decryptString($storedNumber));
        $this->assertDatabaseCount('account_audit_events', 0);
        $this->assertStringNotContainsString('529.982.247-25', json_encode(DB::table('account_audit_events')->get()->all(), JSON_THROW_ON_ERROR));
    }

    public function test_document_types_are_optional_unique_per_person_and_restricted_to_person_type(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        $payload = $this->validPayload();
        $payload['person_type'] = 'PJ';
        $payload['documents'] = [['type' => 'CPF', 'number' => '52998224725']];
        $this->mfa($actor)->from(route('people.create'))->post(route('people.store'), $payload)->assertSessionHasErrors('documents.0.type');
        $this->assertDatabaseCount('people', 0);

        $payload['documents'] = [];
        $this->mfa($actor)->post(route('people.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('person_documents', 0);

        $payload['documents'] = [
            ['type' => 'CNPJ', 'number' => '11.222.333/0001-81'],
            ['type' => 'CNPJ', 'number' => '11.222.333/0001-81'],
        ];
        $this->mfa($actor)->post(route('people.store'), $payload)->assertSessionHasErrors('documents.1.type');
    }

    public function test_invalid_cpf_and_cnpj_are_rejected_but_valid_numeric_and_alphanumeric_cnpj_pass(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        $payload = $this->validPayload();
        $payload['documents'][0]['number'] = '111.111.111-11';
        $this->mfa($actor)->post(route('people.store'), $payload)->assertSessionHasErrors('documents.0.number');
        $this->assertDatabaseCount('people', 0);

        $payload['person_type'] = 'PJ';
        $payload['documents'] = [['type' => 'CNPJ', 'number' => '11.111.111/1111-11']];
        $this->mfa($actor)->post(route('people.store'), $payload)->assertSessionHasErrors('documents.0.number');
        $this->assertDatabaseCount('people', 0);

        $validator = app(PersonDocumentValidator::class);
        $this->assertTrue($validator->validCnpj('11.222.333/0001-81'));
        $this->assertTrue($validator->validCnpj('12.ABC.345/01DE-35'));
        $this->assertFalse($validator->validCnpj('12.ABC.345/01DE-36'));
    }

    public function test_duplicate_name_is_exact_private_and_requires_audited_confirmation(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        $this->mfa($actor)->post(route('people.store'), $this->validPayload('Nome Exato'))->assertRedirect();
        $count = Person::query()->count();

        $payload = $this->validPayload(' nome   exato ');
        $this->mfa($actor)->from(route('people.create'))->post(route('people.store'), $payload)->assertSessionHasErrors('name');
        $this->assertSame($count, Person::query()->count());
        $this->assertStringNotContainsString('Nome Exato', json_encode(session()->all(), JSON_THROW_ON_ERROR));

        $payload['confirm_duplicate'] = '1';
        $this->mfa($actor)->post(route('people.store'), $payload)->assertRedirect();
        $this->assertSame(2, Person::query()->count());
        $this->assertDatabaseHas('account_audit_events', ['event' => 'person.duplicate_name.confirmed']);

        $differentCase = $this->validPayload('nome exato');
        $this->mfa($actor)->post(route('people.store'), $differentCase)->assertSessionHasErrors('name');
        $differentCase['confirm_duplicate'] = '1';
        $this->mfa($actor)->post(route('people.store'), $differentCase)->assertRedirect();
        $this->assertSame(3, Person::query()->count());

        $this->mfa($actor)->post(route('people.store'), $this->validPayload('Nome-Exato'))->assertRedirect();
        $this->mfa($actor)->post(route('people.store'), $this->validPayload('José Silva'))->assertRedirect();
        $this->mfa($actor)->post(route('people.store'), $this->validPayload('Jose Silva'))->assertRedirect();
        $this->assertSame(6, Person::query()->count());
    }

    public function test_creator_can_update_own_person_but_cannot_change_pf_pj_or_other_users_person(): void
    {
        $creator = $this->account([['people', 'read'], ['people', 'create']]);
        $this->mfa($creator)->post(route('people.store'), $this->validPayload())->assertRedirect();
        $person = Person::query()->firstOrFail();
        $payload = $this->validPayload();
        unset($payload['person_type']);
        $payload['name'] = 'Pessoa alterada';
        $this->mfa($creator)->put(route('people.update', $person), $payload)->assertRedirect();
        $this->assertSame('Pessoa alterada', $person->fresh()->name);

        $payload['person_type'] = 'PJ';
        $this->mfa($creator)->put(route('people.update', $person), $payload)->assertSessionHasErrors('person_type');
        $this->assertSame('PF', $person->fresh()->person_type);

        $other = $this->account([['people', 'read'], ['people', 'create']]);
        $this->mfa($other)->get(route('people.edit', $person))->assertNotFound();

        $authorizedEditor = $this->account([['people', 'read'], ['people', 'update']]);
        $this->mfa($authorizedEditor)->get(route('people.edit', $person))->assertOk();
    }

    public function test_phone_and_whatsapp_require_exactly_one_primary_and_valid_brazilian_ddd(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        $payload = $this->validPayload();
        $payload['phones'] = [
            ['number' => '(73) 99123-4567', 'category' => 'Celular', 'is_primary' => '1'],
            ['number' => '(73) 99123-4568', 'category' => 'Celular', 'is_primary' => '1'],
        ];
        $this->mfa($actor)->post(route('people.store'), $payload)->assertSessionHasErrors('phones');

        $payload['phones'] = [['number' => '(20) 99123-4567', 'category' => 'Celular', 'is_primary' => '1']];
        $this->mfa($actor)->post(route('people.store'), $payload)->assertSessionHasErrors('phones');

        $payload['phones'] = [];
        $payload['whatsapp'] = [
            ['type' => 'username', 'value' => 'fokus.sintetico', 'user_key' => 'chave-sintetica', 'is_primary' => '0'],
            ['type' => 'phone', 'value' => '(73) 99123-4567', 'user_key' => '', 'is_primary' => '0'],
        ];
        $this->mfa($actor)->post(route('people.store'), $payload)->assertSessionHasErrors('whatsapp');
    }

    public function test_ddd_state_label_preserves_ambiguous_61_as_df_or_go(): void
    {
        $phones = app(BrazilianPhoneNumber::class);
        $this->assertSame('DF/GO', $phones->stateLabel('61991234567'));
        $this->assertSame('BA', $phones->stateLabel('73991234567'));
    }

    public function test_only_authorized_admin_can_delete_unlinked_person_and_audit_is_minimal(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'is_synthetic_admin' => true,
            'mfa_enabled_at' => now()->subMinute(),
            'mfa_reset_required' => false,
        ]);
        $person = $this->newPerson($admin);
        $phone = PersonPhone::query()->create(['person_id' => $person->getKey(), 'number' => '61991234567', 'category' => 'Celular', 'is_primary' => true]);
        PersonDocument::query()->create(['person_id' => $person->getKey(), 'type' => 'CPF', 'number' => '00000000000']);
        PersonWhatsappIdentifier::query()->create(['person_id' => $person->getKey(), 'phone_id' => $phone->getKey(), 'type' => 'phone', 'value' => '61991234567', 'is_primary' => true]);
        $this->grantPersonDelete($admin);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->delete(route('people.destroy', $person->getKey()))
            ->assertRedirect(route('people.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('people', ['id' => $person->getKey()]);
        $this->assertDatabaseMissing('person_documents', ['person_id' => $person->getKey()]);
        $this->assertDatabaseMissing('person_phones', ['person_id' => $person->getKey()]);
        $this->assertDatabaseMissing('person_whatsapp_identifiers', ['person_id' => $person->getKey()]);
        $this->assertDatabaseHas('account_audit_events', [
            'actor_user_id' => $admin->getKey(),
            'event' => 'person.deleted',
            'subject_id' => null,
            'module' => 'people',
            'action' => 'delete',
            'outcome' => 'success',
        ]);
    }

    public function test_delete_is_denied_when_person_has_an_account_or_process_link(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'is_synthetic_admin' => true,
            'mfa_enabled_at' => now()->subMinute(),
            'mfa_reset_required' => false,
        ]);
        $this->grantPersonDelete($admin);

        $linkedAccount = User::factory()->create();
        $personWithAccount = $this->newPerson($admin);
        $personWithAccount->user_id = $linkedAccount->getKey();
        $personWithAccount->save();
        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->delete(route('people.destroy', $personWithAccount->getKey()))
            ->assertNotFound();
        $this->assertDatabaseHas('people', ['id' => $personWithAccount->getKey()]);

        $personWithProcess = $this->newPerson($admin);
        $unitId = '00000000-0000-4000-8000-000000000099';
        DB::table('units')->insert(['id' => $unitId, 'name' => 'Unidade protegida', 'created_at' => now(), 'updated_at' => now()]);
        $process = CourtProcess::query()->create([
            'unit_id' => $unitId, 'created_by_user_id' => $admin->getKey(),
            'cnj_normalized' => '00001005020088270000',
        ]);
        PersonProcess::query()->create([
            'person_id' => $personWithProcess->getKey(), 'court_process_id' => $process->getKey(),
            'created_by_user_id' => $admin->getKey(), 'role' => 'reu',
        ]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->delete(route('people.destroy', $personWithProcess->getKey()))
            ->assertNotFound();
        $this->assertDatabaseHas('people', ['id' => $personWithProcess->getKey()]);
        $this->assertDatabaseMissing('account_audit_events', ['event' => 'person.deleted']);
    }

    public function test_jorge_links_account_and_person_explicitly_with_both_internal_ids_audited(): void
    {
        $admin = User::factory()->create([
            'is_active' => true, 'is_synthetic_admin' => true,
            'mfa_enabled_at' => now()->subMinute(), 'mfa_reset_required' => false,
        ]);
        $account = User::factory()->create(['is_active' => true, 'email' => 'same@example.test']);
        $person = $this->newPerson($admin);

        $this->assertNull($person->user_id);
        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('person-account-links.index'))
            ->assertOk()
            ->assertSee($person->name)
            ->assertSee($account->email);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('person-account-links.store'), ['person_id' => $person->getKey(), 'user_id' => $account->getKey()])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('people', ['id' => $person->getKey(), 'user_id' => $account->getKey()]);
        $this->assertDatabaseHas('person_account_link_events', [
            'actor_user_id' => $admin->getKey(),
            'person_id' => $person->getKey(),
            'user_id' => $account->getKey(),
            'operation' => 'linked',
            'outcome' => 'success',
        ]);

        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('person-account-links.store'), ['person_id' => $person->getKey(), 'user_id' => $account->getKey()])
            ->assertSessionHasErrors('link');
        $this->assertDatabaseCount('person_account_link_events', 1);
    }

    public function test_non_admin_cannot_create_account_person_link(): void
    {
        $ordinary = User::factory()->create([
            'is_active' => true, 'is_synthetic_admin' => false,
            'mfa_enabled_at' => now()->subMinute(), 'mfa_reset_required' => false,
        ]);
        $account = User::factory()->create(['is_active' => true]);
        $person = $this->newPerson($ordinary);

        $this->actingAs($ordinary)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->post(route('person-account-links.store'), ['person_id' => $person->getKey(), 'user_id' => $account->getKey()])
            ->assertForbidden();

        $this->assertNull($person->fresh()->user_id);
        $this->assertDatabaseCount('person_account_link_events', 0);
    }

    public function test_linked_active_account_can_update_only_its_own_contacts_without_module_read_grant(): void
    {
        $owner = User::factory()->create([
            'is_active' => true, 'mfa_enabled_at' => now()->subMinute(), 'mfa_reset_required' => false,
        ]);
        $person = $this->newPerson($owner);
        $person->user_id = $owner->getKey();
        $person->save();
        $phone = PersonPhone::query()->create([
            'person_id' => $person->getKey(), 'number' => '61991234567', 'category' => 'Celular', 'is_primary' => true,
        ]);

        $this->actingAs($owner)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('people.own-contacts.edit'))
            ->assertOk()
            ->assertSee('Meus telefones e endereço');

        $this->actingAs($owner)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->put(route('people.own-contacts.update'), [
                'address' => [
                    'postal_code' => '45653241', 'street' => 'Rua atualizada', 'number' => '5',
                    'city' => 'Ilhéus', 'state' => 'BA',
                ],
                'phones' => [[
                    'id' => $phone->getKey(), 'number' => '(61) 9 1234-5678',
                    'category' => 'Celular', 'is_primary' => '1',
                ]],
            ])
            ->assertRedirect(route('people.own-contacts.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('people', ['id' => $person->getKey(), 'street' => 'Rua atualizada', 'name' => 'Pessoa sintética']);
        $this->assertSame('61912345678', $phone->fresh()->number);
        $this->assertDatabaseHas('account_audit_events', [
            'actor_user_id' => $owner->getKey(),
            'event' => 'person.own_contact.updated',
            'subject_type' => 'person',
            'subject_id' => (string) $person->getKey(),
            'action' => 'update_self',
            'outcome' => 'success',
        ]);
        $event = AccountAuditEvent::query()->where('event', 'person.own_contact.updated')->firstOrFail();
        $this->assertStringNotContainsString('61912345678', json_encode($event->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_self_contact_update_cannot_change_other_person_or_sensitive_fields(): void
    {
        $owner = User::factory()->create([
            'is_active' => true, 'mfa_enabled_at' => now()->subMinute(), 'mfa_reset_required' => false,
        ]);
        $other = User::factory()->create();
        $person = $this->newPerson($other);
        $person->user_id = $other->getKey();
        $person->save();

        $this->actingAs($owner)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->get(route('people.own-contacts.edit'))
            ->assertOk()
            ->assertSee('ainda não tem um cadastro de Pessoa vinculado');

        $linkedPerson = $this->newPerson($owner);
        $linkedPerson->user_id = $owner->getKey();
        $linkedPerson->save();
        $this->actingAs($owner)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->put(route('people.own-contacts.update'), [
                'name' => 'Nome alterado sem permissão',
                'address' => [
                    'postal_code' => '45653241', 'street' => 'Rua atualizada', 'number' => '5',
                    'city' => 'Ilhéus', 'state' => 'BA',
                ],
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('Pessoa sintética', $linkedPerson->fresh()->name);
        $this->assertSame('Rua de teste', $linkedPerson->fresh()->street);
    }

    public function test_self_contact_edit_cannot_change_a_phone_referenced_by_whatsapp(): void
    {
        $owner = User::factory()->create([
            'is_active' => true, 'mfa_enabled_at' => now()->subMinute(), 'mfa_reset_required' => false,
        ]);
        $person = $this->newPerson($owner);
        $person->user_id = $owner->getKey();
        $person->save();
        $phone = PersonPhone::query()->create([
            'person_id' => $person->getKey(), 'number' => '61991234567', 'category' => 'Celular', 'is_primary' => true,
        ]);
        PersonWhatsappIdentifier::query()->create([
            'person_id' => $person->getKey(), 'phone_id' => $phone->getKey(),
            'type' => 'phone', 'value' => '61991234567', 'is_primary' => true,
        ]);

        $this->actingAs($owner)->withSession(['auth.mfa_verified_at' => now()->subSeconds(2)->timestamp])
            ->put(route('people.own-contacts.update'), [
                'address' => [
                    'postal_code' => '45653241', 'street' => 'Rua atualizada', 'number' => '5',
                    'city' => 'Ilhéus', 'state' => 'BA',
                ],
                'phones' => [[
                    'id' => $phone->getKey(), 'number' => '62991234567',
                    'category' => 'Celular', 'is_primary' => '1',
                ]],
            ])
            ->assertSessionHasErrors('phones.0.number');

        $this->assertSame('61991234567', $phone->fresh()->number);
        $this->assertSame('Rua de teste', $person->fresh()->street);
        $this->assertDatabaseMissing('account_audit_events', ['event' => 'person.own_contact.updated']);
    }

    private function grantPersonDelete(User $admin): void
    {
        foreach (['read', 'delete'] as $action) {
            DB::table('account_grants')->insert([
                'user_id' => $admin->getKey(), 'module' => 'people', 'action' => $action,
                'granted_by_user_id' => $admin->getKey(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function newPerson(User $creator): Person
    {
        return Person::query()->create([
            'created_by_user_id' => $creator->getKey(), 'person_type' => 'PF', 'name' => 'Pessoa sintética',
            'postal_code' => '45653240', 'street' => 'Rua de teste', 'address_number' => '1',
            'city' => 'Ilhéus', 'state' => 'BA',
        ]);
    }

    public function test_search_and_show_require_read_grant_and_search_is_literal(): void
    {
        $creator = $this->account([['people', 'read'], ['people', 'create']]);
        $this->mfa($creator)->post(route('people.store'), $this->validPayload('Árvore'))->assertRedirect();
        $person = Person::query()->firstOrFail();
        $reader = $this->account([['people', 'read']]);
        $this->mfa($reader)->post(route('people.search'), ['name' => 'arvore'])->assertDontSee('Árvore');
        $this->mfa($reader)->get(route('people.show', $person))->assertOk()->assertSee('529.982.247-25');
        $withoutRead = $this->account();
        $this->mfa($withoutRead)->get(route('people.show', $person))->assertNotFound();
    }

    public function test_address_lookup_sends_only_user_entered_cep_and_returns_allowlisted_fields(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        Http::fake(['viacep.com.br/*' => Http::response(['cep' => '45653-240', 'logradouro' => 'Rua Sintética', 'bairro' => 'Centro', 'localidade' => 'Ilhéus', 'uf' => 'BA', 'complemento' => 'Não enviar'], 200)]);

        $response = $this->mfa($actor)->postJson(route('people.address.lookup'), ['cep' => '45653-240']);
        $response->assertOk()->assertJson(['found' => true, 'address' => ['street' => 'Rua Sintética', 'district' => 'Centro', 'city' => 'Ilhéus', 'state' => 'BA']])->assertJsonMissingPath('address.complement');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://viacep.com.br/ws/45653240/json/' && $request->method() === 'GET' && $request->body() === '');
    }

    public function test_address_lookup_failure_is_audited_without_cep_or_address_data(): void
    {
        $actor = $this->account([['people', 'read'], ['people', 'create']]);
        Http::fake(['viacep.com.br/*' => Http::response(['erro' => true], 200)]);

        $this->mfa($actor)->postJson(route('people.address.lookup'), ['cep' => '45653-240'])->assertOk()->assertJson(['found' => false]);
        $event = AccountAuditEvent::query()->firstOrFail();
        $this->assertSame('person.address.lookup_failed', $event->event);
        $this->assertNull($event->subject_id);
        $this->assertStringNotContainsString('45653240', json_encode($event->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_delete_policy_requires_jorge_and_rejects_account_linked_person(): void
    {
        $admin = $this->account([['people', 'read'], ['people', 'delete']], true);
        $person = Person::query()->create([
            'created_by_user_id' => $admin->getKey(), 'person_type' => 'PF', 'name' => 'Pessoa',
            'postal_code' => '45653240', 'street' => 'Rua', 'address_number' => '1', 'city' => 'Ilhéus', 'state' => 'BA',
        ]);
        $this->assertTrue(app(PersonAccessPolicy::class)->canDelete($admin, $person));
        $person->user_id = User::factory()->create()->getKey();
        $person->save();
        $this->assertFalse(app(PersonAccessPolicy::class)->canDelete($admin, $person));

        $ordinary = $this->account([['people', 'read'], ['people', 'delete']]);
        $this->assertFalse(app(PersonAccessPolicy::class)->canDelete($ordinary, $person));

        $person->user_id = null;
        $person->save();
        $this->assertTrue(app(PersonAccessPolicy::class)->canDelete($admin, $person));
        $unitSettings = app(ProcessUnitConfiguration::class);
        $unitSettings->set($admin, 'Unidade sintética', 0);
        $unitId = $unitSettings->snapshot()['unit']->id;
        $processId = DB::table('court_processes')->insertGetId([
            'unit_id' => $unitId,
            'created_by_user_id' => $admin->getKey(),
            'cnj_normalized' => '20261210100001234',
            'operational_status' => 'ativo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        PersonProcess::query()->create([
            'person_id' => $person->getKey(),
            'court_process_id' => $processId,
            'created_by_user_id' => $admin->getKey(),
            'role' => PersonProcess::ROLE_REU,
        ]);
        $this->assertFalse(app(PersonAccessPolicy::class)->canDelete($admin, $person));
    }
}
