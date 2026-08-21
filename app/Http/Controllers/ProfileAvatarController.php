<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileAvatarController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $previousAvatar = $user->avatar_url;
        $path = $validated['avatar']->storePublicly("avatars/{$user->id}", 'public');

        $user->update(['avatar_url' => Storage::url($path)]);

        if ($previousAvatar && str_starts_with($previousAvatar, '/storage/avatars/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $previousAvatar));
        }

        return response()->json([
            'avatar_url' => $user->avatar_url,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url && str_starts_with($user->avatar_url, '/storage/avatars/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
        }

        $user->update(['avatar_url' => null]);

        return response()->json(['avatar_url' => null]);
    }
}
