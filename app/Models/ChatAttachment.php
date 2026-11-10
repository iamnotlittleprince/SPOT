<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAttachment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['path'];

    protected function casts(): array { return ['size' => 'integer']; }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Anexos de mensagens não podem ser excluídos.'));
    }
}
