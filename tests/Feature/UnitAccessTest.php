<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProcessUnitAccess;
use App\Services\ProcessUnitConfiguration;
use App\Services\UnitGrants;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnitAccessTest extends TestCase
{
    use RefreshDatabase;

    private function account(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_synthetic_admin' => $admin, 'is_active' => true, 'mfa_enabled_at' => now()])->save();

        return $user;
    }

    private function unit(string $name = '1ª Vara Crime de Ilhéus'): string
    {
        app(ProcessUnitConfiguration::class)->set($this->account(true), $name, (int) DB::table('process_module_unit_settings')->value('revision'));

        return (string) DB::table('process_module_unit_settings')->value('unit_id');
    }

    public function test_unit_access_requires_exact_grant_and_active_mfa_account(): void
    {
        $admin = $this->account(true);
        $user = $this->account();
        $unitId = $this->unit();
        $access = app(ProcessUnitAccess::class);
        $grants = app(UnitGrants::class);

        $this->assertFalse($access->allows($user, $unitId));
        $grants->set($admin, $user, $unitId, true);
        $this->assertTrue($access->allows($user, $unitId));
        $this->assertFalse($access->allows($user, 'not-a-unit'));
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->assertFalse($access->allows($user, $unitId));
        $grants->set($admin, $user, $unitId, false);
        $this->assertSame(['granted', 'revoked'], DB::table('unit_grant_events')->orderBy('id')->pluck('event')->all());
    }

    public function test_only_jorge_can_configure_and_switch_preserves_old_unit_ids(): void
    {
        $admin = $this->account(true);
        $user = $this->account();
        $settings = app(ProcessUnitConfiguration::class);
        $settings->set($admin, '1ª Vara Crime de Ilhéus', 0);
        $oldId = $settings->snapshot()['unit']->id;
        app(UnitGrants::class)->set($admin, $user, $oldId, true);
        $settings->set($admin, 'Unidade sintética revisada', 1);
        $newId = $settings->snapshot()['unit']->id;

        $this->assertNotSame($oldId, $newId);
        $this->assertDatabaseHas('units', ['id' => $oldId, 'name' => '1ª Vara Crime de Ilhéus']);
        $this->assertTrue(app(ProcessUnitAccess::class)->allows($user, $oldId));
        $this->assertFalse(app(ProcessUnitAccess::class)->allows($user, $newId));
        $this->assertSame(2, DB::table('process_module_unit_events')->count());
        $this->assertDatabaseHas('process_module_unit_events', ['before_unit_id' => $oldId, 'after_unit_id' => $newId]);
    }

    public function test_unit_configuration_rejects_non_admin_and_stale_revision(): void
    {
        $settings = app(ProcessUnitConfiguration::class);
        try {
            $settings->set($this->account(), 'Unidade inválida', 0);
            $this->fail('Non-admin must not configure the Unit.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('units', 0);
        }
        $admin = $this->account(true);
        $settings->set($admin, 'Unidade válida', 0);
        try {
            $settings->set($admin, 'Unidade obsoleta', 0);
            $this->fail('Stale revision must not replace configuration.');
        } catch (ValidationException) {
            $this->assertSame('Unidade válida', $settings->snapshot()['unit']->name);
        }
    }

    public function test_admin_routes_require_session_mfa_and_admin_marker(): void
    {
        $admin = $this->account(true);
        $subject = $this->account();
        $this->get(route('unit-admin.index'))->assertRedirect(route('login.create'));
        $this->actingAs($this->account(true))->get(route('unit-admin.index'))->assertForbidden();
        $this->actingAs($admin)->withSession(['auth.mfa_verified_at' => now()->timestamp]);
        $this->get(route('unit-admin.index'))->assertOk()->assertSeeText('Nenhuma Unidade configurada');
        $this->post(route('unit-admin.configure'), ['unit' => '1ª Vara Crime de Ilhéus', 'revision' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $unitId = DB::table('process_module_unit_settings')->value('unit_id');
        $this->post(route('unit-admin.grant', $subject), ['unit' => $unitId, 'operation' => 'grant'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('account_unit_grants', ['user_id' => $subject->id, 'unit_id' => $unitId]);
    }
}
