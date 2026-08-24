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
            'timezone' => 'Europe/Lisbon',
        ])->assertOk()->assertJsonPath('name', 'Marco Silva')->assertJsonPath('timezone', 'Europe/Lisbon');

        $this->assertSame('Marco Silva', $user->fresh()->name);
        $this->assertSame('Europe/Lisbon', $user->fresh()->timezone);
    }

    public function test_regular_user_cannot_change_administrative_identity_fields(): void
    {
        $user = User::factory()->create(['job_title' => 'Analista', 'department' => 'Projetos']);

        $this->actingAs($user)->patchJson('/profile', [
            'first_name' => 'Usuário', 'last_name' => 'Comum', 'timezone' => 'America/Sao_Paulo',
            'job_title' => 'Administrador', 'department' => 'Diretoria',
        ])->assertForbidden();

        $this->assertSame('Analista', $user->fresh()->job_title);
        $this->assertSame('Projetos', $user->fresh()->department);
    }
}
