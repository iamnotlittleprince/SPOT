<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactor
{
    public static function verify(User $user, ?string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $current->two_factor_confirmed_at) return true;
            if (! $code) return false;
            $code = trim($code);
            if (preg_match('/^[0-9]{6}$/', $code) && (new Google2FA())->verifyKey($current->two_factor_secret, $code)) return true;
            $codes = $current->two_factor_recovery_codes ?? [];
            $index = array_search(hash('sha256', strtoupper($code)), $codes, true);
            if ($index === false) return false;
            unset($codes[$index]);
            $current->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
            return true;
        });
    }
}
