<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ClientDemoSeeder::class);
        $admin = User::query()->where('email', env('INITIAL_ADMIN_EMAIL', 'admin@computecnica.com.br'))->firstOrFail();
        $analystProfile = Profile::query()->where('slug', 'analista')->firstOrFail();
        $demoPassword = env('DEMO_USER_PASSWORD', 'Demo@Spot2026');
        $people = collect([
            ['name' => 'Mariana Costa', 'email' => 'mariana.demo@computecnica.com.br', 'job_title' => 'Analista de Projetos', 'department' => 'Projetos'],
            ['name' => 'Rafael Santos', 'email' => 'rafael.demo@computecnica.com.br', 'job_title' => 'Analista de Infraestrutura', 'department' => 'Tecnologia'],
            ['name' => 'Camila Oliveira', 'email' => 'camila.demo@computecnica.com.br', 'job_title' => 'Analista de Processos', 'department' => 'Consultoria'],
            ['name' => 'Lucas Almeida', 'email' => 'lucas.demo@computecnica.com.br', 'job_title' => 'Analista de Sistemas', 'department' => 'Tecnologia'],
        ])->map(function (array $person) use ($admin, $analystProfile, $demoPassword): User {
            $user = User::query()->updateOrCreate(['email' => $person['email']], [
                ...$person,
                'password' => $demoPassword,
                'email_verified_at' => now(),
                'current_company_id' => $admin->current_company_id,
                'organization_id' => $admin->organization_id,
                'timezone' => 'America/Sao_Paulo',
                'active' => true,
                'account_status' => 'active',
            ]);
            $user->profiles()->syncWithoutDetaching([$analystProfile->id => ['company_id' => $admin->current_company_id]]);
            return $user;
        });

        $definitions = [
            ['name' => 'Portal de Serviços do Cliente', 'client_name' => 'Alfa Indústria', 'status' => 'in_progress', 'progress' => 68, 'due_date' => today()->addDays(18), 'members' => [0, 1, 2]],
            ['name' => 'Migração de Infraestrutura', 'client_name' => 'Beta Logística', 'status' => 'in_progress', 'progress' => 42, 'due_date' => today()->addDays(32), 'members' => [1, 3]],
            ['name' => 'Mapeamento de Processos', 'client_name' => 'Gamma Serviços', 'status' => 'planning', 'progress' => 20, 'due_date' => today()->addDays(45), 'members' => [0, 2]],
            ['name' => 'Modernização do Atendimento', 'client_name' => 'Delta Comércio', 'status' => 'stopped', 'progress' => 55, 'due_date' => today()->addDays(12), 'members' => [2, 3]],
        ];

        $projects = collect($definitions)->map(function (array $definition) use ($admin, $people): Project {
            $memberIndexes = $definition['members'];
            unset($definition['members']);
            $project = Project::query()->updateOrCreate(
                ['company_id' => $admin->current_company_id, 'name' => $definition['name']],
                [...$definition, 'user_id' => $admin->id],
            );
            $project->members()->updateOrCreate(['user_id' => $admin->id], [
                'role' => 'manager', 'relationship_type' => 'responsible_manager', 'organization_id' => $admin->organization_id,
                'permissions' => ['tasks.view', 'tasks.comment', 'files.view', 'files.upload'], 'active' => true, 'access_revoked_at' => null,
                'assigned_by' => $admin->id, 'assigned_at' => now(),
            ]);
            foreach ($memberIndexes as $index) {
                $project->members()->updateOrCreate(['user_id' => $people[$index]->id], [
                    'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'organization_id' => $admin->organization_id,
                    'permissions' => ['tasks.view', 'tasks.comment', 'files.view', 'files.upload'], 'active' => true, 'access_revoked_at' => null,
                    'assigned_by' => $admin->id, 'assigned_at' => now(),
                ]);
            }
            return $project;
        });

        $tasks = [
            [0, 'Validar proposta final com o cliente', 'todo', 'high', today()],
            [0, 'Revisar fluxo de autenticação', 'in_progress', 'medium', today()->addDays(2)],
            [0, 'Publicar documentação da entrega', 'done', 'low', today()->subDay()],
            [1, 'Inventariar servidores do ambiente atual', 'in_progress', 'high', today()->subDay()],
            [1, 'Preparar plano de migração', 'todo', 'high', today()->addDays(4)],
            [2, 'Agendar entrevistas com as áreas', 'todo', 'medium', today()->addDays(3)],
            [2, 'Consolidar mapa do processo atual', 'todo', 'low', today()->addDays(8)],
            [3, 'Registrar dependências para retomada', 'todo', 'medium', null],
        ];
        foreach ($tasks as $position => [$projectIndex, $title, $status, $priority, $dueDate]) {
            Task::query()->updateOrCreate(['project_id' => $projects[$projectIndex]->id, 'title' => $title], [
                'user_id' => $admin->id, 'status' => $status, 'priority' => $priority,
                'due_date' => $dueDate, 'position' => $position,
            ]);
        }
    }
}
