<?php

namespace App\Domain\Chat;

use App\Models\{ChatAccessRequest, ChatConversation, ChatMessage, User};
use Illuminate\Support\Facades\DB;

class ChatAccess
{
    private function roles(User $user): \Illuminate\Support\Collection
    {
        $user->loadMissing('profiles');
        return $user->profiles->filter(fn ($profile) => (int) $profile->pivot->company_id === (int) $user->current_company_id)->pluck('slug');
    }

    public function eligible(User $user): bool
    {
        $roles = $this->roles($user);
        return $user->active && $user->account_status === 'active' && $user->current_company_id
            && ! $roles->contains('convidado')
            && $roles->intersect(['analista', 'gestor', 'gestor-administrador', 'administrador'])->isNotEmpty();
    }

    public function canReview(User $user): bool
    {
        return $this->eligible($user) && $this->roles($user)->intersect(['administrador', 'gestor-administrador'])->isNotEmpty();
    }

    public function teamPeerIds(User $user): array
    {
        return DB::table('project_members as peers')
            ->join('projects', 'projects.id', '=', 'peers.project_id')
            ->join('project_members as own', 'own.project_id', '=', 'projects.id')
            ->where('projects.company_id', $user->current_company_id)->whereNull('projects.deleted_at')
            ->where('own.user_id', $user->id)->where('own.active', true)->whereNull('own.access_revoked_at')->whereIn('own.role', ['manager', 'analyst'])
            ->where('peers.active', true)->whereNull('peers.access_revoked_at')->whereIn('peers.role', ['manager', 'analyst'])
            ->distinct()->pluck('peers.user_id')->all();
    }

    public function sameTeam(User $user, User $peer): bool
    {
        return in_array($peer->id, $this->teamPeerIds($user), true);
    }

    public function pairEligible(User $user, User $peer): bool
    {
        return $user->id !== $peer->id && $user->current_company_id === $peer->current_company_id
            && $this->eligible($user) && $this->eligible($peer);
    }

    public function direct(User $user, User $peer): bool
    {
        return $this->pairEligible($user, $peer) && ($this->canReview($user) || $this->sameTeam($user, $peer));
    }

    public function conversation(User $user, User $peer, bool $create = false): ?ChatConversation
    {
        $pair = ['company_id' => $user->current_company_id, 'first_user_id' => min($user->id, $peer->id), 'second_user_id' => max($user->id, $peer->id)];
        return $create ? ChatConversation::firstOrCreate($pair) : ChatConversation::where($pair)->first();
    }

    public function allowed(User $user, User $peer): bool
    {
        if (! $this->pairEligible($user, $peer)) return false;
        if ($this->direct($user, $peer)) return true;
        $conversation = $this->conversation($user, $peer);
        if ($conversation && $this->canReview($peer) && ChatMessage::where('conversation_id', $conversation->id)->where('sender_id', $peer->id)->exists()) return true;
        return $conversation && ChatAccessRequest::where('conversation_id', $conversation->id)->where('status', 'approved')->whereNull('revoked_at')
            ->with('reviewer.profiles')->get()->contains(fn ($entry) => $entry->reviewer
                && $entry->reviewer->current_company_id === $user->current_company_id && $this->canReview($entry->reviewer));
    }
}
