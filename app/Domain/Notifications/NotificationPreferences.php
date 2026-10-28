<?php

namespace App\Domain\Notifications;

use App\Models\User;
use Carbon\CarbonImmutable;

final class NotificationPreferences
{
    // Somente eventos produzidos pelo sistema são oferecidos na interface.
    public const EVENTS = [
        'project_assigned' => ['Projetos', 'Projeto atribuído', null],
        'project_updated' => ['Projetos', 'Alteração de prazo, situação ou responsável', null],
        'project_finalized' => ['Projetos', 'Projeto finalizado ou reaberto', null],
        'task_assigned' => ['Tarefas', 'Tarefa atribuída ou atualizada', null],
        'task_due' => ['Tarefas', 'Tarefa próxima do prazo ou atrasada', null],
        'calendar_due' => ['Agenda', 'Compromisso começa em até 30 minutos', null],
        'file_uploaded' => ['Arquivos', 'Arquivo enviado aos seus documentos', null],
        'expense_reviewed' => ['Despesas', 'Despesa aprovada ou rejeitada', 'expenses.create'],
        'approval_pending' => ['Gestão', 'Nova aprovação pendente', 'expenses.approve'],
        'invitation_status' => ['Gestão', 'Convite aceito, revogado ou próximo de expirar', 'invitations.create'],
    ];

    public function events(User $user): array
    {
        return collect(self::EVENTS)->filter(fn ($event) => $event[2] === null || $user->can($event[2]))
            ->map(fn ($event, $key) => ['key' => $key, 'group' => $event[0], 'label' => $event[1]])->all();
    }

    public function for(User $user): array
    {
        $defaults = [
            'events' => collect($this->events($user))->mapWithKeys(fn ($event, $key) => [$key => [
                'in_app' => true, 'email' => in_array($key, ['project_assigned', 'task_assigned', 'task_due', 'calendar_due', 'approval_pending'], true),
            ]])->all(),
            'digest' => ['frequency' => 'none', 'time' => '08:00'],
            'quiet_hours' => ['enabled' => false, 'start' => '19:00', 'end' => '08:00'],
        ];

        return array_replace_recursive($defaults, $user->notification_preferences ?? []);
    }

    public function channels(User $user, string $event): array
    {
        $security = ['security_login' => 'notify_new_login', 'security_password' => 'notify_password_change', 'security_provider' => 'notify_provider_link'];
        if (isset($security[$event])) {
            $enabled = (bool) $user->{$security[$event]};

            return ['in_app' => $enabled, 'email' => $enabled];
        }
        if (! array_key_exists($event, $this->events($user))) {
            return ['in_app' => false, 'email' => false];
        }

        return $this->for($user)['events'][$event] ?? ['in_app' => false, 'email' => false];
    }

    public function availableAt(User $user, ?CarbonImmutable $at = null): CarbonImmutable
    {
        $at = ($at ?? CarbonImmutable::now())->setTimezone($user->timezone ?: 'UTC');
        $quiet = $this->for($user)['quiet_hours'];
        if (! $quiet['enabled'] || $quiet['start'] === $quiet['end']) {
            return $at->utc();
        }
        $time = $at->format('H:i');
        $start = $quiet['start'];
        $end = $quiet['end'];
        if ($start < $end) {
            return ($time >= $start && $time < $end) ? $at->setTimeFromTimeString($end)->utc() : $at->utc();
        }
        if ($time >= $start) {
            return $at->addDay()->setTimeFromTimeString($end)->utc();
        }

        return $time < $end ? $at->setTimeFromTimeString($end)->utc() : $at->utc();
    }

    public function digestPeriod(User $user): ?string
    {
        $digest = $this->for($user)['digest'];
        if ($digest['frequency'] === 'none') {
            return null;
        }
        $local = CarbonImmutable::now($user->timezone ?: 'UTC');
        $start = ($digest['frequency'] === 'weekly' ? $local->startOfWeek(CarbonImmutable::MONDAY) : $local->startOfDay())->setTimeFromTimeString($digest['time']);
        if ($local->lessThan($start)) {
            return '';
        }

        return $digest['frequency'].':'.$start->toDateString();
    }
}
