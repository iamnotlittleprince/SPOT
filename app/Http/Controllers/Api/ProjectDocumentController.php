<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = ProjectDocument::with(['project:id,name', 'task:id,title'])->where('user_id', $request->user()->id)->where('company_id', $request->user()->current_company_id)->latest()->get();
        return response()->json([
            'documents' => $documents,
            'projects' => $this->projects($request->user())->orderBy('name')->get(['id', 'name']),
            'tasks' => $this->tasks($request->user())->orderBy('title')->get(['id', 'project_id', 'title']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,zip'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'task_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $assignment = $this->assignment($request->user(), $validated);
        $file = $validated['file'];
        $path = $file->store('documents/'.$request->user()->id, 'local');
        try {
            $document = DB::transaction(function () use ($request, $assignment, $file, $path) {
                $document = ProjectDocument::create([
                    'company_id' => $request->user()->current_company_id,
                    ...$assignment,
                    'user_id' => $request->user()->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                app(AuditRecorder::class)->record('document.created', $document, $request->user(), [], $document->only(['project_id', 'task_id', 'name', 'mime_type', 'size']));
                return $document;
            });
        } catch (\Throwable $failure) {
            Storage::disk('local')->delete($path);
            throw $failure;
        }
        return response()->json($document->load(['project:id,name', 'task:id,title']), 201);
    }

    public function update(Request $request, ProjectDocument $document): JsonResponse
    {
        $this->ensureOwner($request, $document);
        $data = $request->validate([
            'project_id' => ['present', 'nullable', 'integer', 'min:1'],
            'task_id' => ['present', 'nullable', 'integer', 'min:1'],
        ]);
        $assignment = $this->assignment($request->user(), $data);
        DB::transaction(function () use ($request, $document, $assignment) {
            $document = ProjectDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwner($request, $document);
            $old = $document->only(['project_id', 'task_id']);
            $document->fill($assignment);
            if ($document->isDirty()) {
                $document->save();
                app(AuditRecorder::class)->record('document.updated', $document, $request->user(), $old, $assignment);
            }
        });
        return response()->json($document->refresh()->load(['project:id,name', 'task:id,title']));
    }

    private function ensureOwner(Request $request, ProjectDocument $document): void
    {
        abort_unless($document->user_id === $request->user()->id && $document->company_id === $request->user()->current_company_id, 404);
    }

    private function projects(User $user): Builder
    {
        return Project::where('company_id', $user->current_company_id)
            ->when(! $user->can('projects.view_all'), function ($query) use ($user) {
                if (! $user->can('projects.view_assigned')) return $query->whereRaw('1 = 0');
                return $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id)
                    ->where(fn ($access) => $access->where('active', true)->orWhereNotNull('projects.finalized_at')));
            });
    }

    private function tasks(User $user): Builder
    {
        return Task::whereIn('project_id', $this->projects($user)->select('id'))
            ->when(! $user->can('tasks.view_others'), fn ($query) => $query->where(fn ($own) => $own->where('user_id', $user->id)
                ->orWhereHas('participants', fn ($people) => $people->where('users.id', $user->id))));
    }

    private function assignment(User $user, array $data): array
    {
        $task = empty($data['task_id']) ? null : $this->tasks($user)->find($data['task_id']);
        if (! empty($data['task_id']) && ! $task) {
            throw ValidationException::withMessages(['task_id' => 'Escolha uma tarefa à qual você tenha acesso.']);
        }
        if ($task && ! empty($data['project_id']) && (int) $data['project_id'] !== (int) $task->project_id) {
            throw ValidationException::withMessages(['task_id' => 'A tarefa deve pertencer ao projeto selecionado.']);
        }
        $projectId = $task?->project_id ?? ($data['project_id'] ?? null);
        if ($projectId && ! $this->projects($user)->whereKey($projectId)->exists()) {
            throw ValidationException::withMessages(['project_id' => 'Escolha um projeto ao qual você tenha acesso.']);
        }
        return ['project_id' => $projectId ?: null, 'task_id' => $task?->id];
    }

    public function download(Request $request, ProjectDocument $document): StreamedResponse
    {
        $this->ensureOwner($request, $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        app(AuditRecorder::class)->record('document.downloaded', $document, $request->user(), [], $document->only(['project_id', 'task_id', 'name']));
        return Storage::disk('local')->download($document->path, $document->name);
    }

    public function destroy(Request $request, ProjectDocument $document): JsonResponse
    {
        $this->ensureOwner($request, $document);
        DB::transaction(function () use ($request, $document) {
            app(AuditRecorder::class)->record('document.deleted', $document, $request->user(), $document->only(['project_id', 'task_id', 'name', 'mime_type', 'size']));
            $document->delete();
        });
        Storage::disk('local')->delete($document->path);
        return response()->json(null, 204);
    }
}
