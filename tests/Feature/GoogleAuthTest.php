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

    public function test_google_redirect_starts_oauth_flow(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->once()->with(['https://www.googleapis.com/auth/calendar.events'])->andReturnSelf();
        $provider->shouldReceive('with')->once()->with(['prompt' => 'consent select_account', 'access_type' => 'offline'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_google_callback_creates_and_authenticates_user(): void
    {
        User::factory()->create(['name' => 'Pessoa Teste', 'email' => 'pessoa@example.com', 'active' => true, 'account_status' => 'active']);
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
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('pessoa@example.com', $user->google_email);
    }
}
