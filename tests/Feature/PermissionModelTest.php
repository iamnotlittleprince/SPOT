<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_permissions_and_individual_denials_are_enforced(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id]);
        $profile = Profile::create(['name' => 'Gestor', 'slug' => 'gestor']);
        $permission = Permission::create([
            'name' => 'Visualizar financeiro', 'slug' => 'financial.view', 'module' => 'Financeiro',
        ]);
        $profile->permissions()->attach($permission);
        $user->profiles()->attach($profile, ['company_id' => $company->id]);

        $this->assertTrue(Gate::forUser($user)->allows('financial.view'));

        $user->belongsToMany(Permission::class, 'user_permission_overrides')
            ->attach($permission, ['allowed' => false, 'reason' => 'Restrição individual']);

        $this->assertFalse(Gate::forUser($user->fresh())->allows('financial.view'));
    }

    public function test_inactive_user_is_denied_even_when_profile_has_permission(): void
    {
        $company = Company::create(['legal_name' => 'Computécnica']);
        $user = User::factory()->create(['current_company_id' => $company->id, 'active' => false]);

        $this->assertFalse(Gate::forUser($user)->allows('projects.view_all'));
    }
}
