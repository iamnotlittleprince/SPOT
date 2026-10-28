<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_http_errors_have_branded_pages_and_keep_status_and_headers(): void
    {
        foreach ([400, 401, 402, 403, 404, 405, 408, 409, 410, 413, 415, 418, 419, 422, 429, 500, 502, 503, 504, 507] as $status) {
            Route::get('/__error_test/'.$status, fn () => abort($status, 'SECRET exception details', ['Retry-After' => '60']));
            $this->get('/__error_test/'.$status)
                ->assertStatus($status)
                ->assertSee('spot-error-card', false)
                ->assertSee('Erro '.$status)
                ->assertDontSee('SECRET exception details')
                ->assertHeader('Retry-After', '60');
        }
    }

    public function test_existing_application_entry_points_remain_available(): void
    {
        foreach (['/', '/?home', '/?page=calendar', '/first-access', '/first-access/token', '/accept-invitation/token', '/estoque', '/forgot-password', '/reset-password/token'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="root"', false);
        }
    }

    public function test_unknown_web_address_is_a_real_404(): void
    {
        $this->get('/this-page-does-not-exist')->assertNotFound()->assertSee('Página ou item não encontrado');
    }

    public function test_json_errors_keep_the_api_contract_and_hide_server_details(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/__error_test/500', fn () => throw new \RuntimeException('SECRET database credentials'));
        $this->getJson('/api/__error_test/500')->assertStatus(500)
            ->assertJsonStructure(['message'])->assertDontSee('SECRET')->assertDontSee('trace')->assertDontSee('<html', false);
        Route::get('/__error_test/json', fn () => abort(403, 'Permission denied'));
        $this->getJson('/__error_test/json')->assertForbidden()->assertJsonPath('message', 'Permission denied');
    }

    public function test_validation_errors_keep_field_messages(): void
    {
        Route::post('/api/__error_test/validation', fn () => request()->validate(['name' => 'required']));
        $this->postJson('/api/__error_test/validation', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_write_failure_has_no_link_that_repeats_the_write(): void
    {
        Route::post('/__error_test/write', fn () => abort(500));
        $this->post('/__error_test/write')->assertStatus(500)->assertDontSee('Tentar abrir novamente');
    }
}
