<?php

namespace Tests\Feature;

use App\Models\InboxItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_arrival_preview_uses_previous_access_and_excludes_read_archived_and_foreign_items(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $user->forceFill(['notification_access_at' => now()->subHour()])->save();
        $old = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Antiga']);
        $old->forceFill(['created_at' => now()->subHours(2)])->save();
        $fresh = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Nova']);
        InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Lida', 'read_at' => now()]);
        InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Arquivada', 'archived_at' => now()]);
        InboxItem::create(['user_id' => User::factory()->create()->id, 'type' => 'task', 'title' => 'Privada']);
        $first = $this->actingAs($user)->getJson('/api/v1/inbox')->assertOk()
            ->assertJsonCount(1, 'new_items')->assertJsonPath('new_items.0.id', $fresh->id)->assertJsonPath('unread_count', 2);
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/inbox')->assertOk()->assertJsonPath('previous_access_at', $first->json('previous_access_at'));
        $this->patchJson("/api/v1/inbox/{$fresh->id}/read")->assertOk();
        $this->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(0, 'new_items')->assertJsonPath('unread_count', 1);
        $this->assertNotNull($user->fresh()->notification_access_at);
    }

    public function test_first_access_ignores_history_but_detects_arrivals_later_in_the_session(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Histórico']);
        $this->actingAs($user)->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(0, 'new_items');
        $this->travel(1)->minutes();
        $new = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Chegou depois']);
        $this->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(1, 'new_items')->assertJsonPath('new_items.0.id', $new->id);
    }

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

    public function test_user_can_clear_view_and_restore_messages_from_the_trash(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Primeira']);
        InboxItem::create(['user_id' => $user->id, 'type' => 'document', 'title' => 'Segunda']);
        $foreign = InboxItem::create(['user_id' => $other->id, 'type' => 'mention', 'title' => 'Privada']);

        $this->actingAs($user)->deleteJson('/api/v1/inbox')->assertOk()->assertJsonPath('archived_count', 2);
        $this->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('trash_count', 2);
        $this->getJson('/api/v1/inbox?filter=trash')->assertOk()->assertJsonCount(2, 'items');
        $this->patchJson("/api/v1/inbox/{$first->id}/restore")->assertOk();

        $this->assertNull($first->fresh()->archived_at);
        $this->assertNull($foreign->fresh()->archived_at);
    }

    public function test_messages_are_removed_after_thirty_days_in_the_trash(): void
    {
        $user = User::factory()->create();
        $expired = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Antiga', 'archived_at' => now()->subDays(31)]);
        $retained = InboxItem::create(['user_id' => $user->id, 'type' => 'task', 'title' => 'Recente', 'archived_at' => now()->subDays(29)]);

        $this->artisan('inbox:prune')->assertSuccessful();

        $this->assertDatabaseMissing('inbox_items', ['id' => $expired->id]);
        $this->assertDatabaseHas('inbox_items', ['id' => $retained->id]);
    }
}
