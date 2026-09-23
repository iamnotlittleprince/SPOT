<?php

namespace Tests\Feature;

use App\Jobs\SendAccessEmail;
use Illuminate\Support\Facades\Queue;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Mail\ProjectInvitationMail;
use Tests\TestCase;

class ProjectInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_token_is_hashed_single_use_and_grants_scoped_guest_access(): void
    {
        $this->seed();
        Mail::fake();
        Queue::fake();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto compartilhado', 'status' => 'in_progress', 'progress' => 10]);

        $response = $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => 'convidado@example.com',
            'permissions' => ['tasks.view', 'files.view'],
            'expires_in_hours' => 24,
            'project_role' => 'guest',
            'relationship_type' => 'guest_client',
        ])->assertCreated();

        Queue::assertPushed(SendAccessEmail::class, fn ($job) => $job->type === 'invitation');
        Mail::assertNothingSent();
        Queue::pushed(SendAccessEmail::class)->first()->handle();

        $token = basename($response->json('accept_url'));
        $invitation = ProjectInvitation::firstOrFail();
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $this->postJson("/api/v1/invitations/{$token}/accept", [
            'name' => 'Cliente Convidado',
            'password' => 'Senha-Segura1',
            'password_confirmation' => 'Senha-Segura1',
        ])->assertOk()->assertJsonPath('project_access_granted', true);

        $guest = User::where('email', 'convidado@example.com')->firstOrFail();
        $this->assertNull($guest->job_title);
        $this->assertNull($guest->department);
        $this->assertDatabaseHas('project_members', ['project_id' => $project->id, 'user_id' => $guest->id, 'role' => 'guest', 'relationship_type' => 'guest_client']);
        $this->assertSame(['tasks.view', 'files.view'], $project->members()->where('user_id', $guest->id)->firstOrFail()->permissions);
        Mail::assertSent(ProjectInvitationMail::class, fn ($mail) => $mail->hasTo('convidado@example.com'));

        $this->postJson("/api/v1/invitations/{$token}/accept", [
            'name' => 'Outro', 'password' => 'Senha-Segura1', 'password_confirmation' => 'Senha-Segura1',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_invitation_can_require_an_existing_user_to_create_a_new_password(): void
    {
        $this->seed();
        Queue::fake();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $existing = User::factory()->create(['email' => 'existente@example.com', 'password' => 'Senha-Antiga1']);
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto protegido', 'status' => 'planning', 'progress' => 0]);

        $response = $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => $existing->email,
            'project_role' => 'guest',
            'relationship_type' => 'guest_client',
            'requires_password_creation' => true,
        ])->assertCreated()->assertJsonPath('invitation.requires_password_creation', true);
        $token = basename($response->json('accept_url'));

        $this->actingAs($existing)->getJson("/api/v1/invitations/{$token}")
            ->assertOk()->assertJsonPath('requires_password_creation', true);
        $this->postJson("/api/v1/invitations/{$token}/accept", [])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson("/api/v1/invitations/{$token}/accept", [
            'password' => 'Senha-Nova123',
            'password_confirmation' => 'Senha-Nova123',
        ])->assertOk();

        $this->assertTrue(Hash::check('Senha-Nova123', $existing->fresh()->password));
    }

    public function test_existing_user_can_accept_without_changing_password_when_requirement_is_disabled(): void
    {
        $this->seed();
        Queue::fake();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $existing = User::factory()->create(['email' => 'sem-troca@example.com', 'password' => 'Senha-Mantida1']);
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto sem troca', 'status' => 'planning', 'progress' => 0]);

        $response = $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => $existing->email,
            'project_role' => 'guest',
            'relationship_type' => 'guest_client',
            'requires_password_creation' => false,
        ])->assertCreated()->assertJsonPath('invitation.requires_password_creation', false);
        $token = basename($response->json('accept_url'));

        $this->actingAs($existing)->postJson("/api/v1/invitations/{$token}/accept", [])->assertOk();
        $this->assertTrue(Hash::check('Senha-Mantida1', $existing->fresh()->password));
    }

    public function test_invitation_can_be_revoked_before_use(): void
    {
        $this->seed();
        Mail::fake();
        Queue::fake();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['company_id' => $admin->current_company_id, 'user_id' => $admin->id, 'name' => 'Projeto', 'status' => 'planning', 'progress' => 0]);
        $invitation = $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => 'guest@example.com', 'project_role' => 'guest', 'relationship_type' => 'guest_analyst',
        ])->assertCreated()->json('invitation');

        $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => 'externo@example.com', 'project_role' => 'collaborator', 'relationship_type' => 'guest_analyst',
        ])->assertUnprocessable()->assertJsonValidationErrors('project_role');

        $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => 'escrita@example.com', 'project_role' => 'guest', 'relationship_type' => 'guest_manager',
            'permissions' => ['files.upload'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');

        $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => 'cargo@example.com', 'project_role' => 'guest', 'relationship_type' => 'guest_client',
            'job_title' => 'Cargo indevido', 'department' => 'Departamento indevido',
        ])->assertUnprocessable()->assertJsonValidationErrors('job_title');

        $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/invitations", [
            'email' => 'analista@example.com', 'project_role' => 'analyst', 'relationship_type' => 'external_analyst',
            'permissions' => ['tasks.view', 'tasks.comment'],
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_name');

        $this->actingAs($admin)->postJson("/api/v1/invitations/{$invitation['id']}/revoke")
            ->assertOk()->assertJsonPath('message', 'Convite revogado.');
        $this->assertNotNull(ProjectInvitation::findOrFail($invitation['id'])->revoked_at);
    }
}
