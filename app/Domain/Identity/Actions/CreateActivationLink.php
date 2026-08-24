<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Models\User;
use App\Models\UserActivationToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateActivationLink
{
    public function __construct(private AuditRecorder $audit) {}

    /** @return array{record:UserActivationToken, token:string} */
    public function execute(User $user, User $administrator): array
    {
        return DB::transaction(function () use ($user, $administrator): array {
            UserActivationToken::where('user_id', $user->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $token = Str::random(64);
            $record = UserActivationToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'created_by' => $administrator->id, 'expires_at' => now()->addHours(48)]);
            $this->audit->record('user.activation_created', $user, $administrator, [], ['expires_at' => $record->expires_at]);
            return compact('record', 'token');
        });
    }
}
