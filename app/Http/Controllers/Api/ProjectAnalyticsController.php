<?php

namespace App\Http\Controllers\Api;

use App\Domain\Finance\ProjectFinancialSummary;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Process\Process;

class ProjectAnalyticsController extends Controller
{
    public function __invoke(Request $request, ProjectFinancialSummary $calculator): JsonResponse
    {
        Gate::authorize('financial.view');
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'project_id' => ['nullable', 'integer'],
        ]);

        $projects = Project::query()
            ->where('company_id', $request->user()->current_company_id)
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(start_date, created_at)'), '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(start_date, created_at)'), '<=', $date))
            ->when($filters['project_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->with(['taxes.type', 'workLogs', 'expenses', 'projectManager:id,name'])
            ->get();

        $rows = $projects->map(function (Project $project) use ($calculator): array {
            $financial = $calculator->calculate($project);
            $status = \Illuminate\Support\Facades\DB::table('project_statuses')->where('id',$project->project_status_id)->value('slug');
            $situation = \Illuminate\Support\Facades\DB::table('project_situations')->where('id',$project->project_situation_id)->value('slug');
            return [
                'id' => $project->id, 'name' => $project->name, 'client' => \Illuminate\Support\Facades\DB::table('clients')->where('id',$project->client_id)->value('name') ?? $project->client_name,
                'account_manager' => \Illuminate\Support\Facades\DB::table('users')->where('id',$project->account_manager_id)->value('name'), 'unpriced_tasks' => $financial['unpriced_tasks'], 'manager' => $project->projectManager?->name, 'status' => $situation === 'frozen' ? 'frozen' : ($status ? str_replace('-', '_', $status) : $project->status),
                'progress' => $project->progress, 'due_date' => ($project->due_date ?? $project->end_date)?->toDateString(),
                'start_date' => $project->start_date?->toDateString(), 'end_date' => $project->end_date?->toDateString(),
                'created_at' => $project->created_at?->toDateString(), 'finalized' => $project->isFinalized(),
                'contract_value' => $project->contract_value, 'actual_revenue' => $financial['actual']['total_revenue'],
                'actual_cost' => $financial['actual']['total_deductions'], 'profit' => $financial['actual']['profit'],
                'worked_hours' => $financial['actual']['worked_minutes'] / 60,
                'estimated_hours' => $financial['estimated']['minutes'] / 60,
            ];
        });

        $process = new Process([
            env('PYTHON_BINARY', 'python3'), base_path('scripts/project_analytics.py'),
        ], base_path(), null, json_encode(['today' => now()->toDateString(), 'projects' => $rows], JSON_THROW_ON_ERROR), 30);
        $process->run();

        if (! $process->isSuccessful()) {
            return response()->json([
                'message' => 'Não foi possível processar os gráficos. Instale as dependências com: pip install -r requirements.txt',
                'detail' => app()->isLocal() ? trim($process->getErrorOutput()) : null,
            ], 503);
        }

        return response()->json([
            ...json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR),
            'generated_at' => now()->toIso8601String(), 'processor' => 'Python + Pandas',
        ]);
    }
}
