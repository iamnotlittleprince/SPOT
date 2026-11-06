<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password_and_update_security_preferences(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Antiga1']);

        $this->actingAs($user)->putJson('/api/v1/security/password', [
            'current_password' => 'Senha-Antiga1',
            'password' => 'Senha-Nova123',
            'password_confirmation' => 'Senha-Nova123',
        ])->assertOk();
        $this->assertTrue(Hash::check('Senha-Nova123', $user->fresh()->password));

        $this->actingAs($user)->putJson('/api/v1/security/preferences', [
            'notify_new_login' => false,
            'notify_password_change' => true,
            'notify_provider_link' => false,
        ])->assertOk()->assertJsonPath('preferences.notify_new_login', false);
    }

    public function test_confirmed_two_factor_is_required_during_password_login(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['password' => 'Senha-Segura1']);
        $setup = $this->actingAs($user)->postJson('/api/v1/security/two-factor/setup', ['password' => 'Senha-Segura1'])->assertOk();
        $secret = $setup->json('secret');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $setup->json('qr_code'));

        $code = (new Google2FA())->getCurrentOtp($secret);
        $this->postJson('/api/v1/security/two-factor/confirm', ['code' => $code])
            ->assertOk()->assertJsonCount(8, 'recovery_codes');

        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Senha-Segura1'])
            ->assertUnprocessable()->assertJsonPath('two_factor_required', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'Senha-Segura1',
            'two_factor_code' => (new Google2FA())->getCurrentOtp($secret),
        ])->assertOk();
    }

    public function test_setup_requires_password_and_cannot_replace_active_two_factor(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['password' => 'Senha-Segura1']);
        $this->actingAs($user)->postJson('/api/v1/security/two-factor/setup')->assertUnprocessable();
        $this->postJson('/api/v1/security/two-factor/setup', ['password' => 'wrong'])->assertUnprocessable();
        $setup = $this->postJson('/api/v1/security/two-factor/setup', ['password' => 'Senha-Segura1'])->assertOk();
        $secret = $setup->json('secret');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->postJson('/api/v1/security/two-factor/confirm', ['code' => 'invalid'])->assertUnprocessable();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->postJson('/api/v1/security/two-factor/confirm', ['code' => (new Google2FA())->getCurrentOtp($secret)])->assertOk();
        $codes = $user->fresh()->two_factor_recovery_codes;
        $this->postJson('/api/v1/security/two-factor/setup', ['password' => 'Senha-Segura1'])->assertConflict();
        $this->postJson('/api/v1/security/two-factor/confirm', ['code' => (new Google2FA())->getCurrentOtp($secret)])->assertConflict();
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
        $this->assertSame($codes, $user->fresh()->two_factor_recovery_codes);
        $this->deleteJson('/api/v1/security/two-factor', ['password' => 'wrong'])->assertUnprocessable();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->deleteJson('/api/v1/security/two-factor', ['password' => 'Senha-Segura1'])->assertOk();
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_recovery_codes);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_recovery_code_is_single_use_and_missing_factor_does_not_authenticate(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['password' => 'Senha-Segura1']);
        $setup = $this->actingAs($user)->postJson('/api/v1/security/two-factor/setup', ['password' => 'Senha-Segura1']);
        $confirmation = $this->postJson('/api/v1/security/two-factor/confirm', ['code' => (new Google2FA())->getCurrentOtp($setup->json('secret'))])->assertOk();
        $code = $confirmation->json('recovery_codes.0');
        $this->assertNotContains($code, $user->fresh()->two_factor_recovery_codes);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $credentials = ['email' => $user->email, 'password' => 'Senha-Segura1'];
        $this->postJson('/api/v1/auth/login', $credentials)->assertUnprocessable();
        $this->assertGuest('web');
        $this->getJson('/api/v1/security')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', [...$credentials, 'two_factor_code' => strtolower($code)])->assertOk();
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->postJson('/api/v1/auth/login', [...$credentials, 'two_factor_code' => $code])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_legacy_token_login_requires_the_second_factor(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Segura1', 'two_factor_secret' => (new Google2FA())->generateSecretKey(), 'two_factor_confirmed_at' => now()]);
        $credentials = ['email' => $user->email, 'password' => 'Senha-Segura1'];
        $this->postJson('/api/auth/login', $credentials)->assertUnprocessable()->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('access_token');
        $this->postJson('/api/auth/login', [...$credentials, 'two_factor_code' => (new Google2FA())->getCurrentOtp($user->two_factor_secret)])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_oauth_challenge_requires_pending_session_and_expires(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['two_factor_secret' => (new Google2FA())->generateSecretKey(), 'two_factor_confirmed_at' => now()]);
        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);
        $this->postJson('/api/v1/auth/two-factor/challenge', ['code' => $code])->assertStatus(419);
        $this->withSession(['two_factor_challenge' => ['user_id' => $user->id, 'provider' => 'google', 'expires' => time() - 1]])
            ->postJson('/api/v1/auth/two-factor/challenge', ['code' => $code])->assertStatus(419)->assertSessionMissing('two_factor_challenge');
        $this->assertGuest('web');
        $this->withSession(['two_factor_challenge' => ['user_id' => $user->id, 'provider' => 'google', 'expires' => time() + 300]])
            ->postJson('/api/v1/auth/two-factor/challenge', ['code' => 'invalid'])->assertUnprocessable();
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/two-factor/challenge', ['code' => $code])->assertOk()->assertSessionMissing('two_factor_challenge');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_disconnect_an_external_provider(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-123',
            'google_email' => 'person@gmail.com',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/security/providers/google')
            ->assertOk()
            ->assertJsonPath('message', 'Integração desconectada com sucesso.');

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertNull($user->google_email);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'security.provider_disconnected.google',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/security/providers/invalid')
            ->assertUnprocessable();
    }
}
