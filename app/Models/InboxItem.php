<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxItem extends Model
{
    protected $fillable = ['user_id', 'project_id', 'type', 'title', 'body', 'actor_name', 'data', 'read_at', 'archived_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
