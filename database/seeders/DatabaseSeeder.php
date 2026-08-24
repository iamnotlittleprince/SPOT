<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['legal_name' => 'Computécnica Tecnologia Ltda.'],
            ['trade_name' => 'Computécnica', 'cnpj' => null],
        );
        $cptOrganizationId = DB::table('organizations')->insertGetId([
            'name' => 'Computécnica Tecnologia Ltda.', 'type' => 'cpt', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $permissions = [
            'security.manage' => ['Segurança', 'Administrar perfis, permissões e exceções'],
            'users.view' => ['Usuários', 'Visualizar usuários'],
            'users.manage' => ['Usuários', 'Cadastrar e alterar usuários'],
            'projects.view_all' => ['Projetos', 'Visualizar todos os projetos'],
            'projects.view_assigned' => ['Projetos', 'Visualizar projetos atribuídos'],
            'projects.create' => ['Projetos', 'Criar projetos'],
            'projects.update' => ['Projetos', 'Alterar projetos'],
            'projects.finalize' => ['Projetos', 'Finalizar projetos'],
            'projects.reopen' => ['Projetos', 'Reabrir projetos'],
            'projects.delete' => ['Projetos', 'Excluir projetos com justificativa'],
            'projects.restore' => ['Projetos', 'Restaurar projetos excluídos'],
            'financial.view' => ['Financeiro', 'Visualizar valores, custos, lucro e margem'],
            'financial.manage' => ['Financeiro', 'Alterar composição financeira'],
            'tasks.manage' => ['Tarefas', 'Criar e alterar tarefas'],
            'tasks.delete' => ['Tarefas', 'Excluir tarefas permitidas'],
            'tasks.view' => ['Tarefas', 'Visualizar tarefas permitidas'],
            'tasks.comment' => ['Tarefas', 'Comentar em tarefas permitidas'],
            'files.view' => ['Arquivos', 'Visualizar arquivos permitidos'],
            'files.upload' => ['Arquivos', 'Enviar arquivos permitidos'],
            'work_logs.create_own' => ['Apontamentos', 'Registrar os próprios apontamentos'],
            'work_logs.manage_own' => ['Apontamentos', 'Alterar os próprios apontamentos'],
            'work_logs.view_others' => ['Apontamentos', 'Visualizar apontamentos de outros analistas'],
            'work_logs.manage_others' => ['Apontamentos', 'Alterar apontamentos de outros analistas'],
            'work_logs.create_for_others' => ['Apontamentos', 'Registrar em nome de outro analista'],
            'expenses.create' => ['Despesas', 'Registrar despesas'],
            'expenses.approve' => ['Despesas', 'Aprovar ou rejeitar despesas'],
            'invitations.create' => ['Convidados', 'Criar convites de acesso'],
            'parameters.view' => ['Parâmetros', 'Visualizar parâmetros'],
            'parameters.create' => ['Parâmetros', 'Cadastrar parâmetros'],
            'parameters.update' => ['Parâmetros', 'Alterar parâmetros'],
            'audit.view' => ['Auditoria', 'Consultar registros de auditoria'],
        ];

        foreach ($permissions as $slug => [$module, $description]) {
            Permission::query()->updateOrCreate(['slug' => $slug], [
                'name' => $description, 'module' => $module, 'description' => $description,
            ]);
        }

        $profiles = [
            'administrador' => array_keys($permissions),
            'gestor-administrador' => array_keys($permissions),
            'gestor' => ['users.view', 'projects.view_all', 'projects.create', 'projects.update', 'projects.finalize', 'projects.reopen', 'financial.view', 'financial.manage', 'tasks.manage', 'tasks.delete', 'work_logs.create_own', 'work_logs.manage_own', 'work_logs.view_others', 'work_logs.manage_others', 'work_logs.create_for_others', 'expenses.create', 'expenses.approve', 'invitations.create', 'parameters.view', 'audit.view'],
            'analista' => ['projects.view_assigned', 'tasks.manage', 'work_logs.create_own', 'work_logs.manage_own', 'expenses.create', 'parameters.view'],
            'convidado' => ['projects.view_assigned'],
        ];

        foreach ($profiles as $slug => $profilePermissions) {
            $profile = Profile::query()->updateOrCreate(['slug' => $slug], [
                'name' => match ($slug) {
                    'administrador' => 'Administrador',
                    'gestor-administrador' => 'Gestor administrador',
                    'gestor' => 'Gestor',
                    'analista' => 'Analista',
                    default => 'Convidado',
                },
                'is_system' => true,
            ]);
            $profile->permissions()->sync(Permission::query()->whereIn('slug', $profilePermissions)->pluck('id'));
        }

        if (app()->isProduction() && ! env('INITIAL_ADMIN_PASSWORD')) {
            throw new RuntimeException('Defina INITIAL_ADMIN_PASSWORD antes de executar o seed em produção.');
        }

        $admin = User::query()->firstOrCreate(
            ['email' => env('INITIAL_ADMIN_EMAIL', 'admin@computecnica.com.br')],
            ['name' => 'Administrador', 'password' => env('INITIAL_ADMIN_PASSWORD', 'senha12345'), 'email_verified_at' => now()],
        );
        $admin->update(['current_company_id' => $company->id, 'organization_id' => $cptOrganizationId, 'job_title' => 'Administrador de projetos', 'department' => 'Projetos', 'active' => true, 'account_status' => 'active']);
        $admin->profiles()->syncWithoutDetaching([
            Profile::query()->where('slug', 'administrador')->value('id') => ['company_id' => $company->id],
        ]);

        $now = now();
        foreach (['planning' => ['Planejamento', '#64748b'], 'in-progress' => ['Em andamento', '#2563eb'], 'completed' => ['Concluído', '#dc2626'], 'cancelled' => ['Cancelado', '#6b7280']] as $slug => [$name, $color]) {
            DB::table('project_statuses')->updateOrInsert(['company_id' => $company->id, 'slug' => $slug], ['name' => $name, 'color' => $color, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['on-time' => ['No prazo', '#16a34a'], 'overdue' => ['Fora do prazo', '#dc2626'], 'frozen' => ['Congelado', '#0ea5e9']] as $slug => [$name, $color]) {
            DB::table('project_situations')->updateOrInsert(['company_id' => $company->id, 'slug' => $slug], ['name' => $name, 'color' => $color, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['ISS', 'PIS', 'COFINS'] as $name) {
            DB::table('tax_types')->updateOrInsert(['company_id' => $company->id, 'slug' => strtolower($name)], ['name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['Atendimento técnico', 'Reunião', 'Implantação', 'Documentação'] as $name) {
            DB::table('activity_types')->updateOrInsert(['company_id' => $company->id, 'slug' => str($name)->ascii()->slug()], ['name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['Combustível', 'Estacionamento', 'Pedágio', 'Passagem aérea', 'Passagem de ônibus', 'Alimentação', 'Táxi / Uber', 'Locação de carro', 'Hospedagem'] as $name) {
            DB::table('expense_types')->updateOrInsert(['company_id' => $company->id, 'slug' => str($name)->ascii()->slug()], ['name' => $name, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('system_settings')->updateOrInsert(['key' => 'audit.retention_months'], ['value' => json_encode(12), 'encrypted' => false, 'updated_at' => $now, 'created_at' => $now]);

        foreach (['Papelaria', 'Eletrônicos', 'Bebidas'] as $name) {
            Category::query()->firstOrCreate(['name' => $name]);
        }
    }
}
