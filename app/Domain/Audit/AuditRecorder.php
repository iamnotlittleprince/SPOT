<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditRecorder
{
    public function record(string $action, Model $subject, User $actor, array $oldValues = [], array $newValues = []): AuditLog
    {
        $request = app(Request::class);

        return AuditLog::create([
            'company_id' => $actor->current_company_id,
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->attributes->get('request_id', (string) Str::ulid()),
            'created_at' => now(),
        ]);
    }
}
