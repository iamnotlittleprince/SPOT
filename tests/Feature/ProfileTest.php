<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Grupo TCC']);

        $this->actingAs($user)->patchJson('/profile', [
            'first_name' => 'Marco',
            'last_name' => 'Silva',
        ])->assertOk()->assertJsonPath('name', 'Marco Silva');

        $this->assertSame('Marco Silva', $user->fresh()->name);
    }
}
