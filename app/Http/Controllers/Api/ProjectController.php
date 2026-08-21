<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Auth::guard('api')->user()->projects()->withCount('tasks')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $project = Auth::guard('api')->user()->projects()->create($this->validated($request));
        return response()->json($project->loadCount('tasks'), 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        return response()->json($project->load(['tasks.user:id,name'])->loadCount('tasks'));
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $project->update($this->validated($request));
        return response()->json($project->loadCount('tasks'));
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $project->delete();
        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'client_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(['planning', 'in_progress', 'review', 'completed', 'paused'])],
            'progress' => ['required', 'integer', 'between:0,100'],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_unless($project->user_id === Auth::guard('api')->id(), 403);
    }
}
