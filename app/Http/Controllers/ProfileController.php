<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:150'],
        ]);

        $name = trim($validated['first_name'].' '.($validated['last_name'] ?? ''));
        $request->user()->update(['name' => $name]);

        return response()->json([
            'name' => $name,
            'email' => $request->user()->email,
            'avatar_url' => $request->user()->avatar_url,
        ]);
    }
}
