<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validate([
            'before' => ['nullable', 'string', 'max:26'],
            'project_id' => ['required_with:task_id', 'nullable', 'integer', 'min:1'],
            'task_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = AuditLog::query()->with('user:id,name')->where('company_id', $user->current_company_id)
            ->orderByDesc('id')->when($filters['before'] ?? null, fn ($q, $id) => $q->where('id', '<', $id));
        if (! $user->can('audit.view')) {
            $query->where('user_id', $user->id);
        }

        if ($projectId = $filters['project_id'] ?? null) {
            abort_unless(Project::withTrashed()->where('company_id', $user->current_company_id)->whereKey($projectId)->exists(), 404);
            $query->where(function ($scope) use ($projectId) {
                $scope->where('new_values->_context->project_id', $projectId)
                    ->orWhere('new_values->project_id', $projectId)->orWhere('old_values->project_id', $projectId)
                    ->orWhere(fn ($q) => $q->where('auditable_type', (new Project)->getMorphClass())->where('auditable_id', $projectId))
                    ->orWhere(fn ($q) => $q->where('auditable_type', (new Task)->getMorphClass())
                        ->whereIn('auditable_id', Task::where('project_id', $projectId)->select('id')));
            });
        }
        if ($taskId = $filters['task_id'] ?? null) {
            $query->where(fn ($scope) => $scope
                ->where(fn ($q) => $q->where('auditable_type', (new Task)->getMorphClass())->where('auditable_id', $taskId))
                ->orWhere('new_values->_context->task_id', $taskId)
                ->orWhere('new_values->task_id', $taskId)->orWhere('old_values->task_id', $taskId));
        }

        $detailed = $user->can('security.manage');
        $events = $query->limit(101)->get();
        $page = $events->take(100)->map(function (AuditLog $event) use ($detailed) {
            $old = $event->old_values ?? [];
            $new = $event->new_values ?? [];
            $context = $new['_context'] ?? [
                'project_id' => $new['project_id'] ?? $old['project_id'] ?? ($event->auditable_type === (new Project)->getMorphClass() ? $event->auditable_id : null),
                'task_id' => $event->auditable_type === (new Task)->getMorphClass() ? $event->auditable_id : null,
                'subject_name' => $new['title'] ?? $new['name'] ?? $old['title'] ?? $old['name'] ?? null,
            ];
            $result = $event->only(['id', 'company_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'ip_address', 'created_at']);
            $result['user'] = $event->user;
            $result['context'] = $context;
            if ($detailed) {
                unset($old['_context'], $new['_context']);
                $result += ['old_values' => $old ?: null, 'new_values' => $new ?: null, 'request_id' => $event->request_id];
            }
            return $result;
        })->values();
        return response()->json(['events' => $page, 'next' => $events->count() > 100 ? $page->last()['id'] : null]);
    }
}
