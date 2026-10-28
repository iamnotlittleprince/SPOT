<?php

namespace App\Domain\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProjectManagers
{
    public const ROLES = ['administrador', 'gestor-administrador', 'gestor'];

    public static function query(int|string|null $companyId): Builder
    {
        return User::query()
            ->where('current_company_id', $companyId)
            ->where('active', true)
            ->where('account_status', 'active')
            ->whereHas('profiles', fn (Builder $profiles) => $profiles
                ->where('company_user_profiles.company_id', $companyId)
                ->whereIn('slug', self::ROLES));
    }
}
