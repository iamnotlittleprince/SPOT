<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'finalized_at', 'finalized_by', 'deleted_at', 'deleted_by', 'deletion_reason'];

    protected function casts(): array
    {
        return [
            'progress' => 'integer', 'due_date' => 'date', 'proposal_date' => 'date',
            'start_date' => 'date', 'end_date' => 'date', 'billing_date' => 'date',
            'contract_value' => 'decimal:2', 'commission_rate' => 'decimal:4',
            'estimated_labor_cost' => 'decimal:2', 'estimated_additional_cost' => 'decimal:2',
            'estimated_minutes' => 'integer', 'finalized_at' => 'datetime', 'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function projectManager(): BelongsTo { return $this->belongsTo(User::class, 'project_manager_id'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function members(): HasMany { return $this->hasMany(ProjectMember::class); }
    public function workLogs(): HasMany { return $this->hasMany(WorkLog::class); }
    public function expenses(): HasMany { return $this->hasMany(ProjectExpense::class); }
    public function taxes(): HasMany { return $this->hasMany(ProjectTax::class); }

    public function isFinalized(): bool { return $this->finalized_at !== null; }
}
