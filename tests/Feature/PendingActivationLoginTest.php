<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingActivationLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_access_is_explained_without_authenticating_or_activating_user(): void
    {
        $user = User::factory()->create([
            'email' => 'pending@example.com', 'password' => null,
            'active' => false, 'account_status' => 'pending_activation',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'senha-qualquer',
        ])->assertUnauthorized()->assertJsonPath('activation_required', true);

        $this->assertGuest();
        $this->assertNull($user->fresh()->password);
        $this->assertSame('pending_activation', $user->fresh()->account_status);
    }

    public function test_wrong_password_for_activated_user_keeps_generic_error(): void
    {
        $user = User::factory()->create(['password' => 'senha-correta']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'senha-incorreta',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciais inválidas.']);
        $this->assertGuest();
    }
}
