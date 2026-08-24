<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\AnalystProjectRate;
use App\Models\Project;
use App\Models\ProjectTax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectConfigurationController extends Controller
{
    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('projects.update');
        $this->ensureCompany($request, $project);

        return response()->json($project->load([
            'members', 'taxes.type',
        ])->setRelation('rates', AnalystProjectRate::where('project_id', $project->id)->orderByDesc('effective_from')->get()));
    }

    public function addMember(Request $request, Project $project, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('projects.update');
        $this->mutable($request, $project);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('current_company_id', $request->user()->current_company_id)->where('active', true)],
            'role' => ['required', Rule::in(['analyst', 'manager'])],
            'relationship_type' => ['sometimes', Rule::in(['cpt_internal_analyst', 'external_analyst', 'responsible_manager'])],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);
        $member = $project->members()->updateOrCreate(['user_id' => $data['user_id']], [
            'role' => $data['role'], 'relationship_type' => $data['relationship_type'] ?? 'cpt_internal_analyst',
            'organization_id' => $data['organization_id'] ?? null, 'active' => true, 'access_revoked_at' => null, 'assigned_by' => $request->user()->id, 'assigned_at' => now(),
        ]);
        $audit->record('project.member_assigned', $member, $request->user(), [], $member->only(['project_id', 'user_id', 'role']));

        return response()->json($member, 201);
    }

    public function storeRate(Request $request, Project $project, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('financial.manage');
        $this->mutable($request, $project);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'normal_cost_rate' => ['required', 'numeric', 'min:0'],
            'overtime_cost_rate' => ['required', 'numeric', 'min:0'],
            'overtime_sale_rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        if (! $project->members()->where('user_id', $data['user_id'])->where('active', true)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'O usuário precisa ser membro ativo do projeto.']);
        }
        $overlap = AnalystProjectRate::query()->where('project_id', $project->id)->where('user_id', $data['user_id'])
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))
            ->when($data['effective_until'] ?? null, fn ($query, $until) => $query->whereDate('effective_from', '<=', $until))
            ->exists();
        if ($overlap) throw ValidationException::withMessages(['effective_from' => 'A vigência conflita com uma tarifa existente.']);

        $rate = AnalystProjectRate::create([...$data, 'project_id' => $project->id, 'created_by' => $request->user()->id]);
        $audit->record('project.rate_created', $rate, $request->user(), [], $rate->toArray());

        return response()->json($rate, 201);
    }

    public function replaceTaxes(Request $request, Project $project, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('financial.manage');
        $this->mutable($request, $project);
        $data = $request->validate([
            'taxes' => ['present', 'array'],
            'taxes.*.tax_type_id' => ['required', 'integer', 'distinct', Rule::exists('tax_types', 'id')->where('company_id', $request->user()->current_company_id)],
            'taxes.*.calculation_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'taxes.*.rate' => ['nullable', 'required_if:taxes.*.calculation_type,percentage', 'numeric', 'between:0,100'],
            'taxes.*.fixed_amount' => ['nullable', 'required_if:taxes.*.calculation_type,fixed', 'numeric', 'min:0'],
            'taxes.*.calculation_basis' => ['required', Rule::in(['contract_value', 'total_revenue'])],
        ]);
        $old = $project->taxes()->get()->toArray();
        DB::transaction(function () use ($project, $data): void {
            $project->taxes()->delete();
            foreach ($data['taxes'] as $tax) {
                $project->taxes()->create([...$tax, 'calculated_amount' => 0]);
            }
        });
        $new = $project->taxes()->get()->toArray();
        $audit->record('project.taxes_replaced', $project, $request->user(), ['taxes' => $old], ['taxes' => $new]);

        return response()->json(['taxes' => $project->taxes()->with('type')->get()]);
    }

    private function mutable(Request $request, Project $project): void
    {
        $this->ensureCompany($request, $project);
        abort_if($project->isFinalized(), 409, 'Projeto finalizado não pode ser configurado.');
    }

    private function ensureCompany(Request $request, Project $project): void
    {
        abort_unless($project->company_id && $project->company_id === $request->user()->current_company_id, 404);
    }
}
