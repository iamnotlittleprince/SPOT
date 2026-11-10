<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'locale' => ['nullable', \Illuminate\Validation\Rule::in(['pt', 'en', 'es'])]]);

        Password::sendResetLink($request->only('email'), function ($user, $token) use ($data) {
            $user->notify((new \Illuminate\Auth\Notifications\ResetPassword($token))->locale($data['locale'] ?? $user->preferredLocale()));
            event(new \Illuminate\Auth\Events\PasswordResetLinkSent($user));
        });

        return response()->json([
            'message' => 'Se existir uma conta com esse e-mail, enviaremos um link de recuperação.',
        ]);
    }
}
