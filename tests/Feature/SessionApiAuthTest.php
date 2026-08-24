<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_can_authenticate_and_use_versioned_api_with_session(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-segura',
        ])->assertOk()->assertJsonPath('user.email', $user->email);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_versioned_api_rejects_an_unauthenticated_request(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_login_rotates_the_session_identifier(): void
    {
        $user = User::factory()->create(['password' => 'senha-segura']);

        $this->withSession(['marker' => true]);
        $oldId = session()->getId();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-segura',
        ])->assertOk();

        $this->assertNotSame($oldId, session()->getId());
    }

    public function test_profile_lists_only_projects_assigned_to_the_authenticated_user(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $other = User::factory()->create(['current_company_id' => $company->id]);
        $assigned = Project::create(['company_id' => $company->id, 'user_id' => $other->id, 'name' => 'Projeto atribuído', 'status' => 'in_progress', 'progress' => 65]);
        Project::create(['company_id' => $company->id, 'user_id' => $other->id, 'name' => 'Projeto de outra pessoa', 'status' => 'planning', 'progress' => 10]);
        $assigned->members()->create(['user_id' => $user->id, 'role' => 'analyst', 'relationship_type' => 'cpt_internal_analyst', 'active' => true]);

        $this->actingAs($user)->getJson('/api/v1/auth/me/projects')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Projeto atribuído')
            ->assertJsonPath('0.progress', 65);
    }

    public function test_authenticated_user_can_list_all_supported_timezones(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/auth/timezones')
            ->assertOk()
            ->assertJsonFragment(['id' => 'America/Sao_Paulo'])
            ->assertJsonFragment(['id' => 'Europe/Lisbon'])
            ->assertJsonFragment(['id' => 'Asia/Tokyo']);
    }
}
