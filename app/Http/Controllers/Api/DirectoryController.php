<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class DirectoryController extends Controller
{
    private function token(Request $r): string
    {
        Gate::authorize('security.manage');
        $s = CompanySetting::where('company_id', $r->user()->current_company_id)->first();
        abort_unless($s?->aad_tenant_id && $s?->aad_client_id && $s?->aad_client_secret, 422, 'Configure o tenant, client ID e segredo do Microsoft Entra ID.');
        $res = Http::asForm()->timeout(20)->post('https://login.microsoftonline.com/'.$s->aad_tenant_id.'/oauth2/v2.0/token', ['client_id' => $s->aad_client_id, 'client_secret' => $s->aad_client_secret, 'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials']);
        abort_unless($res->successful() && $res->json('access_token'), 502, 'Não foi possível autenticar no diretório Microsoft. Verifique as credenciais e o consentimento.');

        return $res->json('access_token');
    }

    public function index(Request $r)
    {
        $token = $this->token($r);
        $url = 'https://graph.microsoft.com/v1.0/users?$select=id,displayName,mail,userPrincipalName,accountEnabled&$top=100';
        if ($r->filled('cursor')) {
            try {
                $cursor = json_decode(Crypt::decryptString($r->string('cursor')), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                abort(422, 'Página inválida.');
            }
            abort_unless(($cursor['company'] ?? null) === $r->user()->current_company_id && str_starts_with($cursor['url'] ?? '', 'https://graph.microsoft.com/v1.0/users?'), 422);
            $url = $cursor['url'];
        }
        $res = Http::withToken($token)->timeout(20)->get($url);
        abort_unless($res->successful(), 502, 'Não foi possível listar o diretório. Verifique User.Read.All e o consentimento administrativo.');

        return response()->json(['users' => $res->json('value', []), 'next' => $res->json('@odata.nextLink') ? Crypt::encryptString(json_encode(['company' => $r->user()->current_company_id, 'url' => $res->json('@odata.nextLink')])) : null]);
    }

    public function import(Request $r, AuditRecorder $audit)
    {
        $r->validate(['id' => ['required', 'uuid']]);
        $token = $this->token($r);
        $res = Http::withToken($token)->timeout(20)->get('https://graph.microsoft.com/v1.0/users/'.$r->input('id'), ['$select' => 'id,displayName,mail,userPrincipalName,accountEnabled']);
        abort_unless($res->successful(), 502, 'Não foi possível consultar este usuário no diretório.');
        $person = $res->json();
        $email = mb_strtolower($person['mail'] ?: ($person['userPrincipalName'] ?? ''));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) && ($person['accountEnabled'] ?? false), 422, 'O usuário precisa estar ativo e possuir e-mail válido.');
        abort_if(User::where('email', $email)->orWhere('microsoft_email', $email)->orWhere('microsoft_id', $person['id'])->exists(), 422, 'Esta conta já está cadastrada. Use Usuários e acessos.');
        $user = DB::transaction(function () use ($r, $person, $email, $audit) {
            $user = User::create(['name' => $person['displayName'] ?: $email, 'email' => $email, 'microsoft_email' => $email, 'microsoft_id' => $person['id'], 'password' => null, 'current_company_id' => $r->user()->current_company_id, 'active' => true, 'account_status' => 'active']);
            $user->profiles()->attach(Profile::where('slug', 'analista')->firstOrFail(), ['company_id' => $r->user()->current_company_id]);
            $audit->record('user.directory_imported', $user, $r->user(), [], ['name' => $user->name, 'email' => $email, 'profile' => 'analista']);

            return $user;
        });

        return response()->json($user->only(['id', 'name', 'email']),201);
    }
}
