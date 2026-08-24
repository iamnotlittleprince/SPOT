<?php

namespace Tests\Feature;

use App\Models\InboxItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_and_archive_only_their_own_inbox_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $item = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Nova tarefa']);
        $foreign = InboxItem::create(['user_id' => $other->id, 'type' => 'mention', 'title' => 'Privada']);

        $this->actingAs($user)->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('unread_count', 1);
        $this->patchJson("/api/v1/inbox/{$item->id}/read")->assertOk();
        $this->assertNotNull($item->fresh()->read_at);
        $this->deleteJson("/api/v1/inbox/{$item->id}")->assertOk();
        $this->assertNotNull($item->fresh()->archived_at);
        $this->patchJson("/api/v1/inbox/{$foreign->id}/read")->assertNotFound();
    }
}
