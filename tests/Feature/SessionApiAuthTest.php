<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_tab_cannot_use_or_log_out_the_account_that_replaced_its_session(): void
    {
        $previous = User::factory()->create();
        $current = User::factory()->create();
        $this->actingAs($current)->withHeader('X-Spot-User', (string) $previous->id)
            ->getJson('/api/v1/auth/me')->assertConflict()->assertJsonPath('code', 'session_changed');
        $this->postJson('/api/v1/auth/logout')->assertConflict()->assertJsonPath('code', 'session_changed');
        $this->assertAuthenticatedAs($current);
        $this->withHeader('X-Spot-User', (string) $current->id)
            ->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('id', $current->id);
    }

    public function test_browser_password_change_still_invalidates_the_previous_session(): void
    {
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'senha-antiga']);
        $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/']);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senha-antiga'])->assertOk();
        $this->useBrowserSession($login);
        $current = $this->getJson('/api/v1/auth/me')->assertOk();
        $this->useBrowserSession($current);
        $user->update(['password' => 'senha-nova']);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_first_access_replaces_an_existing_browser_session_and_survives_the_redirect(): void
    {
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'database']);
        $admin = User::factory()->create(['password' => 'senha-segura']);
        $pending = User::factory()->create(['password' => null, 'active' => false, 'account_status' => 'pending_activation']);
        \App\Models\UserActivationToken::create([
            'user_id' => $pending->id, 'created_by' => $admin->id,
            'token_hash' => hash('sha256', 'browser-activation'), 'expires_at' => now()->addHour(),
        ]);
        $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/']);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'senha-segura'])->assertOk();
        $this->useBrowserSession($login);
        $activation = $this->postJson('/api/v1/first-access/browser-activation', [
            'name' => $pending->name, 'email' => $pending->email,
            'password' => 'nova-senha-segura', 'password_confirmation' => 'nova-senha-segura',
        ])->assertOk();
        $this->useBrowserSession($activation);
        $this->get('/?home')->assertOk()->assertSee($pending->email);
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('id', $pending->id);
        $this->postJson('/api/v1/auth/logout')->assertOk();
    }

    private function useBrowserSession($response): void
    {
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        \Illuminate\Support\Facades\Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $this->withCredentials()->withUnencryptedCookies([$cookie->getName() => $cookie->getValue()]);
    }

    public function test_browser_login_emits_a_session_cookie_readable_by_web_routes(): void
    {
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'senha-segura']);
        $response = $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/'])
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senha-segura'])
            ->assertOk();

        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $sessionId = \Illuminate\Cookie\CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
        $this->assertSame(session()->getId(), $sessionId, 'API cookies must be encrypted once, like web-route cookies.');

        \Illuminate\Support\Facades\Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $this->withCredentials()->withUnencryptedCookies([$cookie->getName() => $cookie->getValue()])
            ->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_spa_can_authenticate_and_use_versioned_api_with_session(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-segura',
        ])->assertOk()->assertJsonPath('user.email', $user->email);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_versioned_api_rejects_an_unauthenticated_request(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_login_rotates_the_session_identifier(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura']);

        $this->withSession(['marker' => true]);
        $oldId = session()->getId();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-segura',
        ])->assertOk();

        $this->assertNotSame($oldId, session()->getId());
    }

    public function test_profile_lists_only_projects_assigned_to_the_authenticated_user(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $other = User::factory()->create(['current_company_id' => $company->id]);
        $assigned = Project::create(['company_id' => $company->id, 'user_id' => $other->id, 'name' => 'Projeto atribuído', 'status' => 'in_progress', 'progress' => 65]);
        Project::create(['company_id' => $company->id, 'user_id' => $other->id, 'name' => 'Projeto de outra pessoa', 'status' => 'planning', 'progress' => 10]);
        $assigned->members()->create(['user_id' => $user->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);

        $this->actingAs($user)->getJson('/api/v1/auth/me/projects')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Projeto atribuído')
            ->assertJsonPath('0.progress', 65);
    }

    public function test_authenticated_user_can_list_all_supported_timezones(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/auth/timezones')
            ->assertOk()
            ->assertJsonFragment(['id' => 'America/Sao_Paulo'])
            ->assertJsonFragment(['id' => 'Europe/Lisbon'])
            ->assertJsonFragment(['id' => 'Asia/Tokyo']);
    }
}
