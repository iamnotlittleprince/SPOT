<?php

namespace Tests\Feature;

use App\Jobs\SendAccessEmail;
use App\Models\User;
use App\Models\UserActivationToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccessEmailQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function activation(): UserActivationToken
    {
        $user = User::factory()->create(['name' => 'Ana Teste', 'email' => 'ana@example.com', 'account_status' => 'pending_activation', 'active' => false]);

        return UserActivationToken::create(['user_id' => $user->id, 'created_by' => $user->id, 'token_hash' => hash('sha256', 'old-token'), 'expires_at' => now()->addHours(2)]);
    }

    public function test_request_persists_encrypted_job_and_worker_sends_email(): void
    {
        config(['queue.default' => 'database']);
        $old = $this->activation();
        $response = $this->postJson('/api/v1/first-access', ['name' => 'Ana Teste', 'email' => 'ana@example.com'])->assertOk();
        $this->assertNotNull($old->fresh()->revoked_at);
        $row = DB::table('jobs')->sole();
        $this->assertSame('emails', $row->queue);
        $payload = json_decode($row->payload, true);
        $job = unserialize(decrypt($payload['data']['command']));
        $this->assertInstanceOf(SendAccessEmail::class, $job);
        $this->assertStringNotContainsString($job->url, $row->payload);
        $this->assertCount(0, app('mail.manager')->mailer()->getSymfonyTransport()->messages());

        $this->artisan('queue:work', ['--once' => true, '--queue' => 'emails'])->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(1, app('mail.manager')->mailer()->getSymfonyTransport()->messages());

        $this->postJson('/api/v1/first-access', ['name' => 'Outra Pessoa', 'email' => 'ana@example.com'])
            ->assertOk()->assertExactJson($response->json());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_revoked_and_expired_links_are_not_sent(): void
    {
        $activation = $this->activation();
        Mail::shouldReceive('raw')->never();
        $activation->update(['revoked_at' => now()]);
        (new SendAccessEmail('activation', $activation->id, 'http://localhost/first-access/test'))->handle();
        $activation->update(['revoked_at' => null, 'expires_at' => now()->subMinute()]);
        (new SendAccessEmail('activation', $activation->id, 'http://localhost/first-access/test'))->handle();
    }

    public function test_mail_failure_retries_and_is_recorded_after_last_attempt(): void
    {
        config(['queue.default' => 'database']);
        $activation = $this->activation();
        Mail::shouldReceive('raw')->twice()->andThrow(new \RuntimeException('SMTP indisponível'));
        SendAccessEmail::dispatch('activation', $activation->id, 'http://localhost/first-access/test');
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'emails'])->assertExitCode(0);
        $row = DB::table('jobs')->sole();
        $this->assertSame(1, $row->attempts);
        $this->assertGreaterThanOrEqual(now()->timestamp + 55, $row->available_at);
        $this->assertDatabaseCount('failed_jobs', 0);
        DB::table('jobs')->update(['attempts' => 2, 'available_at' => now()->timestamp]);
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'emails'])->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_rollback_does_not_enqueue_email(): void
    {
        config(['queue.default' => 'database']);
        $activation = $this->activation();
        DB::beginTransaction();
        SendAccessEmail::dispatch('activation', $activation->id, 'http://localhost/first-access/test')->afterCommit();
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
    }
}
