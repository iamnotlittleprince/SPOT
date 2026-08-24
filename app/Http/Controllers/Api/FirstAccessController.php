<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\UserActivationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirstAccessController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string'], 'email' => ['required', 'email']]);
        $user = \App\Models\User::where('email', mb_strtolower($data['email']))->where('account_status', 'pending_activation')->first();
        if ($user && mb_strtolower(trim($user->name)) === mb_strtolower(trim($data['name']))) {
            $previous = UserActivationToken::where('user_id', $user->id)->latest()->first();
            if ($previous) {
                UserActivationToken::where('user_id', $user->id)->whereNull('used_at')->update(['revoked_at' => now()]);
                $token = Str::random(64);
                UserActivationToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'created_by' => $previous->created_by, 'expires_at' => now()->addHours(2)]);
                $url = url('/first-access/'.$token);
                Mail::raw("Seu link de primeiro acesso ao Spot é: {$url}\n\nO link expira em 2 horas.", fn ($message) => $message->to($user->email)->subject('Primeiro acesso ao Spot'));
            }
        }
        return response()->json(['message' => 'Se os dados corresponderem a uma conta pendente, enviaremos um link de ativação.']);
    }

    public function activate(Request $request, string $token, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = DB::transaction(function () use ($token, $data, $audit) {
            $activation = UserActivationToken::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $activation || ! $activation->isUsable()) throw ValidationException::withMessages(['token' => 'Link inválido, expirado ou já utilizado.']);
            $user = \App\Models\User::findOrFail($activation->user_id);
            if (mb_strtolower(trim($user->name)) !== mb_strtolower(trim($data['name'])) || mb_strtolower($user->email) !== mb_strtolower($data['email'])) {
                throw ValidationException::withMessages(['identity' => 'Nome, sobrenome ou e-mail não correspondem ao cadastro administrativo.']);
            }
            $user->forceFill(['password' => Hash::make($data['password']), 'active' => true, 'account_status' => 'active', 'email_verified_at' => now()])->save();
            $activation->forceFill(['used_at' => now()])->save();
            $audit->record('user.activated', $user, $user);
            return $user;
        });
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        return response()->json(['user' => $user]);
    }
}
