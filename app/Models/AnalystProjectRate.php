<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalystProjectRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'normal_cost_rate' => 'decimal:2', 'overtime_cost_rate' => 'decimal:2',
            'overtime_sale_rate' => 'decimal:2', 'effective_from' => 'date', 'effective_until' => 'date',
        ];
    }
}
