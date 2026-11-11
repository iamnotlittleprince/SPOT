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

    public function test_public_activation_link_generation_is_unavailable(): void
    {
        $this->postJson('/api/v1/first-access', [
            'name' => 'Ana', 'email' => 'ana@example.com',
        ])->assertStatus(405);
        $this->assertDatabaseCount('user_activation_tokens', 0);
    }

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
        $this->assertContains('convidado', collect($response->json('profiles'))->pluck('slug'));
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

        $this->postJson("/api/v1/first-access/{$token}", [
            'name' => 'Ana Analista', 'email' => 'outra@computecnica.com.br',
            'password' => 'senha-segura', 'password_confirmation' => 'senha-segura',
        ])->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonPath('errors.email.0', 'Por gentileza, insira o e-mail correspondente ao do convite enviado!');
        $this->assertAuthenticatedAs($admin);
        $this->assertNull($pending->fresh()->password);
        $this->assertDatabaseHas('user_activation_tokens', [
            'user_id' => $pending->id, 'used_at' => null,
        ]);
        $this->withSession(['previous_account_data' => 'admin']);
        $previousSessionId = session()->getId();
        $this->postJson("/api/v1/first-access/{$token}", [
            'name' => 'Ana', 'email' => 'ANA@COMPUTECNICA.COM.BR',
            'password' => 'senha-segura', 'password_confirmation' => 'senha-segura',
        ])->assertOk()->assertJsonPath('user.account_status', 'active');
        $this->assertSame('Ana Analista', $pending->fresh()->name);
        $this->assertAuthenticatedAs($pending->fresh(), 'web');
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertNull(session('previous_account_data'));
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('id', $pending->id);

        $this->postJson("/api/v1/first-access/{$token}", [
            'name' => 'Ana Analista', 'email' => 'ana@computecnica.com.br',
            'password' => 'outra-senha', 'password_confirmation' => 'outra-senha',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertAuthenticatedAs($pending->fresh(), 'web');

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
    public function test_administrator_can_grant_and_revoke_project_creation_for_an_analyst(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id, 'organization_id' => $admin->organization_id]);
        $analyst->profiles()->attach(DB::table('profiles')->where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->actingAs($analyst)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('can_create_projects', false);
        $this->postJson('/api/v1/projects', [])->assertForbidden();
        $changes = ['profile' => 'analista', 'account_status' => 'active', 'can_create_projects' => true];
        $this->patchJson('/api/v1/admin/users/'.$analyst->id, $changes)->assertForbidden();
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$analyst->id, $changes)->assertOk();
        $this->assertTrue($analyst->fresh()->can('projects.create'));
        $this->actingAs($analyst)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('can_create_projects', true);
        $this->postJson('/api/v1/projects', [
            'name' => 'Projeto autorizado', 'proposal_number' => 'AUT-001', 'project_manager_id' => $admin->id,
            'project_status_id' => DB::table('project_statuses')->where('company_id', $admin->current_company_id)->value('id'),
            'project_situation_id' => DB::table('project_situations')->where('company_id', $admin->current_company_id)->value('id'),
            'contract_value' => 100,
        ])->assertCreated();
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$analyst->id, [...$changes, 'can_create_projects' => false])->assertOk();
        $this->actingAs($analyst)->postJson('/api/v1/projects', [])->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('can_create_projects', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.access_updated', 'auditable_id' => $analyst->id]);
    }

    public function test_administrator_cannot_grant_creation_to_user_in_another_company(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $foreign = User::factory()->create(['current_company_id' => null]);
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$foreign->id, [
            'profile' => 'analista', 'account_status' => 'active', 'can_create_projects' => true,
        ])->assertNotFound();
    }

    public function test_administrator_can_delete_a_pending_user(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $pending = User::factory()->create([
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => false,
            'account_status' => 'pending_activation',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/users/'.$pending->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $pending->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deleted',
            'auditable_id' => $pending->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_pending_user_with_chat_history_is_removed_from_administration_without_losing_history(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $pending = User::factory()->create([
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => false,
            'account_status' => 'pending_activation',
        ]);
        $conversationId = DB::table('chat_conversations')->insertGetId([
            'company_id' => $admin->current_company_id,
            'first_user_id' => min($admin->id, $pending->id),
            'second_user_id' => max($admin->id, $pending->id),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $messageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversationId, 'sender_id' => $pending->id,
            'body' => 'Histórico preservado', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $originalEmail = $pending->email;
        $oldJwt = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($pending);
        $pending->forceFill(['google_id' => 'archived-google', 'google_email' => $originalEmail, 'microsoft_id' => 'archived-microsoft', 'microsoft_email' => $originalEmail])->save();
        DB::table('user_activation_tokens')->insert([
            'user_id' => $pending->id, 'created_by' => $admin->id,
            'token_hash' => hash('sha256', 'archive-test-token'), 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('password_reset_tokens')->insert(['email' => $originalEmail, 'token' => 'old-reset-token', 'created_at' => now()]);
        DB::table('sessions')->insert(['id' => 'archived-account-session', 'user_id' => $pending->id, 'payload' => 'test', 'last_activity' => time()]);
        $pending->createToken('archived-account-token');

        $this->actingAs($admin)->deleteJson('/api/v1/admin/users/'.$pending->id)->assertNoContent();
        $archived = $pending->fresh();
        $this->assertNotNull($archived->archived_at);
        $this->assertFalse($archived->active);
        $this->assertSame('disabled', $archived->account_status);
        $this->assertNotSame($originalEmail, $archived->email);
        $this->assertSame($pending->name, $archived->name);
        $this->assertNull($archived->password);
        $this->assertNull($archived->google_id);
        $this->assertNull($archived->microsoft_id);
        $this->assertDatabaseHas('chat_conversations', ['id' => $conversationId]);
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'sender_id' => $pending->id]);
        $this->assertNotNull(DB::table('user_activation_tokens')->where('user_id', $pending->id)->value('revoked_at'));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $originalEmail]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $pending->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $pending->id, 'tokenable_type' => User::class]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'user.deleted')->where('auditable_id', $pending->id)->count());
        $ids = $this->actingAs($admin)->getJson('/api/v1/admin/users')->assertOk()->json('users');
        $this->assertNotContains($pending->id, collect($ids)->pluck('id'));
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$pending->id, ['profile' => 'analista', 'account_status' => 'active'])->assertNotFound();
        $this->actingAs($admin)->deleteJson('/api/v1/admin/users/'.$pending->id)->assertNotFound();
        $newUser = User::factory()->create(['email' => $originalEmail]);
        $this->assertNotEquals($pending->id, $newUser->id);
        Auth::forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$oldJwt)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_administrator_can_provision_and_delete_a_pending_guest(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name' => 'Visitante', 'email' => 'visitante@example.test',
            'profile' => 'convidado', 'organization_id' => $admin->organization_id,
            'job_title' => 'Visitante', 'department' => 'Externo',
        ])->assertCreated()->assertJsonPath('user.account_status', 'pending_activation');
        $guest = User::findOrFail($response->json('user.id'));
        $this->assertTrue($guest->profiles()->where('slug', 'convidado')->exists());
        $this->assertFalse($guest->hasPermission('security.manage'));
        $this->assertFalse($guest->hasPermission('projects.create'));
        $this->assertFalse(app(\App\Domain\Chat\ChatAccess::class)->eligible($guest));
        $this->actingAs($admin)->deleteJson('/api/v1/admin/users/'.$guest->id)->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
        $this->assertDatabaseMissing('user_activation_tokens', ['user_id' => $guest->id]);
    }

    public function test_administrator_can_change_an_active_analyst_to_guest_without_administrative_access(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $analyst = User::factory()->create([
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => true, 'account_status' => 'active',
        ]);
        $analyst->profiles()->attach(DB::table('profiles')->where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$analyst->id, [
            'profile' => 'convidado', 'account_status' => 'active',
        ])->assertOk()->assertJsonPath('user.profiles.0.slug', 'convidado');
        $guest = $analyst->fresh();
        $this->assertFalse(app(\App\Domain\Chat\ChatAccess::class)->eligible($guest));
        $this->assertFalse($guest->hasPermission('security.manage'));
        $this->actingAs($guest)->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_activated_user_must_be_disabled_instead_of_deleted(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $user = User::factory()->create([
            'current_company_id' => $admin->current_company_id,
            'organization_id' => $admin->organization_id,
            'active' => true,
            'account_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/users/'.$user->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

}
