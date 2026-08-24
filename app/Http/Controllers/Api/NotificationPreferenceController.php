<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    private const EVENTS = [
        'project_assigned' => ['Projetos', 'Projeto atribuído', null],
        'project_updated' => ['Projetos', 'Alteração de prazo, situação ou responsável', null],
        'project_finalized' => ['Projetos', 'Projeto finalizado ou reaberto', null],
        'task_assigned' => ['Tarefas', 'Nova tarefa atribuída', null],
        'task_due' => ['Tarefas', 'Tarefa próxima do prazo ou atrasada', null],
        'task_comment' => ['Tarefas', 'Comentários e menções', null],
        'file_uploaded' => ['Arquivos', 'Novo arquivo ou versão', null],
        'file_review' => ['Arquivos', 'Solicitação de revisão', null],
        'work_log_reviewed' => ['Apontamentos', 'Apontamento aprovado, rejeitado ou devolvido', 'work_logs.create_own'],
        'expense_reviewed' => ['Despesas', 'Despesa aprovada, rejeitada ou devolvida', 'expenses.create'],
        'approval_pending' => ['Gestão', 'Nova aprovação pendente', 'expenses.approve'],
        'invitation_status' => ['Gestão', 'Convite aceito, revogado ou próximo de expirar', 'invitations.create'],
    ];

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function update(Request $request): JsonResponse
    {
        $available = array_keys($this->availableEvents($request));
        $data = $request->validate([
            'events' => ['required', 'array'],
            'events.*.in_app' => ['required', 'boolean'],
            'events.*.email' => ['required', 'boolean'],
            'digest.frequency' => ['required', Rule::in(['none', 'daily', 'weekly'])],
            'digest.time' => ['required', 'date_format:H:i'],
            'quiet_hours.enabled' => ['required', 'boolean'],
            'quiet_hours.start' => ['required', 'date_format:H:i'],
            'quiet_hours.end' => ['required', 'date_format:H:i'],
        ]);
        $unknown = array_diff(array_keys($data['events']), $available);
        abort_if($unknown, 422, 'Uma ou mais preferências não estão disponíveis para este perfil.');

        $preferences = [
            'events' => collect($data['events'])->only($available)->all(),
            'digest' => $data['digest'],
            'quiet_hours' => $data['quiet_hours'],
        ];
        $request->user()->update(['notification_preferences' => $preferences]);

        return response()->json(['preferences' => $preferences]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->user()->update(['notification_preferences' => null]);
        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $events = $this->availableEvents($request);
        $defaults = $this->defaults(array_keys($events));
        $stored = $request->user()->notification_preferences ?? [];
        $preferences = array_replace_recursive($defaults, $stored);
        $preferences['events'] = collect($preferences['events'])->only(array_keys($events))->all();

        return ['events' => $events, 'preferences' => $preferences, 'timezone' => $request->user()->timezone];
    }

    private function availableEvents(Request $request): array
    {
        return collect(self::EVENTS)->filter(fn (array $event) => $event[2] === null || $request->user()->can($event[2]))
            ->map(fn (array $event, string $key) => ['key' => $key, 'group' => $event[0], 'label' => $event[1]])->all();
    }

    private function defaults(array $keys): array
    {
        return [
            'events' => collect($keys)->mapWithKeys(fn ($key) => [$key => ['in_app' => true, 'email' => in_array($key, ['project_assigned', 'task_assigned', 'task_due', 'approval_pending'], true)]])->all(),
            'digest' => ['frequency' => 'none', 'time' => '08:00'],
            'quiet_hours' => ['enabled' => false, 'start' => '19:00', 'end' => '08:00'],
        ];
    }
}
