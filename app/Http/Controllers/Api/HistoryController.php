<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AuditLog::query()->with('user:id,name')->latest('created_at');

        if ($user->can('security.manage')) {
            $query->where('company_id', $user->current_company_id);
        } else {
            $query->where('user_id', $user->id);
        }

        return response()->json(['events' => $query->limit(100)->get([
            'id', 'company_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'ip_address', 'created_at',
        ])]);
    }
}
