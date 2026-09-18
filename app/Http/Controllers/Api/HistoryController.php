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
        $request->validate(['before'=>['nullable','string','max:26']]);
        $query = AuditLog::query()->with('user:id,name')->orderByDesc('id')->when($request->input('before'),fn($q,$id)=>$q->where('id','<',$id));

        if ($user->can('audit.view')) {
            $query->where('company_id', $user->current_company_id);
        } else {
            $query->where('company_id', $user->current_company_id)->where('user_id', $user->id);
        }

        $fields=['id','company_id','user_id','action','auditable_type','auditable_id','ip_address','created_at'];
        if ($user->can('security.manage')) $fields=[...$fields,'old_values','new_values','request_id'];
        $events=$query->limit(101)->get($fields);
        $page=$events->take(100)->values();
        return response()->json(['events'=>$page,'next'=>$events->count()>100?$page->last()->id:null]);
    }
}
