<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FirstAccessController extends Controller
{
    public function activate(Request $request, string $token, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = DB::transaction(function () use ($token, $data, $audit) {
            $activation = UserActivationToken::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $activation || ! $activation->isUsable()) {
                throw ValidationException::withMessages(['token' => 'Link inválido, expirado ou já utilizado.']);
            }
            $user = User::findOrFail($activation->user_id);
            if (mb_strtolower(trim($user->email)) !== mb_strtolower(trim($data['email']))) {
                throw ValidationException::withMessages(['email' => 'Por gentileza, insira o e-mail correspondente ao do convite enviado!']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'active' => true, 'account_status' => 'active', 'email_verified_at' => now()])->save();
            $activation->forceFill(['used_at' => now()])->save();
            $audit->record('user.activated', $user, $user);

            return $user;
        });
        // Troque a sessão somente depois de validar e concluir a ativação.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user]);
    }
}
