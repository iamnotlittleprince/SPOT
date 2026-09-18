<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Expenses\Actions\ReviewProjectExpense;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectExpenseController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->ensureProjectAccess($request, $project);

        return response()->json($project->expenses()->with('submitter:id,name')->latest('expense_date')->get());
    }

    public function store(Request $request, Project $project, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('expenses.create');
        $this->ensureProjectAccess($request, $project);
        abort_if($project->isFinalized(), 409, 'Projeto finalizado não aceita novas despesas.');
        $data = $request->validate([
            'analyst_id' => ['sometimes','integer',Rule::exists('users','id')->where('current_company_id',$request->user()->current_company_id)->where('active',true)],
            'expense_type_id' => ['required', 'integer', Rule::exists('expense_types', 'id')->where('company_id', $request->user()->current_company_id)],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'receipt_path' => ['nullable', 'string', 'max:500'],
        ]);
        if (($data['analyst_id'] ?? $request->user()->id) !== $request->user()->id) {
            Gate::authorize('financial.manage');
            abort_unless($project->members()->where('user_id',$data['analyst_id'])->where('active',true)->exists(),422,'Analista não atribuído ao projeto.');
        }
        $data['analyst_id'] = $data['analyst_id'] ?? $request->user()->id;
        $expense = $project->expenses()->create([...$data, 'submitted_by' => $request->user()->id, 'status' => 'pending']);
        $audit->record('expense.created', $expense, $request->user(), [], $expense->only(['project_id', 'expense_type_id', 'amount', 'status']));

        return response()->json($expense, 201);
    }

    public function review(Request $request, ProjectExpense $expense, ReviewProjectExpense $action): JsonResponse
    {
        Gate::authorize('expenses.approve');
        $this->ensureProjectAccess($request, $expense->project);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'required_if:decision,rejected', 'string', 'min:5', 'max:1000'],
        ]);

        return response()->json($action->execute($expense, $request->user(), $data['decision'], $data['reason'] ?? null));
    }

    private function ensureProjectAccess(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
        if ($request->user()->can('projects.view_all')) return;
        Gate::authorize('projects.view_assigned');
        abort_unless($project->members()->where('user_id', $request->user()->id)->where('active', true)->exists(), 404);
    }
}
