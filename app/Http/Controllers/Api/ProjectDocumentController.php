<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = ProjectDocument::with('project:id,name')->where('user_id', $request->user()->id)->latest()->get();
        return response()->json(['documents' => $documents]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,zip'],
            'project_id' => ['nullable', 'integer'],
        ]);
        $project = null;
        if (!empty($validated['project_id'])) {
            $project = Project::where('company_id', $request->user()->current_company_id)->findOrFail($validated['project_id']);
        }
        $file = $validated['file'];
        $path = $file->store('documents/'.$request->user()->id, 'local');
        $document = ProjectDocument::create([
            'company_id' => $request->user()->current_company_id,
            'project_id' => $project?->id,
            'user_id' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        return response()->json($document->load('project:id,name'), 201);
    }

    public function download(Request $request, ProjectDocument $document): StreamedResponse
    {
        abort_unless($document->user_id === $request->user()->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        return Storage::disk('local')->download($document->path, $document->name);
    }

    public function destroy(Request $request, ProjectDocument $document): JsonResponse
    {
        abort_unless($document->user_id === $request->user()->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();
        return response()->json(null, 204);
    }
}
