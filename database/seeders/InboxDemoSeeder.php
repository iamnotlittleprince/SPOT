<?php

namespace Database\Seeders;

use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class InboxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = filled(env('DEMO_INBOX_EMAIL'))
            ? User::where('email', env('DEMO_INBOX_EMAIL'))->first()
            : User::whereNotNull('last_login_at')->latest('last_login_at')->first()
                ?? User::where('email', 'admin@computecnica.com.br')->first();
        if (! $user) return;
        $projects = Project::where('company_id', $user->current_company_id)
            ->whereHas('members', fn ($members) => $members->where('user_id', $user->id)->where('active', true))
            ->limit(6)->get();
        if ($projects->isEmpty()) $projects = Project::where('company_id', $user->current_company_id)->limit(6)->get();
        $messages = [
            ['task', 'Nova tarefa atribuída a você', 'Revise o fluxo de autenticação e registre os pontos de atenção antes da próxima reunião.', 'Camila Oliveira', false],
            ['mention', 'Você foi mencionado em uma tarefa', 'Mariana solicitou sua opinião na validação da proposta final do projeto.', 'Mariana Costa', false],
            ['document', 'Novo documento disponível', 'O memorial descritivo atualizado foi publicado e está pronto para revisão.', 'Rafael Santos', false],
            ['update', 'Projeto avançou de etapa', 'O projeto saiu de Planejamento e agora está Em andamento.', 'Administrador', false],
            ['task', 'Prazo termina amanhã', 'Uma atividade importante atribuída ao seu time vence nas próximas 24 horas.', 'Spot', false],
            ['document', 'Cliente enviou um arquivo', 'Uma nova planilha de requisitos foi adicionada aos documentos do projeto.', 'Cliente do projeto', true],
            ['mention', 'Resposta em seu comentário', 'Lucas respondeu ao comentário que você deixou na tarefa de homologação.', 'Lucas Almeida', true],
            ['task', 'Tarefa concluída pelo time', 'A atividade de levantamento técnico foi marcada como concluída.', 'Rafael Santos', true],
            ['update', 'Reunião de acompanhamento agendada', 'Uma reunião de acompanhamento foi adicionada à agenda para esta semana.', 'Camila Oliveira', true],
            ['update', 'Resumo semanal disponível', 'Confira o andamento dos projetos, tarefas concluídas e próximos prazos.', 'Spot', true],
        ];
        foreach ($messages as $index => [$type, $title, $body, $actor, $read]) {
            $item = InboxItem::updateOrCreate(['user_id' => $user->id, 'title' => $title], [
                'project_id' => $projects[$index % max(1, $projects->count())]?->id,
                'type' => $type, 'body' => $body, 'actor_name' => $actor,
                'read_at' => $read ? now()->subMinutes($index * 23) : null,
                'archived_at' => null,
            ]);
            $item->forceFill(['created_at' => now()->subMinutes($index * 37)])->save();
        }

        $this->command?->info("Caixa de entrada populada para {$user->email}.");
    }
}
