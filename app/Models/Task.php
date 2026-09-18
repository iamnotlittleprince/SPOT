<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $hidden = ['cost_rate_snapshot', 'sale_rate_snapshot'];

    protected $fillable = ['project_id', 'user_id', 'title', 'status', 'priority', 'due_date', 'position', 'worked_on', 'duration_minutes', 'activity_type_id', 'description', 'is_overtime', 'created_by', 'cost_rate_snapshot', 'sale_rate_snapshot'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'position' => 'integer', 'worked_on' => 'date', 'duration_minutes' => 'integer', 'is_overtime' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
