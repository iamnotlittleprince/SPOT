<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTax extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'fixed_amount' => 'decimal:2', 'calculated_amount' => 'decimal:2'];
    }

    public function type(): BelongsTo { return $this->belongsTo(TaxType::class, 'tax_type_id'); }
}
