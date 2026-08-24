<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivationToken extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function isUsable(): bool { return ! $this->used_at && ! $this->revoked_at && $this->expires_at->isFuture(); }
}
