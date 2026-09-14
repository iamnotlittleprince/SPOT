<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdministrativeProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_lists_users_and_administration_options(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analystProfileId = DB::table('profiles')->where('slug', 'analista')->value('id');

        $analyst = User::factory()->create([
            'name' => 'Ana Analista',
            'email' => 'ana@example.com',
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => false,
            'account_status' => 'pending_activation',
        ]);
        $analyst->profiles()->attach($analystProfileId, ['company_id' => $admin->current_company_id]);

        $foreignUser = User::factory()->create(['name' => 'Usuário Externo', 'current_company_id' => null]);
        DB::table('organizations')->insert([
            'name' => 'Organização inativa', 'type' => 'client', 'active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('users.0.name', 'Administrador')
            ->assertJsonPath('users.1.name', 'Ana Analista')
            ->assertJsonPath('users.1.account_status', 'pending_activation')
            ->assertJsonPath('users.1.profiles.0.slug', 'analista')
            ->assertJsonPath('profiles.0.slug', 'administrador')
            ->assertJsonPath('profiles.3.slug', 'analista');

        $this->assertNotContains($foreignUser->id, collect($response->json('users'))->pluck('id'));
        $this->assertNotContains('convidado', collect($response->json('profiles'))->pluck('slug'));
        $this->assertNotContains('Organização inativa', collect($response->json('organizations'))->pluck('name'));
    }

    public function test_user_without_security_management_permission_cannot_list_users(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = User::factory()->create([
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => true,
            'account_status' => 'active',
        ]);
        $analyst->profiles()->attach(
            DB::table('profiles')->where('slug', 'analista')->value('id'),
            ['company_id' => $admin->current_company_id],
        );

        $this->actingAs($analyst)->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_administrator_provisions_user_who_activates_account_once(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $organizationId = $admin->organization_id;

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name' => 'Ana Analista', 'email' => 'ana@computecnica.com.br',
            'profile' => 'analista', 'organization_id' => $organizationId,
            'job_title' => 'Analista de projetos', 'department' => 'Projetos',
        ])->assertCreated()->assertJsonPath('user.account_status', 'pending_activation');

        $token = basename($response->json('activation_url'));
        $pending = User::where('email', 'ana@computecnica.com.br')->firstOrFail();
        $this->assertNull($pending->password);
        $this->assertSame('Analista de projetos', $pending->job_title);
        $this->assertSame('Projetos', $pending->department);

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $this->postJson("/api/v1/first-access/{$token}", [
            'name' => 'Ana Analista', 'email' => 'ana@computecnica.com.br',
            'password' => 'senha-segura', 'password_confirmation' => 'senha-segura',
        ])->assertOk()->assertJsonPath('user.account_status', 'active');

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $this->postJson("/api/v1/first-access/{$token}", [
            'name' => 'Ana Analista', 'email' => 'ana@computecnica.com.br',
            'password' => 'outra-senha', 'password_confirmation' => 'outra-senha',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activation_created', 'auditable_id' => $pending->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated', 'auditable_id' => $pending->id]);
    }

    public function test_pending_user_cannot_login_before_activation(): void
    {
        User::factory()->create(['email' => 'pendente@example.com', 'password' => 'senha-segura', 'active' => false, 'account_status' => 'pending_activation']);

        $this->postJson('/api/v1/auth/login', ['email' => 'pendente@example.com', 'password' => 'senha-segura'])->assertUnauthorized();
    }

    public function test_last_active_administrator_cannot_remove_own_administrative_access(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();

        $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$admin->id}", [
            'profile' => 'gestor', 'account_status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('profile');
    }

    public function test_administrative_access_update_is_audited(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $user = User::factory()->create([
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => true,
            'account_status' => 'active',
        ]);
        $user->profiles()->attach(
            DB::table('profiles')->where('slug', 'analista')->value('id'),
            ['company_id' => $admin->current_company_id],
        );

        $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$user->id}", [
            'profile' => 'gestor', 'account_status' => 'suspended',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.access_updated',
            'auditable_id' => $user->id,
            'user_id' => $admin->id,
        ]);
    }
}
