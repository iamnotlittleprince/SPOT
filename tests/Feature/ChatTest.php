<?php

namespace Tests\Feature;

use App\Models\{ChatAccessRequest, ChatAttachment, ChatMessage, Company, InboxItem, Profile, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Http, Storage};
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function person(Company $company, string $role = 'analista'): User
    {
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::firstOrCreate(['slug' => $role], ['name' => $role]);
        $user->profiles()->attach($profile, ['company_id' => $company->id]);
        return $user;
    }

    private function team(): array
    {
        Http::preventStrayRequests();
        $company = Company::create(['legal_name' => 'Chat']);
        $analyst = $this->person($company);
        $manager = $this->person($company, 'gestor');
        $admin = $this->person($company, 'gestor-administrador');
        $outsider = $this->person($company);
        $project = Project::create(['company_id' => $company->id, 'user_id' => $manager->id, 'name' => 'Time A']);
        $project->members()->createMany([
            ['user_id' => $analyst->id, 'role' => 'analyst', 'active' => true],
            ['user_id' => $manager->id, 'role' => 'manager', 'active' => true],
        ]);
        return [$company, $analyst, $manager, $admin, $outsider, $project];
    }

    private function send(User $actor, User $peer, string $body = 'Olá!'): int
    {
        return $this->actingAs($actor)->postJson("/api/v1/chat/users/{$peer->id}/messages", ['body' => $body])->assertCreated()->json('message.id');
    }

    private function ask(User $actor, User $peer, ?User $reviewer = null): int
    {
        $reviewer ??= User::with('profiles')->where('current_company_id', $actor->current_company_id)->where('id', '!=', $actor->id)->get()->first(fn ($person) => app(\App\Domain\Chat\ChatAccess::class)->canReview($person));
        return $this->actingAs($actor)->postJson("/api/v1/chat/users/{$peer->id}/requests", ['reason' => 'Precisamos alinhar a entrega.', 'reviewer_id' => $reviewer?->id])->assertCreated()->json('request_id');
    }

    public function test_teammates_exchange_messages_and_directory_does_not_expose_email_or_foreign_users(): void
    {
        [$company, $analyst, $manager, $admin, $outside] = $this->team();
        $foreign = $this->person(Company::create(['legal_name' => 'Outra']));
        $guest = $this->person($company, 'convidado');
        $response = $this->actingAs($analyst)->getJson('/api/v1/chat')->assertOk();
        $contacts = collect($response->json('contacts'))->keyBy('id');
        $this->assertTrue($contacts[$manager->id]['can_chat']);
        $this->assertFalse($contacts[$outside->id]['can_chat']);
        $this->assertFalse($contacts->has($foreign->id));
        $this->assertFalse($contacts->has($guest->id));
        $this->assertArrayNotHasKey('email', $contacts[$manager->id]);
        $this->assertFalse($response->json('can_review'));
        $id = $this->send($analyst, $manager);
        $this->actingAs($manager)->getJson("/api/v1/chat/users/{$analyst->id}/messages")->assertOk()->assertJsonPath('messages.0.id', $id)->assertJsonPath('messages.0.can_edit', false);
        $this->send($manager, $analyst, 'Bom dia!');
        $this->actingAs($analyst)->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertJsonCount(2, 'messages');
        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertDatabaseCount('chat_message_revisions', 2);
    }

    public function test_outsiders_need_approval_from_manager_administrator_and_cannot_self_approve(): void
    {
        [$company, $analyst, $manager, $admin, $outside] = $this->team();
        foreach ([$analyst, $manager] as $actor) {
            $this->actingAs($actor)->postJson("/api/v1/chat/users/{$outside->id}/messages", ['body' => 'Indevido'])->assertForbidden();
            $this->getJson("/api/v1/chat/users/{$outside->id}/messages")->assertForbidden();
        }
        $id = $this->ask($analyst, $outside);
        $this->actingAs($analyst)->postJson("/api/v1/chat/users/{$outside->id}/requests", ['reason' => 'Repetida', 'reviewer_id' => $admin->id])->assertConflict();
        $this->actingAs($outside)->postJson("/api/v1/chat/users/{$analyst->id}/requests", ['reason' => 'Pedido inverso', 'reviewer_id' => $admin->id])->assertConflict();
        $this->assertDatabaseCount('chat_access_requests', 1);
        $this->assertDatabaseHas('inbox_items', ['user_id' => $admin->id, 'title' => 'Solicitação de chat']);
        foreach ([$analyst, $manager] as $actor) {
            $this->actingAs($actor)->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertForbidden();
        }
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve', 'note' => 'Alinhamento autorizado.'])->assertOk();
        $this->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'reject'])->assertConflict();
        $this->send($analyst, $outside);
        $this->send($outside, $analyst);
        $this->actingAs($manager)->getJson('/api/v1/chat')->assertJsonCount(0, 'requests');
        $this->actingAs($admin)->getJson("/api/v1/chat/users/{$analyst->id}/messages")->assertOk()->assertJsonCount(0, 'messages');
        $otherAdmin = $this->person($company, 'gestor-administrador');
        $this->actingAs($admin)->postJson("/api/v1/chat/users/{$manager->id}/requests", ['reason' => 'Desnecessário', 'reviewer_id' => $otherAdmin->id])->assertConflict();
        $legacy = app(\App\Domain\Chat\ChatAccess::class)->conversation($admin, $manager, true);
        $selfId = ChatAccessRequest::create(['conversation_id' => $legacy->id, 'requester_id' => $admin->id, 'assigned_reviewer_id' => $otherAdmin->id, 'reason' => 'Pedido legado', 'status' => 'pending'])->id;
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$selfId}/decision", ['decision' => 'approve'])->assertForbidden();
    }

    public function test_analysts_and_managers_can_request_approval_from_admins_outside_the_project(): void
    {
        [$company, $analyst, $manager, , $outside, $project] = $this->team();
        foreach ([[$analyst, 'administrador'], [$manager, 'gestor-administrador']] as [$requester, $role]) {
            $reviewer = $this->person($company, $role);
            $this->assertFalse($project->members()->where('user_id', $reviewer->id)->exists());
            $this->actingAs($requester)->getJson('/api/v1/chat')->assertOk()->assertJsonPath('reviewers_available', true);
            $id = $this->ask($requester, $outside, $reviewer);
            $this->assertDatabaseHas('inbox_items', ['user_id' => $reviewer->id, 'title' => 'Solicitação de chat']);
            $this->actingAs($reviewer)->getJson('/api/v1/chat')->assertOk()->assertJsonPath('can_review', true);
            $this->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertOk();
            $this->send($requester, $outside);
            $this->actingAs($reviewer)->getJson("/api/v1/chat/users/{$requester->id}/messages")->assertOk()->assertJsonCount(0, 'messages');
        }
        $admin = $this->person($company, 'administrador');
        $this->actingAs($admin)->postJson("/api/v1/chat/users/{$outside->id}/requests", ['reason' => 'Desnecessário', 'reviewer_id' => $admin->id])->assertConflict();
    }

    public function test_administrative_roles_start_direct_chats_and_recipients_can_reply_without_requests(): void
    {
        $company = Company::create(['legal_name' => 'Empresa']);
        $outside = $this->person($company);
        $guest = $this->person($company, 'convidado');
        $foreign = $this->person(Company::create(['legal_name' => 'Outra empresa']));
        foreach (['administrador', 'gestor-administrador'] as $role) {
            $admin = $this->person($company, $role);
            $contacts = collect($this->actingAs($admin)->getJson('/api/v1/chat')->assertOk()->assertJsonPath('can_start_without_approval', true)->json('contacts'))->keyBy('id');
            $this->assertTrue($contacts[$outside->id]['can_chat']);
            $this->assertTrue($contacts[$outside->id]['administrative_access']);
            $this->assertFalse($contacts->has($guest->id));
            $this->assertFalse($contacts->has($foreign->id));
            $this->actingAs($admin)->getJson("/api/v1/chat/users/{$outside->id}/messages")->assertOk()->assertJsonCount(0, 'messages');
            $this->actingAs($outside)->postJson("/api/v1/chat/users/{$admin->id}/messages", ['body' => 'Antes do contato'])->assertForbidden();
            $this->actingAs($admin)->postJson("/api/v1/chat/users/{$outside->id}/requests", [])->assertConflict();
            $this->send($admin, $outside, 'Contato administrativo');
            $recipientContacts = collect($this->actingAs($outside)->getJson('/api/v1/chat')->assertOk()->assertJsonPath('can_start_without_approval', false)->json('contacts'))->keyBy('id');
            $this->assertTrue($recipientContacts[$admin->id]['can_chat']);
            $this->assertTrue($recipientContacts[$admin->id]['administrative_access']);
            $this->getJson("/api/v1/chat/users/{$admin->id}/messages")->assertOk()->assertJsonPath('messages.0.body', 'Contato administrativo');
            $this->send($outside, $admin, 'Resposta sem solicitação');
            $this->assertDatabaseCount('chat_access_requests', 0);
            $this->actingAs($admin)->postJson("/api/v1/chat/users/{$guest->id}/messages", ['body' => 'Indevido'])->assertNotFound();
            $this->postJson("/api/v1/chat/users/{$foreign->id}/messages", ['body' => 'Indevido'])->assertNotFound();
            $admin->profiles()->sync([Profile::where('slug', 'analista')->value('id') => ['company_id' => $company->id]]);
            $this->actingAs($admin->fresh())->getJson("/api/v1/chat/users/{$outside->id}/messages")->assertForbidden();
            $this->actingAs($outside->fresh())->getJson("/api/v1/chat/users/{$admin->id}/messages")->assertForbidden();
        }
    }

    public function test_requests_are_directed_only_to_the_selected_eligible_reviewer(): void
    {
        [$company, $analyst, $manager, $firstReviewer, $outside] = $this->team();
        $chosen = $this->person($company, 'administrador');
        $this->actingAs($analyst)->getJson('/api/v1/chat')->assertOk()->assertJsonFragment(['id' => $chosen->id, 'name' => $chosen->name]);
        $this->postJson("/api/v1/chat/users/{$outside->id}/requests", ['reason' => 'Alinhamento'])->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');
        $foreign = $this->person(Company::create(['legal_name' => 'Outra empresa']), 'administrador');
        $inactive = $this->person($company, 'administrador');
        $inactive->update(['active' => false]);
        foreach ([$analyst, $manager, $foreign, $inactive] as $invalid) {
            $this->actingAs($analyst)->postJson("/api/v1/chat/users/{$outside->id}/requests", ['reason' => 'Alinhamento', 'reviewer_id' => $invalid->id])->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');
        }
        $id = $this->ask($analyst, $outside, $chosen);
        $this->assertDatabaseHas('chat_access_requests', ['id' => $id, 'assigned_reviewer_id' => $chosen->id, 'reviewer_id' => null]);
        $this->assertDatabaseHas('inbox_items', ['user_id' => $chosen->id, 'title' => 'Solicitação de chat']);
        $this->assertDatabaseMissing('inbox_items', ['user_id' => $firstReviewer->id, 'title' => 'Solicitação de chat']);
        $this->actingAs($firstReviewer)->getJson('/api/v1/chat')->assertJsonCount(0, 'requests');
        $this->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($analyst)->getJson('/api/v1/chat')->assertJsonPath('requests.0.assigned_reviewer_name', $chosen->name)->assertJsonPath('requests.0.can_decide', false);
        $this->actingAs($chosen)->getJson('/api/v1/chat')->assertJsonPath('requests.0.can_decide', true);
        $this->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertOk();
        $this->assertDatabaseHas('chat_access_requests', ['id' => $id, 'reviewer_id' => $chosen->id, 'status' => 'approved']);
    }

    public function test_request_can_be_rejected_retried_approved_and_revoked_without_deleting_messages(): void
    {
        [, $analyst, $manager, $admin, $outside] = $this->team();
        $id = $this->ask($analyst, $outside);
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'reject'])->assertOk();
        $this->actingAs($analyst)->postJson("/api/v1/chat/users/{$outside->id}/messages", ['body' => 'Indevido'])->assertForbidden();
        $id = $this->ask($analyst, $outside);
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertOk();
        $message = $this->send($outside, $analyst);
        $this->actingAs($manager)->postJson("/api/v1/chat/requests/{$id}/revoke")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$id}/revoke")->assertOk();
        $this->actingAs($analyst)->getJson("/api/v1/chat/users/{$outside->id}/messages")->assertForbidden();
        $this->patchJson("/api/v1/chat/messages/{$message}", ['body' => 'Tentativa', 'version' => 1])->assertForbidden();
        $this->postJson("/api/v1/chat/messages/{$message}/translate", ['target' => 'en'])->assertForbidden();
        $this->getJson("/api/v1/chat/messages/{$message}/revisions")->assertForbidden();
        $this->assertDatabaseHas('chat_messages', ['id' => $message, 'body' => 'Olá!']);
        $this->ask($analyst, $outside);
    }

    public function test_only_author_edits_within_original_24_hours_and_every_version_is_retained(): void
    {
        [, $analyst, $manager, $admin] = $this->team();
        $this->travelTo(now()->startOfSecond());
        $id = $this->send($analyst, $manager, 'Original');
        $this->actingAs($manager)->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Outro autor', 'version' => 1])->assertForbidden();
        $this->actingAs($admin)->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Administrador', 'version' => 1])->assertNotFound();
        $this->travel(23)->hours();
        $this->actingAs($analyst)->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Corrigida', 'version' => 1])->assertOk()->assertJsonPath('message.version', 2);
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Conflito', 'version' => 1])->assertConflict();
        $this->travel(1)->hours();
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'No limite', 'version' => 2])->assertOk();
        $this->travel(1)->seconds();
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Prazo encerrado', 'version' => 3])->assertConflict();
        $this->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertJsonPath('messages.0.can_edit', false);
        $this->actingAs($manager)->getJson("/api/v1/chat/messages/{$id}/revisions")->assertOk()->assertJsonCount(3, 'revisions')->assertJsonPath('revisions.0.body', 'Original')->assertJsonPath('revisions.2.body', 'No limite');
        $this->assertDatabaseHas('chat_messages', ['id' => $id, 'version' => 3, 'body' => 'No limite']);
    }

    public function test_guests_even_with_other_profiles_and_inactive_accounts_have_no_chat(): void
    {
        [$company, $analyst, $manager] = $this->team();
        $id = $this->send($analyst, $manager);
        $guest = $this->person($company, 'convidado');
        $guest->profiles()->attach(Profile::where('slug', 'analista')->value('id'), ['company_id' => $company->id]);
        foreach ([$guest, tap($analyst->fresh(), fn ($user) => $user->update(['active' => false]))] as $actor) {
            $this->actingAs($actor)->getJson('/api/v1/chat')->assertForbidden();
            $this->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertForbidden();
            $this->postJson("/api/v1/chat/users/{$manager->id}/messages", ['body' => 'Indevido'])->assertForbidden();
            $this->postJson("/api/v1/chat/users/{$manager->id}/requests", ['reason' => 'Indevido'])->assertForbidden();
            $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Indevido', 'version' => 1])->assertForbidden();
            $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'en'])->assertForbidden();
        }
        $this->actingAs($manager)->postJson("/api/v1/chat/users/{$guest->id}/messages", ['body' => 'Convidado'])->assertNotFound();
        $this->getJson('/api/v1/auth/me')->assertJsonPath('can_chat', true);
        $this->actingAs($guest)->getJson('/api/v1/auth/me')->assertJsonPath('can_chat', false);
    }

    public function test_membership_removal_revocation_guest_role_and_deleted_project_remove_access(): void
    {
        [, $analyst, $manager, , , $project] = $this->team();
        $id = $this->send($analyst, $manager);
        foreach ([['active' => false], ['access_revoked_at' => now()], ['role' => 'guest']] as $change) {
            $project->members()->where('user_id', $analyst->id)->update(['active' => true, 'role' => 'analyst', 'access_revoked_at' => null, ...$change]);
            $this->actingAs($analyst)->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertForbidden();
            $this->postJson("/api/v1/chat/users/{$manager->id}/messages", ['body' => 'Indevido'])->assertForbidden();
            $this->getJson("/api/v1/chat/messages/{$id}/revisions")->assertForbidden();
        }
        $project->members()->where('user_id', $analyst->id)->update(['active' => true, 'role' => 'analyst', 'access_revoked_at' => null]);
        $project->delete();
        $this->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertForbidden();
    }

    public function test_cross_company_and_third_party_access_are_blocked_even_for_reviewer(): void
    {
        [, $analyst, $manager, $admin, $outside] = $this->team();
        $foreign = $this->person(Company::create(['legal_name' => 'Fora']), 'gestor-administrador');
        $id = $this->send($analyst, $manager);
        $requestId = $this->ask($analyst, $outside);
        $this->actingAs($foreign)->postJson("/api/v1/chat/requests/{$requestId}/decision", ['decision' => 'approve'])->assertNotFound();
        $this->postJson("/api/v1/chat/requests/{$requestId}/revoke")->assertNotFound();
        foreach ([$foreign, $admin, $outside] as $actor) {
            $this->actingAs($actor)->getJson("/api/v1/chat/messages/{$id}/revisions")->assertNotFound();
            $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'es'])->assertNotFound();
        }
        $this->actingAs($analyst)->postJson("/api/v1/chat/users/{$foreign->id}/messages", ['body' => 'Fora'])->assertNotFound();
        $this->postJson("/api/v1/chat/users/{$foreign->id}/requests", ['reason' => 'Fora'])->assertNotFound();
    }

    public function test_translation_preserves_original_caches_by_version_and_handles_missing_service(): void
    {
        [, $analyst, $manager] = $this->team();
        $id = $this->send($analyst, $manager, 'Olá!');
        config(['services.chat_translation.url' => null]);
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'en'])->assertStatus(503);
        Http::assertNothingSent();
        config(['services.chat_translation.url' => 'https://translator.example.test', 'services.chat_translation.key' => 'server-only']);
        Http::fake(['translator.example.test/*' => fn ($request) => $request['target'] === 'es' ? Http::response(['error' => 'unavailable'], 500) : Http::response(['translatedText' => 'Hello!'])]);
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'en'])->assertOk()->assertJsonPath('text', 'Hello!')->assertJsonPath('version', 1);
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'en'])->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['q'] === 'Olá!' && $request['source'] === 'auto' && $request['api_key'] === 'server-only' && $request['format'] === 'text');
        $this->assertDatabaseHas('chat_messages', ['id' => $id, 'body' => 'Olá!']);
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Bom dia', 'version' => 1])->assertOk();
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'en'])->assertOk()->assertJsonPath('version', 2);
        Http::assertSentCount(2);
        ChatMessage::whereKey($id)->update(['created_at' => now()->subHours(25)]);
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'pt'])->assertOk();
        $this->assertDatabaseHas('chat_messages', ['id' => $id, 'body' => 'Bom dia']);
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'xx'])->assertUnprocessable();
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'es'])->assertStatus(503);
    }

    public function test_chat_messages_cannot_be_deleted_or_emptied(): void
    {
        [, $analyst, $manager, $admin] = $this->team();
        $id = $this->send($analyst, $manager);
        foreach ([$analyst, $manager, $admin] as $actor) {
            $this->actingAs($actor)->deleteJson("/api/v1/chat/messages/{$id}")->assertStatus(405);
        }
        $this->actingAs($analyst)->patchJson("/api/v1/chat/messages/{$id}", ['body' => '  ', 'version' => 1])->assertUnprocessable();
        $this->postJson("/api/v1/chat/users/{$manager->id}/messages", ['body' => str_repeat('a', 5001)])->assertUnprocessable();
        $this->assertDatabaseHas('chat_messages', ['id' => $id, 'body' => 'Olá!']);
        $this->expectException(\LogicException::class);
        ChatMessage::findOrFail($id)->delete();
    }

    public function test_pagination_retains_access_to_old_messages(): void
    {
        [, $analyst, $manager] = $this->team();
        $id = $this->send($analyst, $manager);
        $conversation = ChatMessage::findOrFail($id)->conversation_id;
        foreach (range(1, 55) as $number) {
            ChatMessage::create(['conversation_id' => $conversation, 'sender_id' => $analyst->id, 'body' => 'Mensagem '.$number]);
        }
        $response = $this->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true);
        $first = $response->json('messages.0.id');
        $this->getJson("/api/v1/chat/users/{$manager->id}/messages?before={$first}")->assertOk()->assertJsonCount(6, 'messages')->assertJsonPath('has_more', false)->assertJsonPath('messages.0.id', $id);
    }

    public function test_ineligible_reviewer_invalidates_grant_and_request_can_be_repeated(): void
    {
        [, $analyst, $manager, $admin, $outside] = $this->team();
        $id = $this->ask($analyst, $outside);
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertOk();
        $this->send($analyst, $outside);
        $admin->update(['active' => false]);
        $this->actingAs($analyst)->postJson("/api/v1/chat/users/{$outside->id}/messages", ['body' => 'Indevido'])->assertForbidden();
        $this->postJson("/api/v1/chat/users/{$outside->id}/requests", ['reason' => 'Outra autorização', 'reviewer_id' => $admin->id])->assertConflict();
        $replacement = $this->person($analyst->companies()->firstOrFail(), 'gestor-administrador');
        $this->ask($analyst, $outside);
    }
    public function test_company_profile_and_pending_participants_are_rechecked(): void
    {
        [$company, $analyst, $manager, $admin, $outside] = $this->team();
        $id = $this->ask($analyst, $outside);
        $outside->profiles()->attach(Profile::firstOrCreate(['slug' => 'convidado'], ['name' => 'Convidado']), ['company_id' => $company->id]);
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$id}/decision", ['decision' => 'approve'])->assertConflict();
        $this->assertDatabaseHas('chat_access_requests', ['id' => $id, 'status' => 'pending']);
        $otherCompany = Company::create(['legal_name' => 'Outro vínculo']);
        $unprofiled = User::factory()->create(['current_company_id' => $company->id]);
        $unprofiled->profiles()->attach(Profile::where('slug', 'analista')->firstOrFail(), ['company_id' => $otherCompany->id]);
        $this->actingAs($unprofiled)->getJson('/api/v1/chat')->assertForbidden();
        $this->actingAs($analyst)->postJson("/api/v1/chat/users/{$analyst->id}/messages", ['body' => 'Para mim'])->assertNotFound();
        $this->postJson("/api/v1/chat/users/{$manager->id}/messages", ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_bootstrap_and_message_identity_survive_page_reload_and_profile_update(): void
    {
        [, $analyst, $manager] = $this->team();
        $this->actingAs($analyst)->get('/')->assertOk()->assertSee('\\u0022id\\u0022:'.$analyst->id, false);
        $id = $this->send($analyst, $manager);
        $this->getJson("/api/v1/chat/users/{$manager->id}/messages")->assertOk()->assertJsonPath('messages.0.is_mine', true)->assertJsonPath('messages.0.sender_name', $analyst->name);
        $this->actingAs($manager)->getJson("/api/v1/chat/users/{$analyst->id}/messages")->assertOk()->assertJsonPath('messages.0.is_mine', false)->assertJsonPath('messages.0.sender_name', $analyst->name);
        $this->actingAs($analyst)->patchJson('/profile', ['first_name' => 'Novo nome', 'timezone' => 'America/Sao_Paulo'])->assertOk()->assertJsonPath('id', $analyst->id);
    }

    public function test_attachments_are_private_and_downloadable_only_while_chat_access_is_valid(): void
    {
        Storage::fake('local');
        [$company, $analyst, $manager, $admin, $outside, $project] = $this->team();
        $response = $this->actingAs($analyst)->post('/api/v1/chat/users/'.$manager->id.'/messages', [
            'body' => 'Segue o documento.', 'attachments' => [UploadedFile::fake()->createWithContent('relatorio.txt', 'Conteudo privado')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message.is_mine', true)->assertJsonPath('message.attachments.0.name', 'relatorio.txt');
        $messageId = $response->json('message.id');
        $fileId = $response->json('message.attachments.0.id');
        $this->assertArrayNotHasKey('path', $response->json('message.attachments.0'));
        $attachment = ChatAttachment::findOrFail($fileId);
        Storage::disk('local')->assertExists($attachment->path);
        $url = "/api/v1/chat/attachments/{$fileId}/download";
        $this->get($url)->assertOk()->assertDownload('relatorio.txt')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/octet-stream');
        $this->actingAs($manager)->get($url)->assertOk()->assertStreamedContent('Conteudo privado');
        $this->getJson("/api/v1/chat/users/{$analyst->id}/messages")->assertJsonPath('messages.0.attachments.0.id', $fileId);
        $foreign = $this->person(Company::create(['legal_name' => 'Outra empresa']), 'gestor-administrador');
        foreach ([$admin, $outside, $foreign] as $actor) $this->actingAs($actor)->getJson($url)->assertNotFound();
        $guest = $this->person($company, 'convidado');
        $this->actingAs($guest)->getJson($url)->assertForbidden();
        $project->members()->where('user_id', $analyst->id)->update(['active' => false]);
        $this->actingAs($analyst)->getJson($url)->assertForbidden();
        $this->actingAs($manager)->getJson($url)->assertForbidden();
        $this->assertDatabaseHas('chat_messages', ['id' => $messageId]);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_attachment_only_messages_preserve_files_after_caption_edits_and_have_no_delete_route(): void
    {
        Storage::fake('local');
        [, $analyst, $manager, $admin, $outside] = $this->team();
        $grant = $this->ask($analyst, $outside);
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$grant}/decision", ['decision' => 'approve'])->assertOk();
        $response = $this->actingAs($analyst)->post('/api/v1/chat/users/'.$outside->id.'/messages', [
            'attachments' => [UploadedFile::fake()->createWithContent('notas.txt', 'Notas da entrega')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message.body', '');
        $id = $response->json('message.id');
        $fileId = $response->json('message.attachments.0.id');
        $path = ChatAttachment::findOrFail($fileId)->path;
        $this->postJson("/api/v1/chat/messages/{$id}/translate", ['target' => 'en'])->assertUnprocessable();
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => 'Legenda', 'version' => 1])->assertOk()->assertJsonPath('message.attachments.0.id', $fileId);
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body' => '', 'version' => 2])->assertOk()->assertJsonPath('message.body', '')->assertJsonPath('message.attachments.0.id', $fileId);
        $this->deleteJson("/api/v1/chat/attachments/{$fileId}/download")->assertStatus(405);
        $this->actingAs($outside)->get("/api/v1/chat/attachments/{$fileId}/download")->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/chat/requests/{$grant}/revoke")->assertOk();
        $this->actingAs($outside)->getJson("/api/v1/chat/attachments/{$fileId}/download")->assertForbidden();
        Storage::disk('local')->assertExists($path);
        $this->expectException(\LogicException::class);
        ChatAttachment::findOrFail($fileId)->delete();
    }

    public function test_invalid_attachments_are_rejected_without_creating_messages_or_files(): void
    {
        Storage::fake('local');
        [, $analyst, $manager, $admin, $outside] = $this->team();
        $url = '/api/v1/chat/users/'.$manager->id.'/messages';
        $headers = ['Accept' => 'application/json'];
        $this->actingAs($analyst)->post($url, ['attachments' => [UploadedFile::fake()->create('grande.pdf', 2049, 'application/pdf')]], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $temporary = tempnam(sys_get_temp_dir(), 'spot-chat-type-');
        file_put_contents($temporary, '<?php echo 1;');
        try {
            $this->post($url, ['attachments' => [new UploadedFile($temporary, 'executavel.pdf', 'application/pdf', null, true)]], $headers)->assertUnprocessable();
        } finally { if (file_exists($temporary)) unlink($temporary); }
        $this->post($url, ['attachments' => [UploadedFile::fake()->createWithContent('arquivo.exe', 'texto')]], $headers)->assertUnprocessable();
        $this->post($url, ['attachments' => array_map(fn ($i) => UploadedFile::fake()->createWithContent("arquivo{$i}.txt", 'texto'), range(1, 4))], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->post('/api/v1/chat/users/'.$outside->id.'/messages', ['attachments' => [UploadedFile::fake()->createWithContent('fora.txt', 'texto')]], $headers)->assertForbidden();
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failed_attachment_persistence_rolls_back_message_and_cleans_uploaded_file(): void
    {
        Storage::fake('local');
        [, $analyst, $manager] = $this->team();
        Event::listen('eloquent.creating: '.ChatAttachment::class, fn () => throw new \RuntimeException('Falha simulada ao salvar o anexo.'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($analyst)->post('/api/v1/chat/users/'.$manager->id.'/messages', [
                'attachments' => [UploadedFile::fake()->createWithContent('entrega.txt', 'Conteudo')],
            ], ['Accept' => 'application/json']);
            $this->fail('A falha simulada deveria interromper o envio.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('Falha simulada ao salvar o anexo.', $failure->getMessage());
        } finally { Event::forget('eloquent.creating: '.ChatAttachment::class); }
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_message_revisions', 0);
        $this->assertDatabaseCount('chat_attachments', 0);
        $this->assertDatabaseCount('inbox_items', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_formatted_message_uses_canonical_text_and_revisions_retain_styles(): void
    {
        [, $analyst, $manager] = $this->team();
        $format = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Olá', 'marks' => [['type' => 'bold'], ['type' => 'textStyle', 'attrs' => ['color' => 'purple', 'fontSize' => 'large']]]], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'equipe', 'marks' => [['type' => 'highlight']]]]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Entrega']]]]]]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Site', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.test/']]]]]],
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => '<script>apenas texto</script>']]],
        ]];
        $response = $this->actingAs($analyst)->postJson('/api/v1/chat/users/'.$manager->id.'/messages', ['body' => 'Texto falsificado', 'body_format' => $format])->assertCreated()->assertJsonPath('message.body', "Olá\nequipe\nEntrega\nSite\n<script>apenas texto</script>");
        $id = $response->json('message.id');
        $this->assertSame($format, $response->json('message.body_format'));
        $changed = $format;
        $changed['content'][0]['content'][0]['marks'][0]['type'] = 'italic';
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body_format' => $changed, 'version' => 1])->assertOk()->assertJsonPath('message.version', 2)->assertJsonPath('message.body_format.content.0.content.0.marks.0.type', 'italic');
        $this->actingAs($manager)->getJson("/api/v1/chat/messages/{$id}/revisions")->assertOk()->assertJsonPath('revisions.0.body_format.content.0.content.0.marks.0.type', 'bold')->assertJsonPath('revisions.1.body_format.content.0.content.0.marks.0.type', 'italic');
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body_format' => $changed, 'version' => 2])->assertForbidden();
    }

    public function test_unsafe_or_excessive_formatting_is_rejected_before_persistence(): void
    {
        [, $analyst, $manager] = $this->team();
        $base = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texto']]]]];
        $invalid = [['type' => 'script', 'text' => 'alert(1)']];
        foreach (['javascript:alert(1)', 'data:text/html,x', 'file:///etc/passwd', '//example.test', 'https://user:password@example.test', "https://example.test/\n"] as $href) {
            $doc = $base; $doc['content'][0]['content'][0]['marks'] = [['type' => 'link', 'attrs' => ['href' => $href]]]; $invalid[] = $doc;
        }
        foreach ([['color' => 'red; background:url(x)'], ['fontSize' => '999px'], ['onclick' => 'alert(1)']] as $attrs) {
            $doc = $base; $doc['content'][0]['content'][0]['marks'] = [['type' => 'textStyle', 'attrs' => $attrs]]; $invalid[] = $doc;
        }
        $doc = $base; $doc['content'][0]['attrs'] = ['onclick' => 'alert(1)']; $invalid[] = $doc;
        $doc = $base; $doc['content'][0]['content'][0]['text'] = str_repeat('a', 5001); $invalid[] = $doc;
        $doc = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Texto']]];
        foreach (range(1, 15) as $i) $doc = ['type' => 'blockquote', 'content' => [$doc]];
        $invalid[] = ['type' => 'doc', 'content' => [$doc]];
        $invalid[] = ['type' => 'doc', 'content' => array_fill(0, 1201, ['type' => 'paragraph'])];
        foreach ($invalid as $doc) {
            $this->actingAs($analyst)->postJson('/api/v1/chat/users/'.$manager->id.'/messages', ['body' => 'Texto', 'body_format' => $doc])->assertUnprocessable()->assertJsonValidationErrors('body_format');
        }
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_message_revisions', 0);
    }

    public function test_formatting_survives_multipart_with_an_attachment_and_old_plain_text_stays_literal(): void
    {
        Storage::fake('local');
        [, $analyst, $manager] = $this->team();
        $format = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Legenda', 'marks' => [['type' => 'underline'], ['type' => 'strike']]]]]]];
        $response = $this->actingAs($analyst)->post('/api/v1/chat/users/'.$manager->id.'/messages', [
            'body_format' => json_encode($format), 'attachments' => [UploadedFile::fake()->createWithContent('notas.txt', 'Notas')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message.body', 'Legenda')->assertJsonCount(1, 'message.attachments');
        $id = $response->json('message.id');
        $this->travel(25)->hours();
        $this->patchJson("/api/v1/chat/messages/{$id}", ['body_format' => $format, 'version' => 1])->assertConflict();
        $plain = $this->send($analyst, $manager, '<b>Texto literal</b>');
        $this->getJson('/api/v1/chat/users/'.$manager->id.'/messages')->assertOk()->assertJsonPath('messages.1.body_format', null)->assertJsonPath('messages.1.body', '<b>Texto literal</b>');
    }

    public function test_spaces_between_styled_spans_and_code_indentation_are_preserved(): void
    {
        [, $analyst, $manager] = $this->team();
        $format = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Olá ', 'marks' => [['type' => 'bold']]], ['type' => 'text', 'text' => 'equipe']]],
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => "  codigo\n    recuo\n"]]],
        ]];
        $this->actingAs($analyst)->postJson('/api/v1/chat/users/'.$manager->id.'/messages', ['body_format' => $format])->assertCreated()->assertJsonPath('message.body', "Olá equipe\n  codigo\n    recuo\n")->assertJsonPath('message.body_format.content.0.content.0.text', 'Olá ');
    }

    public function test_images_have_private_inline_preview_and_accessible_description(): void
    {
        Storage::fake('local');
        [$company, $analyst, $manager, $admin, $outside, $project] = $this->team();
        $response = $this->actingAs($analyst)->post('/api/v1/chat/users/'.$manager->id.'/messages', [
            'attachments' => [UploadedFile::fake()->image('paisagem.png', 120, 80)],
            'attachment_descriptions' => ['Árvores junto ao lago.'],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message.attachments.0.alt_text', 'Árvores junto ao lago.');
        $fileId = $response->json('message.attachments.0.id');
        $url = "/api/v1/chat/attachments/{$fileId}/preview";
        $this->assertSame($url, $response->json('message.attachments.0.preview_url'));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($manager)->get($url)->assertOk();
        $this->get("/api/v1/chat/attachments/{$fileId}/download")->assertOk()->assertDownload('paisagem.png');
        foreach ([$outside, $admin] as $actor) $this->actingAs($actor)->getJson($url)->assertNotFound();
        $guest = $this->person($company, 'convidado');
        $this->actingAs($guest)->getJson($url)->assertForbidden();
        $foreign = $this->person(Company::create(['legal_name' => 'Externa']), 'gestor-administrador');
        $this->actingAs($foreign)->getJson($url)->assertNotFound();
        $project->members()->where('user_id', $analyst->id)->update(['active' => false]);
        $this->actingAs($analyst)->getJson($url)->assertForbidden();
        Storage::disk('local')->assertExists(ChatAttachment::findOrFail($fileId)->path);
    }

    public function test_invalid_images_and_oversized_descriptions_are_rejected(): void
    {
        Storage::fake('local');
        [, $analyst, $manager] = $this->team();
        $url = '/api/v1/chat/users/'.$manager->id.'/messages';
        $headers = ['Accept' => 'application/json'];
        $this->actingAs($analyst)->post($url, ['attachments' => [UploadedFile::fake()->createWithContent('falsa.png', 'nao e imagem')]], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->post($url, ['attachments' => [UploadedFile::fake()->image('grande.png', 8193, 1)]], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->post($url, ['attachments' => [UploadedFile::fake()->image('foto.jpg')], 'attachment_descriptions' => [str_repeat('a', 501)]], $headers)->assertUnprocessable()->assertJsonValidationErrors('attachment_descriptions.0');
        $this->post($url, ['attachments' => ['arquivo invalido']], $headers)->assertUnprocessable();
        $this->assertDatabaseCount('chat_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $response = $this->post($url, ['attachments' => [UploadedFile::fake()->createWithContent('notas.txt', 'texto')]], $headers)->assertCreated()->assertJsonPath('message.attachments.0.preview_url', null);
        $this->getJson('/api/v1/chat/attachments/'.$response->json('message.attachments.0.id').'/preview')->assertNotFound();
    }

}
