<?php
namespace App\Http\Controllers\Api;

use App\Domain\Access\{ProjectReportAccess, TeamsAccessWindow};
use App\Http\Controllers\Controller;
use App\Models\{InboxItem, Project, ProjectAccessRequest, User};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class ProjectAccessController extends Controller
{
    private function eligibleRequester(User $user): bool
    {
        return app(ProjectReportAccess::class)->canRequest($user);
    }

    public function index(Request $request, ProjectReportAccess $access): JsonResponse
    {
        $user = $request->user();
        $managers = User::where('current_company_id', $user->current_company_id)->where('active', true)->where('account_status', 'active')->get();
        // Analistas só podem descobrir e solicitar projetos com participação ativa.
        $projects = $this->eligibleRequester($user) ? Project::where('company_id', $user->current_company_id)->whereNull('finalized_at')
            ->when($access->isAnalyst($user), fn ($q) => $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('role', 'analyst')->where('active', true)->whereNull('access_revoked_at')))
            ->orderBy('name')->get() : collect();
        $options = $projects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'reviewers' => $managers->filter(fn ($m) => $access->mayGrant($m, $user, $p))->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->values()])->values();
        $requests = ProjectAccessRequest::where('company_id', $user->current_company_id)->where(fn ($q) => $q->where('requester_id', $user->id)->orWhere('reviewer_id', $user->id))
            ->with(['project', 'requester', 'reviewer'])->latest()->limit(100)->get();
        return response()->json(['can_request' => $this->eligibleRequester($user), 'projects' => $options, 'requests' => $requests->map(fn ($r) => [
            ...$r->only(['id', 'project_id', 'requester_id', 'reviewer_id', 'message', 'status', 'access_mode', 'duration', 'starts_at', 'expires_at', 'created_at']),
            'project' => $r->project?->only(['id', 'name']), 'requester' => $r->requester?->only(['id', 'name']), 'reviewer' => $r->reviewer?->only(['id', 'name']),
            'can_decide' => $r->reviewer_id === $user->id && $r->project && $r->requester && $this->eligibleRequester($r->requester) && $access->mayGrant($user, $r->requester, $r->project),
            'can_revoke' => $r->reviewer_id === $user->id && $r->status === 'approved',
            ...$access->state($r),
        ])]);
    }

    public function store(Request $request, ProjectReportAccess $access): JsonResponse
    {
        abort_unless($this->eligibleRequester($request->user()), 403);
        $data = $request->validate(['project_id' => ['required', 'integer'], 'reviewer_id' => ['required', 'integer'], 'message' => ['required', 'string', 'max:2000']]);
        $project = Project::where('company_id', $request->user()->current_company_id)->findOrFail($data['project_id']);
        abort_unless($project->members()->where('user_id', $request->user()->id)->where('role', 'analyst')->where('active', true)->whereNull('access_revoked_at')->exists(), 403);
        $reviewer = User::findOrFail($data['reviewer_id']);
        abort_unless($access->mayGrant($reviewer, $request->user(), $project), 403);
        return DB::transaction(function () use ($request, $project, $reviewer, $data) {
            // Serializa pedidos repetidos, inclusive de abas distintas.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (ProjectAccessRequest::where('requester_id', $request->user()->id)->where('project_id', $project->id)->where('reviewer_id', $reviewer->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['project_id' => 'Já existe uma solicitação pendente para este projeto e destinatário.']);
            }
            $entry = ProjectAccessRequest::create($data + ['requester_id' => $request->user()->id, 'company_id' => $project->company_id]);
            app(\App\Domain\Audit\AuditRecorder::class)->record('project.access_requested', $entry, $request->user(), [], $entry->only(['project_id', 'reviewer_id', 'requester_id', 'status']));
            $this->notify($reviewer->id, $entry, 'Acesso solicitado', $data['message'], $request->user()->name);
            $this->notify($request->user()->id, $entry, 'Solicitação de acesso enviada', $data['message'], $request->user()->name);
            return response()->json(['request' => $entry], 201);
        });
    }

    public function meetings(Request $request): JsonResponse
    {
        abort_unless($request->user()->profiles()->wherePivot('company_id', $request->user()->current_company_id)->whereIn('slug', ['gestor', 'gestor-administrador', 'administrador'])->exists()
            || Project::where('company_id', $request->user()->current_company_id)->whereNull('finalized_at')->whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id)->where('role', 'manager')->where('active', true)->whereNull('access_revoked_at'))->exists(), 403);
        try { return response()->json(['meetings' => app(TeamsAccessWindow::class)->upcoming($request->user())]); }
        catch (\Throwable) { return response()->json(['message' => 'Não foi possível consultar as reuniões. Confira a conexão Microsoft em Apps.'], 422); }
    }

    public function decide(Request $request, ProjectAccessRequest $accessRequest, ProjectReportAccess $access): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'access_mode' => ['required_if:decision,approve', Rule::in(['view', 'visual', 'data'])], 'duration' => ['required_if:decision,approve', Rule::in(['meeting', '24h', '48h', '72h', 'week', 'month', 'project'])], 'teams_event_id' => ['required_if:duration,meeting', 'nullable', 'string', 'max:2048']]);
        return DB::transaction(function () use ($request, $accessRequest, $access, $data) {
            $entry = ProjectAccessRequest::lockForUpdate()->findOrFail($accessRequest->id);
            abort_unless($entry->company_id === $request->user()->current_company_id && $entry->reviewer_id === $request->user()->id && $entry->project && $entry->requester && $this->eligibleRequester($entry->requester) && $access->mayGrant($request->user(), $entry->requester, $entry->project), 403);
            abort_unless($entry->status === 'pending', 409, 'Esta solicitação já foi respondida.');
            $values = ['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_at' => now()];
            if ($data['decision'] === 'approve') {
                $duration = $data['duration'];
                $end = match ($duration) { '24h' => now()->addHours(24), '48h' => now()->addHours(48), '72h' => now()->addHours(72), 'week' => now()->addWeek(), 'month' => now()->addMonthNoOverflow(), default => null };
                $start = now();
                if ($duration === 'meeting') {
                    try { $meeting = app(TeamsAccessWindow::class)->event($request->user(), $data['teams_event_id']); }
                    catch (ValidationException $e) { throw $e; }
                    catch (\Throwable) { throw ValidationException::withMessages(['teams_event_id' => 'Não foi possível confirmar a reunião no Teams. Confira a conexão Microsoft.']); }
                    $start = $meeting['start']; $end = $meeting['end'];
                    if ($end->lte(now()) || $end->lte($start)) throw ValidationException::withMessages(['teams_event_id' => 'Escolha uma reunião que ainda não terminou.']);
                }
                $values += ['access_mode' => $data['access_mode'], 'duration' => $duration, 'starts_at' => $start, 'expires_at' => $end, 'teams_event_id' => $duration === 'meeting' ? $data['teams_event_id'] : null];
            }
            $entry->update($values);
            app(\App\Domain\Audit\AuditRecorder::class)->record('project.access_decided', $entry, $request->user(), ['status' => 'pending'], $entry->only(['status', 'access_mode', 'duration', 'starts_at', 'expires_at']));
            $this->notify($entry->requester_id, $entry, $entry->status === 'approved' ? 'Acesso concedido' : 'Acesso recusado', $entry->message, $request->user()->name);
            return response()->json(['request' => $entry]);
        });
    }

    public function revoke(Request $request, ProjectAccessRequest $accessRequest, ProjectReportAccess $access): JsonResponse
    {
        return DB::transaction(function () use ($request, $accessRequest, $access) {
            $entry = ProjectAccessRequest::lockForUpdate()->findOrFail($accessRequest->id);
            abort_unless($entry->company_id === $request->user()->current_company_id && $entry->reviewer_id === $request->user()->id, 403);
            abort_unless($entry->status === 'approved', 409);
            $entry->update(['status' => 'revoked', 'revoked_at' => now()]);
            app(\App\Domain\Audit\AuditRecorder::class)->record('project.access_revoked', $entry, $request->user(), ['status' => 'approved'], ['status' => 'revoked']);
            $this->notify($entry->requester_id, $entry, 'Acesso revogado', $entry->message, $request->user()->name);
            return response()->json(['request' => $entry]);
        });
    }

    public function updateData(Request $request, Project $project, ProjectReportAccess $access): JsonResponse
    {
        return DB::transaction(function () use ($request, $project, $access) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            abort_unless(in_array($project->id, $access->allowedIds($request->user(), 'data'), true), 403);
            abort_if($project->isFinalized(), 409);
            $data = $request->validate(['progress' => ['required', 'integer', 'between:0,100'], 'contract_value' => ['required', 'numeric', 'min:0', 'max:999999999.99'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']]);
            $before = $project->only(array_keys($data));
            $project->update($data);
            app(\App\Domain\Audit\AuditRecorder::class)->record('project.report_data_updated', $project, $request->user(), $before, $project->only(array_keys($data)));
            return response()->json(['message' => 'Dados do projeto atualizados.']);
        });
    }

    private function notify(int $userId, ProjectAccessRequest $entry, string $title, string $body, string $actor): void
    {
        InboxItem::create(['user_id' => $userId, 'project_id' => $entry->project_id, 'type' => 'update', 'title' => $title, 'body' => $body, 'actor_name' => $actor, 'data' => ['access_request_id' => $entry->id, 'status' => $entry->status, 'access_mode' => $entry->access_mode, 'duration' => $entry->duration, 'starts_at' => $entry->starts_at?->toIso8601String(), 'expires_at' => $entry->expires_at?->toIso8601String()]]);
    }
}
