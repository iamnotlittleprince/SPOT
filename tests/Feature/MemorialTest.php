<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemorialTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $this->seed();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto memorial', 'contract_value' => 1000]);
        $analyst = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $analyst->profiles()->attach(Profile::where('slug', 'analista')->firstOrFail(), ['company_id' => $admin->current_company_id]);
        $project->members()->create(['user_id' => $analyst->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);

        return [$admin, $project, $analyst];
    }

    public function test_parameters_crud_is_scoped_and_audited(): void
    {
        [$admin,,$analyst] = $this->setupData();
        foreach (['clients', 'activity_types', 'expense_types', 'project_statuses', 'project_situations', 'tax_types'] as $type) {
            $id = $this->actingAs($admin)->postJson('/api/v1/management/parameters/'.$type, ['name' => 'Cadastro memorial', 'active' => true])->assertCreated()->json('id');
            $this->putJson("/api/v1/management/parameters/$type/$id", ['name' => 'Nome atualizado', 'active' => true])->assertOk();
            $this->actingAs($analyst)->putJson("/api/v1/management/parameters/$type/$id", ['name' => 'Inválido', 'active' => true])->assertForbidden();
            $this->actingAs($admin)->deleteJson("/api/v1/management/parameters/$type/$id")->assertNoContent();
            $this->assertDatabaseHas($type, ['id' => $id, 'active' => false]);
        }
        $other = User::factory()->create(['current_company_id' => Company::create(['legal_name' => 'Outra empresa'])->id]);
        $other->profiles()->attach(Profile::where('slug', 'administrador')->firstOrFail(), ['company_id' => $other->current_company_id]);
        $this->actingAs($other)->getJson('/api/v1/management/parameters/clients')->assertOk()->assertJsonCount(0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'parameter.saved']);
    }

    public function test_client_registration_stores_company_data_and_rejects_duplicate_cnpj(): void
    {
        [$admin] = $this->setupData();
        $payload = [
            'name' => 'Cliente Exemplo',
            'legal_name' => 'Cliente Exemplo Tecnologia Ltda.',
            'document' => '12.345.678/0001-90',
            'email' => 'contato@cliente-exemplo.com.br',
            'phone' => '(11) 4000-1234',
            'active' => true,
        ];

        $this->actingAs($admin)->postJson('/api/v1/management/parameters/clients', $payload)
            ->assertCreated()
            ->assertJsonPath('legal_name', $payload['legal_name'])
            ->assertJsonPath('document', '12345678000190');

        $this->postJson('/api/v1/management/parameters/clients', [...$payload, 'name' => 'Empresa duplicada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    public function test_tasks_feed_finances_and_preserve_historical_rates(): void
    {
        [$admin,$project,$analyst] = $this->setupData();
        $payload = ['project_id' => $project->id, 'analyst_id' => $analyst->id, 'worked_on' => '2026-09-16', 'duration_minutes' => 90, 'activity_type_id' => DB::table('activity_types')->value('id'), 'description' => 'Reunião memorial', 'is_overtime' => false, 'status' => 'done', 'priority' => 'medium'];
        $id = $this->actingAs($analyst)->postJson('/api/v1/tasks', $payload)->assertCreated()->assertJsonMissingPath('cost_rate_snapshot')->json('id');
        $this->actingAs($admin)->getJson("/api/v1/projects/$project->id/financial-result")->assertOk()->assertJsonPath('complete', false)->assertJsonPath('actual.worked_minutes', 90);
        $rate = $this->postJson("/api/v1/projects/$project->id/rates", ['user_id' => $analyst->id, 'normal_cost_rate' => 100, 'overtime_cost_rate' => 150, 'overtime_sale_rate' => 200, 'effective_from' => '2026-01-01'])->assertCreated()->json('id');
        $this->getJson("/api/v1/projects/$project->id/financial-result")->assertOk()->assertJsonPath('complete', true)->assertJsonPath('actual.labor_cost', '150.00')->assertJsonPath('actual.profit', '850.00')->assertJsonPath('by_analyst.0.minutes', 90);
        $this->putJson("/api/v1/rates/$rate", ['normal_cost_rate' => 200, 'overtime_cost_rate' => 300, 'overtime_sale_rate' => 400, 'effective_from' => '2026-01-01'])->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $id, 'cost_rate_snapshot' => 100]);
        $this->actingAs($analyst)->putJson('/api/v1/tasks/'.$id, [...$payload, 'duration_minutes' => 120])->assertOk();
        $this->actingAs($admin)->getJson("/api/v1/projects/$project->id/financial-result")->assertOk()->assertJsonPath('actual.labor_cost', '200.00');
        $this->actingAs($analyst)->deleteJson('/api/v1/tasks/'.$id)->assertNoContent();
        $this->actingAs($admin)->getJson("/api/v1/projects/$project->id/financial-result")->assertOk()->assertJsonPath('actual.labor_cost', '0.00');
    }

    public function test_expense_mutations_and_finalized_rate_guard(): void
    {
        [$admin,$project,$analyst] = $this->setupData();
        $payload = ['expense_type_id' => DB::table('expense_types')->value('id'), 'expense_date' => '2026-09-16', 'description' => 'Transporte', 'amount' => 50];
        $id = $this->actingAs($analyst)->postJson("/api/v1/projects/$project->id/expenses", $payload)->assertCreated()->json('id');
        $this->actingAs($admin)->postJson("/api/v1/expenses/$id/review", ['decision' => 'approved'])->assertOk();
        $this->actingAs($analyst)->putJson("/api/v1/expenses/$id", [...$payload, 'amount' => 60])->assertOk()->assertJsonPath('status', 'pending');
        $project->forceFill(['finalized_at' => now()])->save();
        $this->putJson("/api/v1/expenses/$id", $payload)->assertConflict();
        $this->deleteJson("/api/v1/expenses/$id")->assertConflict();
        $this->actingAs($admin)->deleteJson("/api/v1/projects/$project->id", ['reason' => 'Exclusão de projeto encerrado'])->assertConflict();
    }

    public function test_settings_hide_secret_and_retention_prunes_only_expired_audit(): void
    {
        [$admin] = $this->setupData();
        $this->actingAs($admin)->putJson('/api/v1/management/settings', ['audit_retention_months' => 12, 'aad_tenant_id' => '00000000-0000-4000-8000-000000000001', 'aad_client_id' => '00000000-0000-4000-8000-000000000002', 'aad_client_secret' => 'test-secret'])->assertOk()->assertJsonMissingPath('aad_client_secret')->assertJsonPath('aad_secret_configured', true);
        $this->assertStringNotContainsString('test-secret', DB::table('company_settings')->value('aad_client_secret'));
        AuditLog::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'action' => 'old', 'auditable_type' => 'test', 'auditable_id' => 1, 'created_at' => now()->subMonths(13)]);
        $this->artisan('audit:prune --dry-run')->assertSuccessful();
        $this->assertDatabaseHas('audit_logs', ['action' => 'old']);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'old']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_directory_import_is_explicit_and_assigns_analyst_only(): void
    {
        [$admin,,$analyst] = $this->setupData();
        CompanySetting::create(['company_id' => $admin->current_company_id, 'aad_tenant_id' => 'tenant', 'aad_client_id' => 'client', 'aad_client_secret' => 'secret']);
        $person = ['id' => '00000000-0000-4000-8000-000000000003', 'displayName' => 'Pessoa Diretório', 'mail' => 'diretorio@example.test', 'userPrincipalName' => 'diretorio@example.test', 'accountEnabled' => true];
        Http::preventStrayRequests();
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'token']), 'graph.microsoft.com/v1.0/users?*' => Http::response(['value' => [$person]]), 'graph.microsoft.com/v1.0/users/*' => Http::response($person)]);
        $this->actingAs($analyst)->getJson('/api/v1/management/directory')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/management/directory')->assertOk()->assertJsonPath('users.0.displayName', 'Pessoa Diretório');
        $this->postJson('/api/v1/management/directory/import', ['id' => $person['id']])->assertCreated();
        $imported = User::where('email', $person['mail'])->firstOrFail();
        $this->assertSame('Analista',$imported->spotRoleLabel());
        $this->postJson('/api/v1/management/directory/import',['id' => $person['id']])->assertUnprocessable();
    }
    public function test_field_permissions_hide_values_and_reject_writes_without_resetting_progress(): void
    {
        [$admin,$project,$analyst]=$this->setupData();
        $project->update(['status'=>'in_progress','progress'=>55]);
        $analyst->profiles()->detach();
        $analyst->profiles()->attach(Profile::where('slug','gestor')->firstOrFail(),['company_id'=>$admin->current_company_id]);
        foreach(['view','edit'] as $op) DB::table('user_permission_overrides')->insert(['user_id'=>$analyst->id,'permission_id'=>DB::table('permissions')->where('slug','projects.fields.contract_value.'.$op)->value('id'),'allowed'=>false,'reason'=>'Teste de acesso por campo']);
        $this->actingAs($analyst)->getJson('/api/v1/projects')->assertOk()->assertJsonMissingPath('0.contract_value');
        $this->putJson('/api/v1/projects/'.$project->id,['contract_value'=>99])->assertUnprocessable();
        $this->putJson('/api/v1/projects/'.$project->id,['name'=>'Novo nome'])->assertOk();
        $this->assertDatabaseHas('projects',['id'=>$project->id,'progress'=>55,'status'=>'in_progress','contract_value'=>1000]);
    }

    public function test_task_visibility_and_mutation_permissions_are_independent(): void
    {
        [$admin,$project,$analyst]=$this->setupData();
        $task=Task::create(['project_id'=>$project->id,'user_id'=>$admin->id,'created_by'=>$admin->id,'title'=>'Registro privado']);
        $this->actingAs($analyst)->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(0,'tasks');
        DB::table('user_permission_overrides')->insert(['user_id'=>$analyst->id,'permission_id'=>DB::table('permissions')->where('slug','tasks.view_others')->value('id'),'allowed'=>true,'reason'=>'Consulta autorizada']);
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('tasks.0.can_edit',false)->assertJsonPath('tasks.0.can_delete',false);
        $this->deleteJson('/api/v1/tasks/'.$task->id)->assertForbidden();
        DB::table('user_permission_overrides')->insert(['user_id'=>$analyst->id,'permission_id'=>DB::table('permissions')->where('slug','tasks.delete_others')->value('id'),'allowed'=>true,'reason'=>'Exclusão autorizada']);
        $this->deleteJson('/api/v1/tasks/'.$task->id)->assertNoContent();
    }

    public function test_cancelled_project_can_be_edited_and_defaults_are_available(): void
    {
        [$admin,$project]=$this->setupData();
        $status=DB::table('project_statuses')->where('company_id',$admin->current_company_id)->where('slug','cancelled')->value('id');
        $this->actingAs($admin)->putJson('/api/v1/projects/'.$project->id,['project_status_id'=>$status])->assertOk()->assertJsonPath('status','cancelled');
        $this->putJson('/api/v1/projects/'.$project->id,['status'=>'cancelled','name'=>'Projeto cancelado revisado'])->assertOk();
        $this->getJson('/api/v1/management/settings')->assertOk()->assertJsonPath('audit_retention_months',12);
    }

}
