<?php

namespace App\Domain\Projects;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProjectFields
{
    public const LABELS = ['name' => 'Nome do projeto', 'proposal_number' => 'Número da proposta', 'project_status_id' => 'Status', 'project_situation_id' => 'Situação', 'proposal_date' => 'Data da proposta', 'client_id' => 'Cliente', 'account_manager_id' => 'Gerente de contas', 'project_manager_id' => 'Gerente do projeto', 'start_date' => 'Data de início', 'end_date' => 'Data de término', 'billing_date' => 'Data de faturamento', 'contract_value' => 'Valor do projeto', 'taxes' => 'Impostos', 'commission_rate' => 'Comissão', 'estimated_labor_cost' => 'Custo estimado do analista', 'estimated_additional_cost' => 'Custo adicional estimado', 'estimated_minutes' => 'Tempo estimado'];

    public static function allowed(User $user, string $field, string $operation): bool
    {
        if ($user->profiles()->wherePivot('company_id', $user->current_company_id)->whereIn('slug', ['administrador', 'gestor-administrador'])->exists()) {
            return true;
        }
        $override = DB::table('user_permission_overrides')->join('permissions', 'permissions.id', '=', 'user_permission_overrides.permission_id')->where('user_id', $user->id)->where('permissions.slug', "projects.fields.$field.$operation")->value('allowed');

        return $override === null || (bool) $override;
    }

    public static function access(User $user): array
    {
        $admin = $user->profiles()->wherePivot('company_id', $user->current_company_id)->whereIn('slug', ['administrador', 'gestor-administrador'])->exists();
        $overrides = DB::table('user_permission_overrides')->join('permissions', 'permissions.id', '=', 'user_permission_overrides.permission_id')->where('user_id', $user->id)->where('permissions.slug', 'like', 'projects.fields.%')->pluck('allowed', 'slug');
        $out = [];
        foreach (self::LABELS as $field => $label) {
            foreach (['view', 'edit'] as $operation) {
                $out[$field][$operation] = $admin || ! $overrides->has("projects.fields.$field.$operation") || (bool) $overrides["projects.fields.$field.$operation"];
            }
        }

        return $out;
    }
}
