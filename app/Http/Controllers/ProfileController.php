<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'timezone' => ['required', 'timezone:all'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
        ]);

        $name = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));
        $updates = ['name' => $name, 'timezone' => $validated['timezone']];
        if ($request->hasAny(['job_title', 'department'])) {
            Gate::authorize('security.manage');
            $updates['job_title'] = $validated['job_title'] ?? null;
            $updates['department'] = $validated['department'] ?? null;
        }
        $request->user()->update($updates);

        return response()->json([
            'name' => $name,
            'email' => $request->user()->email,
            'avatar_url' => $request->user()->avatar_url,
            'timezone' => $validated['timezone'],
            'job_title' => $request->user()->job_title,
            'department' => $request->user()->department,
            'can_manage_identity' => $request->user()->can('security.manage'),
        ]);
    }
}
