<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Models\Project;

class ParameterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('parameters.view');
        $companyId = $request->user()->current_company_id;
        $parameters = fn (string $table) => DB::table($table)->where('company_id', $companyId)->where('active', true)->orderBy('name')->get();

        $clients = DB::table('clients')->where('company_id', $companyId)->where('active', true)->whereNull('deleted_at');
        if (! $request->user()->can('projects.view_all')) {
            $clients->whereIn('id', Project::query()
                ->where('company_id', $companyId)
                ->whereHas('members', fn ($members) => $members->where('user_id', $request->user()->id)
                    ->where(fn ($access) => $access->where('active', true)->orWhereNotNull('projects.finalized_at')))
                ->select('client_id'));
        }

        return response()->json([
            'field_access' => \App\Domain\Projects\ProjectFields::access($request->user()),
            'project_statuses' => $parameters('project_statuses'),
            'project_situations' => $parameters('project_situations'),
            'tax_types' => $parameters('tax_types'),
            'activity_types' => $parameters('activity_types'),
            'expense_types' => $parameters('expense_types'),
            'users' => DB::table('users')->where('current_company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'clients' => $clients->orderBy('name')->get(),
        ]);
    }
}
