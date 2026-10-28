<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationMailBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
