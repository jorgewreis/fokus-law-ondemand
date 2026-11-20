<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompetencyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function account(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()])->save();

        return $user;
    }

    public function test_admin_can_import_configure_grant_and_revoke(): void
    {
        $admin = $this->account(true);
        $subject = $this->account();
        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->get(route('competency-admin.index'))->assertOk()->assertSeeText('O catálogo de competências ainda não foi carregado.');
        $this->post(route('competency-admin.import'))->assertRedirect();
        $this->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => ['3', '16']])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('competency-admin.grant', $subject), ['competency' => 3, 'operation' => 'grant'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('account_competency_grants', ['user_id' => $subject->id, 'competency_code' => 3]);
        $this->get(route('competency-admin.index'))->assertOk()->assertSeeText('Criminal')->assertSeeText($subject->email);
        $this->post(route('competency-admin.grant', $subject), ['competency' => 3, 'operation' => 'revoke'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('account_competency_grants', ['user_id' => $subject->id, 'competency_code' => 3]);
        $this->assertSame(2, DB::table('competency_grant_events')->count());
    }

    public function test_authentication_session_mfa_and_admin_are_required(): void
    {
        $this->get(route('competency-admin.index'))->assertRedirect(route('login.create'));
        $this->actingAs($this->account(true))->get(route('competency-admin.index'))->assertForbidden();
        $this->actingAs($this->account())->withSession(['auth.mfa_verified_at' => now()->timestamp])->get(route('competency-admin.index'))->assertForbidden();
        $this->post(route('competency-admin.import'))->assertForbidden();
    }

    public function test_invalid_codes_cannot_change_configuration_or_grants(): void
    {
        $admin = $this->account(true);
        $subject = $this->account();
        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->post(route('competency-admin.import'));
        $this->from(route('competency-admin.index'))->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => ['999']])->assertSessionHasErrors();
        $this->from(route('competency-admin.index'))->post(route('competency-admin.grant', $subject), ['competency' => 999, 'operation' => 'grant'])->assertSessionHasErrors();
        $this->assertDatabaseCount('account_competency_grants', 0);
        $this->assertDatabaseCount('process_module_settings', 0);
    }

    public function test_invalid_scalar_selection_can_be_redisplayed(): void
    {
        $this->actingAs($this->account(true))->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->post(route('competency-admin.import'));
        $this->from(route('competency-admin.index'))->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => 'invalid'])->assertSessionHasErrors('competencies');
        $this->withSession(['_old_input' => ['competencies' => 'invalid']])->get(route('competency-admin.index'))->assertOk();
    }

    public function test_catalog_damage_blocks_writes_but_allows_revocation(): void
    {
        $this->actingAs($this->account(true))->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $subject = $this->account();
        $this->post(route('competency-admin.import'));
        $this->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => [3, 16]])->assertSessionHasNoErrors();
        $this->post(route('competency-admin.grant', $subject), ['competency' => 3, 'operation' => 'grant'])->assertSessionHasNoErrors();
        DB::table('competencies')->where('code', 2)->delete();
        $this->get(route('competency-admin.index'))->assertOk()->assertSeeText('O catálogo está incompleto ou inválido.')->assertSeeText('Carregar catálogo')->assertDontSeeText('Salvar competências da Vara')->assertSeeText('Revogar');
        $this->post(route('competency-admin.settings'), ['revision' => 1, 'competencies' => [3]])->assertSessionHasErrors('catalog');
        $this->post(route('competency-admin.grant', $subject), ['competency' => 16, 'operation' => 'grant'])->assertSessionHasErrors('catalog');
        $this->assertDatabaseHas('process_module_competencies', ['competency_code' => 16, 'active' => true]);
        $this->post(route('competency-admin.grant', $subject), ['competency' => 3, 'operation' => 'revoke'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('account_competency_grants', ['user_id' => $subject->id, 'competency_code' => 3]);
        $this->post(route('competency-admin.import'))->assertRedirect();
        $this->assertDatabaseHas('competencies', ['code' => 2]);
    }

    public function test_stale_settings_do_not_overwrite_changes(): void
    {
        $this->actingAs($this->account(true))->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->post(route('competency-admin.import'));
        $this->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => [3]])->assertSessionHasNoErrors();
        $this->from(route('competency-admin.index'))->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => [16]])->assertSessionHasErrors('revision');
        $this->assertDatabaseHas('process_module_competencies', ['competency_code' => 3, 'active' => true]);
        $this->assertDatabaseMissing('process_module_competencies', ['competency_code' => 16]);
    }

    public function test_invalid_description_blocks_actions_and_reports_import_failure(): void
    {
        $this->actingAs($this->account(true))->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->post(route('competency-admin.import'));
        DB::table('competencies')->where('code', 3)->update(['description' => 'alterado']);
        $this->get(route('competency-admin.index'))->assertOk()->assertSeeText('O catálogo está incompleto ou inválido.')->assertDontSeeText('Salvar competências da Vara');
        $this->post(route('competency-admin.settings'), ['revision' => 0, 'competencies' => [3]])->assertSessionHasErrors('catalog');
        $this->post(route('competency-admin.import'))->assertSessionHasErrors('catalog');
        $this->assertDatabaseCount('process_module_settings', 0);
    }
}
