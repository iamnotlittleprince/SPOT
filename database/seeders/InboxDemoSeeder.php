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
        $user = User::where('email', 'admin@computecnica.com.br')->first();
        if (! $user) return;
        $projects = Project::limit(4)->get();
        $messages = [
            ['task', 'Nova tarefa atribuída a você', 'Revise o fluxo de autenticação e registre os pontos de atenção antes da próxima reunião.', 'Gestor do projeto'],
            ['mention', 'Você foi mencionado em uma tarefa', 'Seu parecer foi solicitado na atividade de validação da proposta final.', 'Mariana Costa'],
            ['document', 'Novo documento disponível', 'A versão atualizada do memorial descritivo foi adicionada ao projeto.', 'Aline Souza'],
            ['update', 'Status do projeto atualizado', 'O projeto avançou para a etapa Em andamento.', 'Carlos Mendes'],
            ['task', 'Prazo próximo', 'Uma tarefa atribuída a você vence nas próximas 24 horas.', 'Spot'],
        ];
        foreach ($messages as $index => [$type, $title, $body, $actor]) {
            InboxItem::firstOrCreate(['user_id' => $user->id, 'title' => $title], ['project_id' => $projects[$index % max(1, $projects->count())]?->id, 'type' => $type, 'body' => $body, 'actor_name' => $actor, 'created_at' => now()->subMinutes($index * 47)]);
        }
    }
}
