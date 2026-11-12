<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ErrorMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_framework_validation_rules_have_messages_in_each_language(): void
    {
        $framework = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        foreach (['pt_BR', 'en', 'es'] as $locale) {
            $catalog = require lang_path($locale.'/validation.php');
            foreach ($framework as $rule => $value) {
                if (in_array($rule, ['custom', 'attributes'], true)) continue;
                $this->assertArrayHasKey($rule, $catalog, "$locale: $rule");
                if (is_array($value)) foreach (array_keys($value) as $type) $this->assertNotEmpty($catalog[$rule][$type], "$locale: $rule.$type");
            }
        }
    }

    public function test_validation_returns_translated_summary_and_field_errors(): void
    {
        Route::post('/api/__errors/validate', fn () => request()->validate([
            'proposal_number' => 'required', 'end_date' => 'after_or_equal:start_date',
            'email' => 'email', 'file' => 'file', 'duration_minutes' => 'integer|min:0',
        ]));
        foreach (['pt' => 'Preencha número da proposta para continuar.', 'en' => 'Fill in proposal number to continue.', 'es' => 'Complete número de propuesta para continuar.'] as $locale => $expected) {
            $response = $this->withHeader('X-Spot-Locale', $locale)->postJson('/api/__errors/validate', [
                'start_date' => '2026-11-11', 'end_date' => '2026-11-10', 'email' => 'invalid', 'file' => 'invalid', 'duration_minutes' => -1,
            ])->assertUnprocessable()->assertJsonValidationErrors(['proposal_number', 'end_date', 'email', 'file', 'duration_minutes']);
            $response->assertJsonPath('errors.proposal_number.0', $expected)->assertDontSee('validation.');
            $this->assertStringNotContainsString('start_date', $response->json('errors.end_date.0'));
        }
    }

    public function test_archived_proposal_number_has_an_actionable_error_without_exposing_project_names(): void
    {
        $this->seed();
        $user = User::where('email', 'admin@computecnica.com.br')->firstOrFail();
        $project = Project::create(['user_id' => $user->id, 'company_id' => $user->current_company_id, 'name' => 'Private archived project', 'proposal_number' => '01']);
        $project->delete();
        $response = $this->actingAs($user)->putJson('/api/v1/projects/'.Project::create(['user_id' => $user->id, 'company_id' => $user->current_company_id, 'name' => 'Current project'])->id, ['proposal_number' => '01']);
        $response->assertUnprocessable()->assertJsonPath('errors.proposal_number.0', 'Este número de proposta já está em uso, inclusive em projetos arquivados. Informe outro número.')->assertDontSee('Private archived project');
    }

    public function test_nested_file_size_and_password_errors_explain_the_constraints(): void
    {
        app()->setLocale('pt_BR');
        $validator = Validator::make(['taxes' => [['rate' => 101]], 'password' => 'short', 'description' => str_repeat('a', 151)], [
            'taxes.*.rate' => 'numeric|between:0,100', 'password' => 'min:12', 'description' => 'max:150',
        ]);
        $this->assertSame('Informe em alíquota do imposto um valor entre 0 e 100.', $validator->errors()->first('taxes.0.rate'));
        $this->assertSame('Use pelo menos 12 caracteres em senha.', $validator->errors()->first('password'));
        $this->assertSame('Use no máximo 150 caracteres em descrição.', $validator->errors()->first('description'));
    }

    public function test_framework_http_errors_are_clear_and_domain_messages_are_preserved(): void
    {
        Route::get('/api/__errors/default', fn () => abort(403));
        Route::get('/api/__errors/domain', fn () => abort(409, 'Projeto finalizado não pode ser alterado.'));
        $this->getJson('/api/__errors/default')->assertForbidden()->assertJsonPath('message', 'Sua conta não tem a permissão necessária. Se precisar deste acesso, fale com a administração.');
        $this->getJson('/api/__errors/domain')->assertConflict()->assertJsonPath('message', 'Projeto finalizado não pode ser alterado.');
        $this->withHeader('X-Spot-Locale', 'en')->getJson('/api/__errors/default')->assertForbidden()->assertDontSee('Sua conta');
    }
}
