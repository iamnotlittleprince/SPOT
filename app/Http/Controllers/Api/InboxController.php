<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['filter' => ['nullable', Rule::in(['all', 'unread', 'tasks', 'documents', 'mentions', 'trash'])], 'search' => ['nullable', 'string', 'max:100']]);
        $trash = ($filters['filter'] ?? 'all') === 'trash';
        $query = InboxItem::query()->with('project:id,name')->where('user_id', $request->user()->id)
            ->when($trash, fn ($items) => $items->whereNotNull('archived_at')->where('archived_at', '>=', now()->subDays(30)), fn ($items) => $items->whereNull('archived_at'));
        if (($filters['filter'] ?? 'all') === 'unread') $query->whereNull('read_at');
        if (($filters['filter'] ?? 'all') === 'tasks') $query->where('type', 'task');
        if (($filters['filter'] ?? 'all') === 'documents') $query->where('type', 'document');
        if (($filters['filter'] ?? 'all') === 'mentions') $query->where('type', 'mention');
        if ($search = mb_strtolower(trim($filters['search'] ?? ''))) $query->where(fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', ["%{$search}%"])->orWhereRaw('LOWER(body) LIKE ?', ["%{$search}%"]));

        return response()->json([
            'items' => $query->latest()->limit(100)->get(),
            'unread_count' => InboxItem::where('user_id', $request->user()->id)->whereNull('archived_at')->whereNull('read_at')->count(),
            'trash_count' => InboxItem::where('user_id', $request->user()->id)->whereNotNull('archived_at')->where('archived_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    public function read(Request $request, InboxItem $inboxItem): JsonResponse
    {
        abort_unless($inboxItem->user_id === $request->user()->id, 404);
        $inboxItem->update(['read_at' => $inboxItem->read_at ?: now()]);
        return response()->json(['item' => $inboxItem->fresh()->load('project:id,name')]);
    }

    public function readAll(Request $request): JsonResponse
    {
        InboxItem::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'Todas as mensagens foram marcadas como lidas.']);
    }

    public function archive(Request $request, InboxItem $inboxItem): JsonResponse
    {
        abort_unless($inboxItem->user_id === $request->user()->id, 404);
        $inboxItem->update(['archived_at' => now()]);
        return response()->json(['message' => 'Mensagem arquivada.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $count = InboxItem::where('user_id', $request->user()->id)->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        return response()->json(['message' => 'Caixa de entrada limpa. As mensagens ficarão na lixeira por até 30 dias.', 'archived_count' => $count]);
    }

    public function restore(Request $request, InboxItem $inboxItem): JsonResponse
    {
        abort_unless($inboxItem->user_id === $request->user()->id && $inboxItem->archived_at, 404);
        $inboxItem->update(['archived_at' => null]);

        return response()->json(['message' => 'Mensagem restaurada.', 'item' => $inboxItem->fresh()->load('project:id,name')]);
    }
}
