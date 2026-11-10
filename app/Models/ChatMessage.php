<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ChatMessage extends Model
{
    protected $guarded = ['id'];

    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function attachments(): HasMany { return $this->hasMany(ChatAttachment::class, 'message_id'); }

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'body_format' => 'array'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Mensagens de chat não podem ser excluídas.'));
    }
}
