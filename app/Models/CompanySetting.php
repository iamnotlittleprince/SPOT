<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $attributes = ['audit_retention_months' => 12];

    protected $guarded = ['id'];

    protected $hidden = ['aad_client_secret'];

    protected function casts(): array
    {
        return ['aad_client_secret' => 'encrypted', 'audit_retention_months' => 'integer'];
    }
}
