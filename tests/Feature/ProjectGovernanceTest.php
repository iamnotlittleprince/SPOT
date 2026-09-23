<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_can_finalize_and_reopen_a_project_with_audit(): void
    {
        [$user, $project] = $this->userAndProject(['projects.finalize', 'projects.reopen', 'projects.update']);

        $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/finalize", [
            'reason' => 'Entrega validada formalmente pelo cliente.',
        ])->assertOk()->assertJsonPath('finalized_by', $user->id);

        $this->actingAs($user)->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Alteração indevida', 'status' => 'completed', 'progress' => 100,
        ])->assertStatus(409);

        $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/reopen", [
            'reason' => 'Cliente solicitou um ajuste adicional.',
        ])->assertOk()->assertJsonPath('finalized_at', null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'project.finalized', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.reopened', 'user_id' => $user->id]);
    }

    public function test_project_deletion_requires_permission_and_a_concrete_reason(): void
    {
        [$manager, $project] = $this->userAndProject(['projects.delete']);

        $this->actingAs($manager)->deleteJson("/api/v1/projects/{$project->id}", ['reason' => 'curto'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'A justificativa deve ter pelo menos 10 caracteres. Explique o motivo da alteração.');

        $this->actingAs($manager)->deleteJson("/api/v1/projects/{$project->id}", [
            'reason' => 'Proposta duplicada registrada por engano.',
        ])->assertNoContent();

        $this->assertSoftDeleted('projects', ['id' => $project->id, 'deleted_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.deleted', 'auditable_id' => $project->id]);
    }

    public function test_user_without_permission_cannot_finalize_project(): void
    {
        [$user, $project] = $this->userAndProject([]);

        $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/finalize", [
            'reason' => 'Tentativa sem a permissão necessária.',
        ])->assertForbidden();
    }

    public function test_finalization_revokes_member_operations_but_preserves_historical_project_visibility(): void
    {
        [$manager, $project] = $this->userAndProject(['projects.finalize']);
        $member = User::factory()->create(['current_company_id' => $manager->current_company_id]);
        $permission = Permission::firstOrCreate(['slug' => 'projects.view_assigned'], ['name' => 'Visualizar atribuídos', 'module' => 'Projetos']);
        $profile = Profile::create(['name' => 'Analista histórico', 'slug' => 'historico-'.uniqid()]);
        $profile->permissions()->attach($permission);
        $member->profiles()->attach($profile, ['company_id' => $manager->current_company_id]);
        $project->members()->create(['user_id' => $member->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'permissions' => ['tasks.view'], 'active' => true]);

        $this->actingAs($manager)->postJson("/api/v1/projects/{$project->id}/finalize", [
            'reason' => 'Entrega encerrada e validada pelo cliente.',
        ])->assertOk();

        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $member->id, 'active' => false]);
        $this->actingAs($member)->getJson('/api/v1/projects')->assertOk()->assertJsonFragment(['id' => $project->id]);
        $this->actingAs($member)->getJson("/api/v1/projects/{$project->id}")->assertOk()->assertJsonMissingPath('tasks');
    }

    /** @param array<int, string> $permissionSlugs @return array{User, Project} */
    private function userAndProject(array $permissionSlugs): array
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Perfil de teste', 'slug' => 'teste-'.uniqid()]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'module' => 'Teste']);
            $profile->permissions()->attach($permission);
        }
        $user->profiles()->attach($profile, ['company_id' => $company->id]);

        $project = Project::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Projeto governado',
            'status' => 'in_progress',
            'progress' => 80,
        ]);

        return [$user, $project];
    }
}
