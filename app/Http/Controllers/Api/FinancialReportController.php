<?php

namespace App\Http\Controllers\Api;

use App\Domain\Finance\ProjectFinancialSummary;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FinancialReportController extends Controller
{
    public function show(Request $request, Project $project, ProjectFinancialSummary $summary): JsonResponse
    {
        Gate::authorize('financial.view');
        $this->ensureCompany($request, $project);

        return response()->json($summary->calculate($project));
    }

    public function portfolio(Request $request, ProjectFinancialSummary $calculator): JsonResponse
    {
        Gate::authorize('financial.view');
        $projects = Project::query()->where('company_id', $request->user()->current_company_id)
            ->with(['taxes.type', 'workLogs', 'expenses'])->get();
        $summaries = $projects->map(fn (Project $project) => $calculator->calculate($project));
        $revenue = $summaries->reduce(fn (BigDecimal $sum, array $item) => $sum->plus($item['actual']['total_revenue']), BigDecimal::zero());
        $profit = $summaries->reduce(fn (BigDecimal $sum, array $item) => $sum->plus($item['actual']['profit']), BigDecimal::zero());

        return response()->json([
            'project_counts' => $projects->groupBy('status')->map->count(),
            'totals' => [
                'projects' => $projects->count(),
                'revenue' => (string) $revenue->toScale(2, RoundingMode::HalfUp),
                'profit' => (string) $profit->toScale(2, RoundingMode::HalfUp),
                'margin_percent' => $revenue->isZero() ? null : (string) $profit->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HalfUp),
            ],
            'projects' => $summaries,
        ]);
    }

    private function ensureCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
    }
}
