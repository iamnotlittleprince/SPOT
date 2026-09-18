<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Profile;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpotRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_exposes_role_from_current_company_instead_of_job_title(): void
    {
        $this->seed();
        $company = Company::firstOrFail();
        foreach (['administrador' => 'Administrador', 'gestor-administrador' => 'Administrador', 'gestor' => 'Gestor', 'analista' => 'Analista', 'convidado' => 'Convidado'] as $slug => $label) {
            $user = User::factory()->create(['current_company_id' => $company->id, 'job_title' => 'Cargo diferente']);
            $user->profiles()->attach(Profile::where('slug', $slug)->value('id'), ['company_id' => $company->id]);
            $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('spot_role', $label);
            $other = Company::create(['legal_name' => 'Outra empresa']);
            $user->profiles()->attach(Profile::where('slug', 'administrador')->value('id'), ['company_id' => $other->id]);
            $this->assertSame($label, $user->spotRoleLabel());
        }
    }
}
