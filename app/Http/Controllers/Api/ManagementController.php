<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Projects\ProjectFields;
use App\Domain\Tasks\TaskCosting;
use App\Http\Controllers\Controller;
use App\Models\AnalystProjectRate;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    private const TABLES = ['clients', 'project_statuses', 'project_situations', 'activity_types', 'expense_types', 'tax_types'];

    public function access(Request $r)
    {
        $abilities = ['projects.create', 'projects.update', 'projects.delete', 'projects.finalize', 'projects.reopen', 'financial.view', 'financial.manage', 'parameters.view', 'parameters.create', 'parameters.update', 'security.manage', 'expenses.create', 'expenses.approve', 'audit.view'];

        return response()->json(collect($abilities)->mapWithKeys(fn ($ability) => [$ability => $r->user()->can($ability)]));
    }

    private function table(string $type): string
    {
        abort_unless(in_array($type, self::TABLES, true), 404);

        return $type;
    }

    public function parameters(Request $r, string $type)
    {
        Gate::authorize('parameters.view');
        $q = DB::table($this->table($type))->where('company_id', $r->user()->current_company_id);
        if ($type === 'clients') {
            $q->whereNull('deleted_at');
        }

        return response()->json($q->orderBy('name')->get());
    }

    public function saveParameter(Request $r, string $type, ?int $id = null)
    {
        Gate::authorize($id ? 'parameters.update' : 'parameters.create');
        $table = $this->table($type);
        $company = $r->user()->current_company_id;
        $data = $r->validate(['name' => ['required', 'string', 'max:150'], 'active' => ['required', 'boolean'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30']]);
        $old = $id ? DB::table($table)->where('company_id', $company)->where('id', $id)->first() : null;
        if ($id) {
            abort_unless($old, 404);
        }
        $values = ['name' => $data['name'], 'active' => $data['active'], 'updated_at' => now()];
        if ($type === 'clients') {
            $values += ['email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null];
        } else {
            $values += ['slug' => $old?->slug ?? Str::slug($data['name']).'-'.Str::lower(Str::random(6)), 'updated_at' => now()];
        }
        $id = DB::transaction(function () use ($r, $table, $company, $values, $id, $old) {
            if ($id) {
                DB::table($table)->where('id', $id)->update($values);
            } else {
                $id = DB::table($table)->insertGetId([...$values, 'company_id' => $company, 'created_at' => now()]);
            }
            $this->audit($r, 'parameter.saved', $table, $id, (array) $old, $values);

            return $id;
        });

        return response()->json(DB::table($table)->find($id), $old ? 200 : 201);
    }

    public function deleteParameter(Request $r, string $type, int $id)
    {
        Gate::authorize('parameters.update');
        $table = $this->table($type);
        $row = DB::table($table)->where('company_id', $r->user()->current_company_id)->find($id);
        abort_unless($row, 404);
        // Archive references instead of destroying historical project data.
        DB::transaction(function () use ($r, $table, $id, $row) {
            DB::table($table)->where('id', $id)->update(['active' => false]);
            $this->audit($r, 'parameter.archived', $table, $id, (array) $row, ['active' => false]);
        });

        return response()->noContent();
    }

    public function settings(Request $r)
    {
        Gate::authorize('security.manage');
        $s = CompanySetting::firstOrCreate(['company_id' => $r->user()->current_company_id]);

        return response()->json([...$s->toArray(), 'aad_secret_configured' => ! empty($s->aad_client_secret)]);
    }

    public function saveSettings(Request $r)
    {
        Gate::authorize('security.manage');
        $data = $r->validate(['audit_retention_months' => ['required', 'integer', 'min:1', 'max:120'], 'aad_tenant_id' => ['nullable', 'uuid'], 'aad_client_id' => ['nullable', 'uuid'], 'aad_client_secret' => ['nullable', 'string', 'max:4000']]);
        $s = CompanySetting::firstOrCreate(['company_id' => $r->user()->current_company_id]);
        if (empty($data['aad_client_secret'])) {
            unset($data['aad_client_secret']);
        }
        $s->update($data);
        app(AuditRecorder::class)->record('settings.updated', $s, $r->user(), [], collect($data)->except('aad_client_secret')->all());

        return $this->settings($r);
    }

    public function permissions(Request $r, User $user)
    {
        Gate::authorize('security.manage');
        abort_unless($user->current_company_id === $r->user()->current_company_id, 404);

        return response()->json(Permission::orderBy('module')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'module' => $p->module, 'allowed' => str_starts_with($p->slug, 'projects.fields.') ? ProjectFields::allowed($user, explode('.', $p->slug)[2], explode('.', $p->slug)[3]) : $user->hasPermission($p->slug)]));
    }

    public function savePermissions(Request $r, User $user)
    {
        Gate::authorize('security.manage');
        abort_unless($user->current_company_id === $r->user()->current_company_id, 404);
        $data = $r->validate(['permissions' => ['required', 'array'], 'permissions.*.id' => ['required', 'integer', 'distinct', 'exists:permissions,id'], 'permissions.*.allowed' => ['required', 'boolean']]);
        abort_if($user->profiles()->wherePivot('company_id', $user->current_company_id)->whereIn('slug', ['administrador', 'gestor-administrador'])->exists(), 422, 'As permissões administrativas são definidas pelo perfil.');
        DB::transaction(function () use ($r, $user, $data) {
            foreach ($data['permissions'] as $p) {
                DB::table('user_permission_overrides')->updateOrInsert(['user_id' => $user->id, 'permission_id' => $p['id']], ['allowed' => $p['allowed'], 'granted_by' => $r->user()->id, 'reason' => 'Definido no editor de permissões.', 'created_at' => now(), 'updated_at' => now()]);
            }
            app(AuditRecorder::class)->record('user.permissions_updated', $user, $r->user(), [], $data);
        });

        return $this->permissions($r, $user);
    }

    private function mutable(Request $r, Project $project): void
    {
        abort_unless($project->company_id === $r->user()->current_company_id, 404);
        abort_if($project->isFinalized(), 409, 'Projeto finalizado não pode ser alterado.');
    }

    public function updateRate(Request $r, AnalystProjectRate $rate)
    {
        Gate::authorize('financial.manage');
        $this->mutable($r, Project::findOrFail($rate->project_id));
        $data = $r->validate(['normal_cost_rate' => ['required', 'numeric', 'min:0'], 'overtime_cost_rate' => ['required', 'numeric', 'min:0'], 'overtime_sale_rate' => ['required', 'numeric', 'min:0'], 'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $overlap = AnalystProjectRate::where('project_id', $rate->project_id)->where('user_id', $rate->user_id)->whereKeyNot($rate->id)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))->when($data['effective_until'] ?? null, fn ($q, $end) => $q->whereDate('effective_from', '<=', $end))->exists();
        abort_if($overlap, 422, 'A vigência conflita com uma tarifa existente.');
        $old = $rate->toArray();
        $rate->update($data);
        app(AuditRecorder::class)->record('project.rate_updated', $rate, $r->user(), $old, $rate->toArray());
        $this->pricePendingTasks($rate);

        return response()->json($rate);
    }

    public function deleteRate(Request $r, AnalystProjectRate $rate)
    {
        Gate::authorize('financial.manage');
        $this->mutable($r, Project::findOrFail($rate->project_id));
        app(AuditRecorder::class)->record('project.rate_deleted', $rate, $r->user(), $rate->toArray());
        $rate->delete();

        return response()->noContent();
    }

    public function pricePendingTasks(AnalystProjectRate $rate): void
    {
        Task::where('project_id', $rate->project_id)->where('user_id', $rate->user_id)->whereNull('cost_rate_snapshot')->whereNotNull('worked_on')->each(fn ($task) => app(TaskCosting::class)->capture($task));
    }

    public function updateExpense(Request $r, ProjectExpense $expense)
    {
        $this->expenseAccess($r, $expense);
        $data = $r->validate(['analyst_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->where('current_company_id', $r->user()->current_company_id)], 'expense_type_id' => ['required', 'integer', Rule::exists('expense_types', 'id')->where('company_id', $r->user()->current_company_id)], 'expense_date' => ['required', 'date'], 'description' => ['required', 'string', 'max:1000'], 'amount' => ['required', 'numeric', 'gt:0']]);
        if (isset($data['analyst_id']) && (int) $data['analyst_id'] !== (int) $expense->analyst_id) {
            Gate::authorize('financial.manage');
            abort_unless($expense->project->members()->where('user_id', $data['analyst_id'])->where('active', true)->exists(), 422, 'Analista não atribuído ao projeto.');
        }
        $old = $expense->toArray();
        $expense->update([...$data, 'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'review_reason' => null]);
        app(AuditRecorder::class)->record('expense.updated', $expense, $r->user(), $old, $expense->toArray());

        return response()->json($expense);
    }

    public function deleteExpense(Request $r, ProjectExpense $expense)
    {
        $this->expenseAccess($r, $expense);
        app(AuditRecorder::class)->record('expense.deleted', $expense, $r->user(), $expense->toArray());
        $expense->delete();

        return response()->noContent();
    }

    private function expenseAccess(Request $r, ProjectExpense $expense): void
    {
        $this->mutable($r, $expense->project);
        abort_unless($r->user()->can('financial.manage') || ($r->user()->can('expenses.create') && $expense->submitted_by === $r->user()->id && $expense->project->members()->where('user_id', $r->user()->id)->where('active', true)->exists()), 403);
    }

    public function removeMember(Request $r, Project $project, int $user)
    {
        Gate::authorize('projects.update');
        $this->mutable($r, $project);
        $member = $project->members()->where('user_id', $user)->firstOrFail();
        $old = $member->toArray();
        $member->update(['active' => false, 'access_revoked_at' => now()]);
        app(AuditRecorder::class)->record('project.member_removed', $member, $r->user(), $old, $member->toArray());

        return response()->noContent();
    }

    private function audit(Request $r, string $action, string $table, int $id, array $old, array $new): void
    {
        AuditLog::create(['company_id' => $r->user()->current_company_id, 'user_id' => $r->user()->id, 'action' => $action, 'auditable_type' => $table, 'auditable_id' => $id, 'old_values' => $old, 'new_values' => $new, 'ip_address' => $r->ip(), 'user_agent' => $r->userAgent(), 'request_id' => (string) Str::ulid(), 'created_at' => now()]);
    }
}
