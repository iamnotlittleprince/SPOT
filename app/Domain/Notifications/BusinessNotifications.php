<?php

namespace App\Domain\Notifications;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class BusinessNotifications
{
    public function __construct(private NotificationCenter $center) {}

    public function record(AuditLog $audit, Model $subject, User $actor, array $old, array $new): void
    {
        $action = $audit->action;
        $key = 'audit:'.$audit->id;
        $projectId = $subject instanceof Project ? $subject->id : $subject->getAttribute('project_id');
        $project = $projectId ? Project::find($projectId) : null;
        $emit = function ($ids, $event, $title, $body, $data = [], bool $includeActor = false) use ($key, $actor, $project) {
            foreach (User::whereIn('id', collect($ids)->filter()->unique())->get() as $recipient) {
                if (! $includeActor && $recipient->id === $actor->id) {
                    continue;
                }
                $this->center->record($recipient, $event, $key.':'.$event, $title, $body, $project, $data, $actor->name);
            }
        };
        if ($subject instanceof Task && in_array($action, ['task.created', 'task.updated', 'task.status_changed', 'task.priority_changed'], true)) {
            $emit([$subject->user_id, ...$subject->participants()->pluck('users.id')->all()], 'task_assigned',
                $action === 'task.created' ? 'Nova tarefa atribuída' : 'Tarefa atualizada',
                $subject->title, ['task_id' => $subject->id]);
        }
        if ($project && $subject instanceof ProjectMember && $action === 'project.member_assigned') {
            $emit([$subject->user_id], 'project_assigned', 'Você foi atribuído a um projeto', $project->name);
        }
        if ($project && $subject instanceof Project && in_array($action, ['project.created', 'project.updated', 'project.team_updated', 'project.finalized', 'project.reopened'], true)) {
            $ids = [...$project->members()->when(! in_array($action, ['project.finalized', 'project.reopened']), fn ($q) => $q->where('active', true))->pluck('user_id')->all(), $project->project_manager_id, $project->account_manager_id, $project->user_id];
            $life = in_array($action, ['project.finalized', 'project.reopened']);
            $emit($ids, $life ? 'project_finalized' : 'project_updated', match ($action) {
                'project.finalized' => 'Projeto finalizado', 'project.reopened' => 'Projeto reaberto', default => 'Projeto atualizado',
            }, $project->name);
        }
        if ($project && in_array($action, ['expense.created', 'expense.updated'], true) && $subject->getAttribute('status') === 'pending') {
            $ids = User::where('current_company_id', $project->company_id)->where('active', true)->get()
                ->filter(fn ($user) => $user->can('expenses.approve'))->pluck('id');
            $emit($ids, 'approval_pending', 'Despesa aguardando aprovação', $project->name.' — '.$subject->description);
        }
        if (in_array($action, ['expense.approved', 'expense.rejected'], true)) {
            $emit([$subject->submitted_by, $subject->analyst_id], 'expense_reviewed', $action === 'expense.approved' ? 'Despesa aprovada' : 'Despesa rejeitada', $subject->description, [], true);
        }
        if ($subject instanceof ProjectInvitation && in_array($action, ['invitation.accepted', 'invitation.revoked'], true)) {
            $emit([$subject->invited_by], 'invitation_status', $action === 'invitation.accepted' ? 'Convite aceito' : 'Convite revogado', $project?->name ?? 'Convite do projeto', [], true);
        }
        if ($action === 'document.created') {
            // Documentos são privados por proprietário, não pela equipe inteira.
            $emit([$subject->user_id], 'file_uploaded', 'Documento recebido', $subject->name, [], true);
        }
        if ($subject instanceof User) {
            $event = match ($action) {
                'user.login' => 'security_login', 'security.password_changed' => 'security_password',
                'security.provider_disconnected.google', 'security.provider_disconnected.microsoft', 'security.provider_linked.google', 'security.provider_linked.microsoft' => 'security_provider', default => null,
            };
            if ($event) {
                $this->center->record($subject, $event, $key, match ($event) {
                    'security_login' => 'Novo acesso à sua conta', 'security_password' => 'Senha da conta alterada', default => 'Integração da conta alterada',
                }, 'Uma alteração de segurança foi registrada na sua conta Spot.', null, [], $actor->name);
            }
        }
    }
}
