<?php

namespace Tests\Feature;

use App\Models\AnalystProjectRate;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_overtime_log_snapshots_cost_and_billable_rates(): void
    {
        [$user, $project, $activityTypeId] = $this->context(['projects.view_assigned', 'work_logs.create_own']);

        $response = $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/work-logs", [
            'activity_type_id' => $activityTypeId,
            'worked_on' => '2026-08-20',
            'duration_minutes' => 90,
            'description' => 'Atendimento fora do horário comercial.',
            'is_overtime' => true,
        ])->assertCreated()->assertJsonPath('cost_rate_snapshot', '100.00')->assertJsonPath('sale_rate_snapshot', '180.00');

        $log = WorkLog::findOrFail($response->json('id'));
        $this->assertSame(150.0, $log->costAmount());
        $this->assertSame(270.0, $log->billableAmount());

        AnalystProjectRate::where('project_id', $project->id)->update(['overtime_sale_rate' => 999]);
        $this->assertSame(270.0, $log->fresh()->billableAmount());
        $this->assertDatabaseHas('audit_logs', ['action' => 'work_log.created', 'auditable_id' => $log->id]);
    }

    public function test_analyst_cannot_register_time_for_another_user_without_permission(): void
    {
        [$user, $project, $activityTypeId] = $this->context(['projects.view_assigned', 'work_logs.create_own']);
        $other = User::factory()->create(['current_company_id' => $user->current_company_id]);
        $project->members()->create(['user_id' => $other->id, 'assigned_by' => $user->id, 'assigned_at' => now()]);

        $this->actingAs($user)->postJson("/api/v1/projects/{$project->id}/work-logs", [
            'analyst_id' => $other->id,
            'activity_type_id' => $activityTypeId,
            'worked_on' => '2026-08-20',
            'duration_minutes' => 30,
            'description' => 'Tentativa em nome de terceiro.',
        ])->assertForbidden();
    }

    /** @param array<int, string> $slugs @return array{User, Project, int} */
    private function context(array $slugs): array
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Analista', 'slug' => 'analista']);
        foreach ($slugs as $slug) {
            $permission = Permission::create(['name' => $slug, 'slug' => $slug, 'module' => 'Teste']);
            $profile->permissions()->attach($permission);
        }
        $user->profiles()->attach($profile, ['company_id' => $company->id]);
        $project = Project::create(['company_id' => $company->id, 'user_id' => $user->id, 'name' => 'Projeto', 'status' => 'in_progress', 'progress' => 10]);
        $project->members()->create(['user_id' => $user->id, 'assigned_by' => $user->id, 'assigned_at' => now()]);
        AnalystProjectRate::create(['project_id' => $project->id, 'user_id' => $user->id, 'normal_cost_rate' => 60, 'overtime_cost_rate' => 100, 'overtime_sale_rate' => 180, 'effective_from' => '2026-01-01']);
        $activityTypeId = DB::table('activity_types')->insertGetId(['company_id' => $company->id, 'name' => 'Atendimento', 'slug' => 'atendimento', 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $project, $activityTypeId];
    }
}
