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

    public function microsoft(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->microsoft_id) return response()->json(['status' => 'reconnect', 'message' => 'Conecte sua conta Microsoft em Apps para buscar a foto.'], 422);
        try {
            $identity = new \Laravel\Socialite\Two\User;
            $identity->token = app(\App\Domain\Auth\MicrosoftToken::class)->get($user);
            $status = app(\App\Domain\Profile\OAuthAvatar::class)->sync($user, 'microsoft', $identity);
        } catch (\Throwable) {
            $status = 'reconnect';
        }
        $messages = [
            'updated' => 'Foto da Microsoft atualizada.',
            'no_photo' => 'A Microsoft não disponibilizou uma foto para esta conta. Você pode enviar uma imagem pelo perfil.',
            'custom_photo' => 'Sua foto enviada manualmente foi mantida. Use a imagem padrão antes de importar outra foto.',
            'reconnect' => 'Reconecte sua conta Microsoft em Apps e tente buscar a foto novamente.',
            'unavailable' => 'Não foi possível buscar a foto da Microsoft agora. Tente novamente.',
        ];
        return response()->json(['status' => $status, 'message' => $messages[$status], 'avatar_url' => $user->avatar_url], in_array($status, ['reconnect', 'unavailable']) ? 422 : 200);
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
