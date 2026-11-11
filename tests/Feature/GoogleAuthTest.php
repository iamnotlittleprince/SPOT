<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_account_is_not_authenticated_by_oauth_alone(): void
    {
        $user = User::factory()->create(['email' => 'twofactor@example.test', 'two_factor_secret' => (new \PragmaRX\Google2FA\Google2FA())->generateSecretKey(), 'two_factor_confirmed_at' => now()]);
        $remote = (new SocialiteUser)->map(['id' => 'two-factor-provider', 'name' => 'Teste', 'email' => $user->email]);
        $remote->token = 'access-token';
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($remote);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        $this->get('/auth/google/callback')->assertRedirect('/?two_factor=required')->assertSessionHas('two_factor_challenge.user_id', $user->id);
        $this->assertGuest('web');
        $this->getJson('/api/v1/security')->assertUnauthorized();
        $this->postJson('/api/v1/auth/two-factor/challenge', ['code' => (new \PragmaRX\Google2FA\Google2FA())->getCurrentOtp($user->two_factor_secret)])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_redirect_starts_oauth_flow(): void
    {
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->once()->with(['https://www.googleapis.com/auth/calendar.events'])->andReturnSelf();
        $provider->shouldReceive('with')->once()->with(['prompt' => 'consent select_account', 'access_type' => 'offline', 'include_granted_scopes' => 'true'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_redirect_stays_in_spot_when_configuration_is_missing(): void
    {
        Socialite::shouldReceive('driver')->never();
        foreach (['client_id', 'client_secret', 'redirect'] as $missing) {
            config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
            config(['services.google.'.$missing => '   ']);
            $this->get('/auth/google')->assertRedirect('/?auth_error=google_configuration');
        }
    }

    public function test_google_login_makes_calendar_available_without_a_second_connection(): void
    {
        $user = User::factory()->create(['email' => 'calendar-login@example.test']);
        $remote = (new SocialiteUser)->map(['id' => 'google-calendar', 'email' => $user->email, 'name' => 'Pessoa']);
        $remote->token = 'calendar-access';
        $remote->refreshToken = 'calendar-refresh';
        $remote->expiresIn = 3600;
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($remote);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['https://www.googleapis.com/calendar/v3/*' => \Illuminate\Support\Facades\Http::response(['items' => []])]);

        $this->get('/auth/google/callback')->assertRedirect('/?home');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('calendar-refresh', $user->fresh()->google_refresh_token);
        $this->getJson('/api/v1/calendar?start=2026-11-01T00:00:00Z&end=2026-12-01T00:00:00Z')
            ->assertOk()->assertJsonPath('providers.google', true)->assertJsonCount(0, 'errors');
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer calendar-access'));
    }

    public function test_google_callback_updates_provider_name_and_authenticates_user(): void
    {
        User::factory()->create(['name' => 'pessoa@example.com', 'email' => 'pessoa@example.com', 'active' => true, 'account_status' => 'active']);
        $googleUser = (new SocialiteUser)->map([
            'id' => 'google-123',
            'name' => 'Pessoa Teste',
            'email' => 'pessoa@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect('/?home');

        $user = User::where('email', 'pessoa@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Pessoa Teste', $user->name);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('pessoa@example.com', $user->google_email);
    }
}
