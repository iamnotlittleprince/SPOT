<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MicrosoftAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_microsoft_redirect_starts_oauth_flow(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->once()->with(['offline_access', 'Calendars.ReadWrite'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));
        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);

        $this->get('/auth/microsoft')
            ->assertRedirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
    }

    public function test_microsoft_callback_creates_and_authenticates_user(): void
    {
        User::factory()->create(['name' => 'Pessoa Microsoft', 'email' => 'pessoa@example.com', 'active' => true, 'account_status' => 'active']);
        $microsoftUser = (new SocialiteUser)->map([
            'id' => 'microsoft-123',
            'name' => 'Pessoa Microsoft',
            'email' => 'pessoa@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($microsoftUser);
        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);

        $this->get('/auth/microsoft/callback')
            ->assertRedirect('/?home');

        $user = User::where('email', 'pessoa@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('microsoft-123', $user->microsoft_id);
        $this->assertSame('pessoa@example.com', $user->microsoft_email);
    }
}
