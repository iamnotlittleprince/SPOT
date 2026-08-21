<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['project_id', 'user_id', 'title', 'status', 'priority', 'due_date', 'position'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'position' => 'integer'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
