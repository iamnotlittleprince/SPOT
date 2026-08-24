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
        $setup = $this->actingAs($user)->postJson('/api/v1/security/two-factor/setup')->assertOk();
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
