<?php

namespace App\Domain\WorkLogs\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\AnalystProjectRate;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateWorkLog
{
    public function __construct(private AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function execute(Project $project, User $actor, array $data): WorkLog
    {
        return DB::transaction(function () use ($project, $actor, $data): WorkLog {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($project->isFinalized()) {
                throw ValidationException::withMessages(['project' => 'Projeto finalizado não aceita apontamentos.']);
            }

            $analystId = (int) ($data['analyst_id'] ?? $actor->id);
            if (! $project->members()->where('user_id', $analystId)->where('active', true)->exists()) {
                throw ValidationException::withMessages(['analyst_id' => 'O analista não está atribuído ao projeto.']);
            }

            $rate = AnalystProjectRate::query()
                ->where('project_id', $project->id)->where('user_id', $analystId)
                ->whereDate('effective_from', '<=', $data['worked_on'])
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['worked_on']))
                ->latest('effective_from')->first();
            if (! $rate) {
                throw ValidationException::withMessages(['worked_on' => 'Não existe uma tarifa vigente para o analista nessa data.']);
            }

            $overtime = (bool) ($data['is_overtime'] ?? false);
            $log = WorkLog::create([
                ...$data,
                'project_id' => $project->id,
                'analyst_id' => $analystId,
                'created_by' => $actor->id,
                'is_overtime' => $overtime,
                'cost_rate_snapshot' => $overtime ? $rate->overtime_cost_rate : $rate->normal_cost_rate,
                'sale_rate_snapshot' => $overtime ? $rate->overtime_sale_rate : 0,
                'status' => 'submitted',
            ]);
            $this->audit->record('work_log.created', $log, $actor, [], $log->only(['project_id', 'analyst_id', 'worked_on', 'duration_minutes', 'is_overtime', 'cost_rate_snapshot', 'sale_rate_snapshot']));

            return $log->load('analyst:id,name');
        });
    }
}
