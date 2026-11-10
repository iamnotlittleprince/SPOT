<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversation extends Model
{
    protected $guarded = ['id'];

    public function firstUser(): BelongsTo { return $this->belongsTo(User::class, 'first_user_id'); }
    public function secondUser(): BelongsTo { return $this->belongsTo(User::class, 'second_user_id'); }
}
