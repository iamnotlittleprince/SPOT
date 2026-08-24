<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdministrativeProvisioningTest extends TestCase
{
    use RefreshDatabase;

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
}
