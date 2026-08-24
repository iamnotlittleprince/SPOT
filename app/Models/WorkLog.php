<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'worked_on' => 'date', 'duration_minutes' => 'integer', 'is_overtime' => 'boolean',
            'cost_rate_snapshot' => 'decimal:2', 'sale_rate_snapshot' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function analyst(): BelongsTo { return $this->belongsTo(User::class, 'analyst_id'); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }

    public function costAmount(): float
    {
        return round(($this->duration_minutes / 60) * (float) $this->cost_rate_snapshot, 2);
    }

    public function billableAmount(): float
    {
        return round(($this->duration_minutes / 60) * (float) $this->sale_rate_snapshot, 2);
    }
}
