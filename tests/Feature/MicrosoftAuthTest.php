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

    private function mockMicrosoft(string $email = 'conta@hotmail.com', string $id = 'microsoft-link'): void
    {
        $remote = (new SocialiteUser)->map(['id' => $id, 'name' => 'Nome Microsoft', 'email' => $email]);
        $remote->token = 'access-token';
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($remote);
        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);
    }

    public function test_legacy_integration_url_also_records_the_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'conta@gmail.com']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://login.microsoftonline.com/'));
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialiteUser)->map(['id' => 'legacy-microsoft', 'name' => 'Microsoft', 'email' => 'conta@hotmail.com'])
        );
        Socialite::shouldReceive('driver')->twice()->with('microsoft')->andReturn($provider);

        $this->actingAs($user)->get('/auth/microsoft')
            ->assertRedirect('https://login.microsoftonline.com/')
            ->assertSessionHas('microsoft_link_user_id', $user->id);
        $this->get('/auth/microsoft/callback')->assertRedirect('/?home');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('conta@hotmail.com', $user->fresh()->microsoft_email);
    }

    public function test_integration_redirect_requires_login_and_records_current_user(): void
    {
        $this->get('/integrations/microsoft/connect')->assertRedirect('/');
        $user = User::factory()->create();
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->once()->with(['offline_access', 'Calendars.ReadWrite'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://login.microsoftonline.com/'));
        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);
        $this->actingAs($user)->get('/integrations/microsoft/connect')
            ->assertRedirect('https://login.microsoftonline.com/')
            ->assertSessionHas('microsoft_link_user_id', $user->id);
    }

    public function test_integration_links_a_different_email_without_changing_the_spot_identity(): void
    {
        $user = User::factory()->create(['name' => 'Nome Spot', 'email' => 'conta@gmail.com', 'email_verified_at' => null]);
        $this->mockMicrosoft();
        $this->actingAs($user)->withSession(['microsoft_link_user_id' => $user->id])
            ->get('/auth/microsoft/callback')->assertRedirect('/?home')
            ->assertSessionMissing('microsoft_link_user_id');
        $user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('conta@gmail.com', $user->email);
        $this->assertSame('Nome Spot', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('conta@hotmail.com', $user->microsoft_email);
        $this->assertSame('microsoft-link', $user->microsoft_id);
        $this->assertSame('access-token', $user->microsoft_access_token);
    }

    public function test_integration_cannot_take_a_provider_owned_by_another_user(): void
    {
        $owner = User::factory()->create(['microsoft_id' => 'microsoft-link', 'microsoft_email' => 'conta@hotmail.com']);
        $user = User::factory()->create();
        $this->mockMicrosoft();
        $this->actingAs($user)->withSession(['microsoft_link_user_id' => $user->id])
            ->get('/auth/microsoft/callback')->assertRedirect('/?auth_error=microsoft_already_linked');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->microsoft_id);
        $this->assertSame('microsoft-link', $owner->fresh()->microsoft_id);
    }

    public function test_integration_rejects_a_changed_or_missing_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Socialite::shouldReceive('driver')->never();
        $this->withSession(['microsoft_link_user_id' => $user->id])
            ->get('/auth/microsoft/callback')->assertRedirect('/?auth_error=account_not_provisioned');
        $this->actingAs($other)->withSession(['microsoft_link_user_id' => $user->id])
            ->get('/auth/microsoft/callback')->assertRedirect('/?auth_error=account_not_provisioned');
    }

    public function test_guest_login_does_not_link_an_unregistered_email(): void
    {
        $this->mockMicrosoft();
        $this->get('/auth/microsoft/callback')->assertRedirect('/?auth_error=account_not_provisioned');
        $this->assertGuest();
    }

    public function test_integration_cannot_replace_an_existing_microsoft_identity(): void
    {
        $user = User::factory()->create(['microsoft_id' => 'existing-id']);
        $this->mockMicrosoft();
        $this->actingAs($user)->withSession(['microsoft_link_user_id' => $user->id])
            ->get('/auth/microsoft/callback')->assertRedirect('/?auth_error=microsoft_already_linked');
        $this->assertSame('existing-id', $user->fresh()->microsoft_id);
    }

    public function test_integration_rejects_a_suspended_user(): void
    {
        $user = User::factory()->create(['active' => false, 'account_status' => 'suspended']);
        $this->mockMicrosoft();
        $this->actingAs($user)->withSession(['microsoft_link_user_id' => $user->id])
            ->get('/auth/microsoft/callback')->assertRedirect('/?auth_error=account_not_provisioned');
        $this->assertNull($user->fresh()->microsoft_id);
    }

    public function test_linked_microsoft_account_can_subsequently_log_in(): void
    {
        $user = User::factory()->create(['email' => 'conta@gmail.com', 'microsoft_email' => 'conta@hotmail.com', 'microsoft_id' => 'microsoft-link']);
        $this->mockMicrosoft('CONTA@hotmail.com');
        $this->get('/auth/microsoft/callback')->assertRedirect('/?home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_microsoft_redirect_starts_oauth_flow(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')->once()->with(['offline_access', 'Calendars.ReadWrite'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize'));
        Socialite::shouldReceive('driver')->once()->with('microsoft')->andReturn($provider);

        $this->get('/auth/microsoft')
            ->assertRedirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
    }

    public function test_microsoft_callback_updates_provider_name_and_authenticates_user(): void
    {
        User::factory()->create(['name' => 'pessoa@example.com', 'email' => 'pessoa@example.com', 'active' => true, 'account_status' => 'active']);
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
        $this->assertSame('Pessoa Microsoft', $user->name);
        $this->assertSame('microsoft-123', $user->microsoft_id);
        $this->assertSame('pessoa@example.com', $user->microsoft_email);
    }
}
