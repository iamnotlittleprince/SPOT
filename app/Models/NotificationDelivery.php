<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'in_app' => 'boolean', 'email' => 'boolean', 'available_at' => 'datetime',
            'in_app_delivered_at' => 'datetime', 'email_processed_at' => 'datetime'];
    }
}
