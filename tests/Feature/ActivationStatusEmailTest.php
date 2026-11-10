<?php

namespace Tests\Feature;

use App\Jobs\SendAccessEmail;
use App\Mail\AccountActivationMail;
use App\Models\User;
use App\Models\UserActivationToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ActivationStatusEmailTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['queue.default' => 'database']);
    }

    public function test_every_transition_to_pending_enqueues_a_new_activation_and_revokes_previous_links(): void
    {
        Mail::fake();
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $user = User::factory()->create(['current_company_id' => $admin->current_company_id, 'organization_id' => $admin->organization_id, 'account_status' => 'active', 'active' => true, 'preferred_locale' => 'es']);
        $old = UserActivationToken::create(['user_id' => $user->id, 'created_by' => $admin->id, 'token_hash' => hash('sha256', 'previous-token'), 'expires_at' => now()->addDay()]);
        $endpoint = '/api/v1/admin/users/'.$user->id;
        $payload = ['profile' => 'analista', 'account_status' => 'pending_activation'];
        $this->actingAs($admin)->patchJson($endpoint, $payload)->assertOk()->assertJsonPath('activation_email_queued', true);
        $this->assertFalse($user->fresh()->active);
        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertDatabaseCount('jobs', 1);
        $first = UserActivationToken::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $first->created_by);
        $this->assertTrue($first->isUsable());
        $job = unserialize(decrypt(json_decode(DB::table('jobs')->sole()->payload, true)['data']['command']));
        $this->assertInstanceOf(SendAccessEmail::class, $job);
        $this->assertSame($first->id, $job->recordId);
        $job->handle();
        Mail::assertSent(AccountActivationMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->mailLocale === 'es' && $mail->activationUrl === $job->url);

        // Editing a pending account is not a new status transition.
        $this->patchJson($endpoint, [...$payload, 'department' => 'Suporte'])->assertOk()->assertJsonPath('activation_email_queued', false);
        $this->assertDatabaseCount('jobs', 1);
        $this->patchJson($endpoint, [...$payload, 'account_status' => 'suspended'])->assertOk()->assertJsonPath('activation_email_queued', false);
        $this->patchJson($endpoint, $payload)->assertOk()->assertJsonPath('activation_email_queued', true);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertNotNull($first->fresh()->revoked_at);
        $second = UserActivationToken::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertNotSame($first->token_hash, $second->token_hash);
        $this->assertTrue($second->isUsable());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activation_created', 'auditable_id' => $user->id, 'user_id' => $admin->id]);
    }

    public function test_non_admin_cannot_trigger_activation_by_changing_status(): void
    {
        $admin = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $user = User::factory()->create(['current_company_id' => $admin->current_company_id]);
        $user->profiles()->attach(DB::table('profiles')->where('slug', 'analista')->value('id'), ['company_id' => $admin->current_company_id]);
        $this->actingAs($user)->patchJson('/api/v1/admin/users/'.$user->id, ['profile' => 'analista', 'account_status' => 'pending_activation'])->assertForbidden();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('user_activation_tokens', 0);
    }
}
