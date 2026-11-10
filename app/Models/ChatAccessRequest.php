<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAccessRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(ChatConversation::class, 'conversation_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function assignedReviewer(): BelongsTo { return $this->belongsTo(User::class, 'assigned_reviewer_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
