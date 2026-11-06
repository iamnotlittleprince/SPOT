<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_has_a_separate_six_hundred_request_budget(): void
    {
        Cache::store()->flush();
        $user = User::factory()->create(['password' => 'Senha-Segura1']);
        $this->actingAs($user);
        for ($i = 0; $i < 121; $i++) {
            $this->getJson('/api/v1/security')->assertOk()->assertHeader('X-RateLimit-Limit', '600');
        }
        // Ordinary navigation must not consume the five attempts for security actions.
        $this->postJson('/api/v1/security/two-factor/setup', ['password' => 'Senha-Segura1'])->assertOk();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/security/two-factor/setup', ['password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/security/two-factor/setup', ['password' => 'incorrect'])
            ->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_general_budget_is_enforced_and_separate_for_each_user(): void
    {
        Cache::store()->flush();
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 600; $i++) {
            $this->getJson('/api/v1/auth/me')->assertOk();
        }
        $this->getJson('/api/v1/auth/me')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/auth/me')->assertOk();
    }
}
