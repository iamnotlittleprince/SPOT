<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ParameterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('parameters.view');
        $companyId = $request->user()->current_company_id;
        $parameters = fn (string $table) => DB::table($table)->where('company_id', $companyId)->where('active', true)->orderBy('name')->get();

        return response()->json([
            'field_access' => \App\Domain\Projects\ProjectFields::access($request->user()),
            'project_statuses' => $parameters('project_statuses'),
            'project_situations' => $parameters('project_situations'),
            'tax_types' => $parameters('tax_types'),
            'activity_types' => $parameters('activity_types'),
            'expense_types' => $parameters('expense_types'),
            'users' => DB::table('users')->where('current_company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'clients' => DB::table('clients')->where('company_id', $companyId)->where('active', true)->whereNull('deleted_at')->orderBy('name')->get(),
        ]);
    }
}
