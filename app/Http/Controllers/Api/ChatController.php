<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chat\{ChatAccess, ChatFormatting};
use App\Http\Controllers\Controller;
use App\Models\{ChatAccessRequest, ChatAttachment, ChatConversation, ChatMessage, InboxItem, User};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Cache, DB, Http, Storage};
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private ChatAccess $access) {}

    private function actor(Request $request): User
    {
        $user = $request->user();
        $user->unsetRelation('profiles');
        abort_unless($this->access->eligible($user), 403, 'Seu perfil não tem acesso ao chat.');
        return $user;
    }

    private function peer(User $actor, int $id): User
    {
        $peer = User::with('profiles')->findOrFail($id);
        abort_unless($this->access->pairEligible($actor, $peer), 404, 'Pessoa indisponível para chat.');
        return $peer;
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $users = User::with('profiles')->where('current_company_id', $actor->current_company_id)
            ->where('active', true)->where('account_status', 'active')->where('id', '!=', $actor->id)
            ->when(trim($data['search'] ?? '') !== '', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($data['search'])).'%']))
            ->orderBy('name')->get();
        $conversations = ChatConversation::where('company_id', $actor->current_company_id)
            ->where(fn ($q) => $q->where('first_user_id', $actor->id)->orWhere('second_user_id', $actor->id))->get()
            ->keyBy(fn ($conversation) => $conversation->first_user_id === $actor->id ? $conversation->second_user_id : $conversation->first_user_id);
        $accessEntries = ChatAccessRequest::with('reviewer.profiles')->whereIn('conversation_id', $conversations->pluck('id'))
            ->whereIn('status', ['pending', 'approved'])->whereNull('revoked_at')->get()->groupBy('conversation_id');
        $teamPeerIds = $this->access->teamPeerIds($actor);
        $canStartWithoutApproval = $this->access->canReview($actor);
        $peerMessageConversations = ChatMessage::whereIn('conversation_id', $conversations->pluck('id'))->where('sender_id', '!=', $actor->id)->distinct()->pluck('conversation_id')->flip();
        $contacts = $users->filter(fn ($peer) => $this->access->eligible($peer))->map(function ($peer) use ($actor, $conversations, $accessEntries, $teamPeerIds, $canStartWithoutApproval, $peerMessageConversations) {
            $conversation = $conversations->get($peer->id);
            $entries = $conversation ? $accessEntries->get($conversation->id, collect()) : collect();
            $sameTeam = in_array($peer->id, $teamPeerIds, true);
            $approved = $entries->contains(fn ($entry) => $entry->status === 'approved' && $entry->reviewer
                && $entry->reviewer->current_company_id === $actor->current_company_id && $this->access->canReview($entry->reviewer));
            $administrativeAccess = $canStartWithoutApproval || ($conversation && $this->access->canReview($peer) && $peerMessageConversations->has($conversation->id));
            return ['id' => $peer->id, 'name' => $peer->name, 'avatar_url' => $peer->avatar_url,
                'can_chat' => $sameTeam || $approved || $administrativeAccess, 'administrative_access' => $administrativeAccess, 'same_team' => $sameTeam, 'pending' => $entries->contains('status', 'pending')];
        })->values();
        $canReview = $this->access->canReview($actor);
        $reviewers = User::with('profiles')->where('current_company_id', $actor->current_company_id)->get()
            ->filter(fn ($person) => $person->id !== $actor->id && $this->access->canReview($person))->values();
        $entries = ChatAccessRequest::with(['requester:id,name', 'reviewer:id,name', 'assignedReviewer:id,name', 'conversation.firstUser.profiles', 'conversation.secondUser.profiles'])
            ->whereHas('conversation', fn ($q) => $q->where('company_id', $actor->current_company_id)
                ->when(! $canReview, fn ($q) => $q->where(fn ($q) => $q->where('first_user_id', $actor->id)->orWhere('second_user_id', $actor->id))))
            ->where(function ($q) use ($actor, $canReview) {
                $q->whereHas('conversation', fn ($pair) => $pair->where('first_user_id', $actor->id)->orWhere('second_user_id', $actor->id));
                if ($canReview) $q->orWhere('assigned_reviewer_id', $actor->id)->orWhereNull('assigned_reviewer_id');
            })
            ->latest('id')->get()->map(function ($entry) use ($actor, $canReview) {
                $conversation = $entry->conversation;
                $eligible = $this->access->pairEligible($conversation->firstUser, $conversation->secondUser);
                return ['id' => $entry->id, 'requester_id' => $entry->requester_id, 'requester_name' => $entry->requester?->name,
                    'first_name' => $conversation->firstUser->name, 'second_name' => $conversation->secondUser->name,
                    'status' => $entry->status, 'reason' => $entry->reason, 'decision_note' => $entry->decision_note,
                    'assigned_reviewer_id' => $entry->assigned_reviewer_id, 'assigned_reviewer_name' => $entry->assignedReviewer?->name,
                    'reviewer_name' => $entry->reviewer?->name, 'created_at' => $entry->created_at->toIso8601String(),
                    'can_decide' => $canReview && (! $entry->assigned_reviewer_id || $entry->assigned_reviewer_id === $actor->id) && $eligible && $entry->requester_id !== $actor->id && $entry->status === 'pending',
                    'can_revoke' => $canReview && $entry->status === 'approved'];
            });
        return response()->json(['contacts' => $contacts, 'requests' => $entries, 'can_review' => $canReview, 'can_start_without_approval' => $canStartWithoutApproval,
            'reviewers_available' => $reviewers->isNotEmpty(), 'reviewers' => $reviewers->map->only(['id', 'name'])->all(), 'translation_available' => filled(config('services.chat_translation.url'))]);
    }

    public function messages(Request $request, int $user): JsonResponse
    {
        $actor = $this->actor($request);
        $peer = $this->peer($actor, $user);
        abort_unless($this->access->allowed($actor, $peer), 403, 'Solicite autorização para conversar com esta pessoa.');
        $data = $request->validate(['before' => ['nullable', 'integer', 'min:1']]);
        $conversation = $this->access->conversation($actor, $peer);
        $messages = $conversation ? ChatMessage::with(['sender:id,name', 'attachments'])->where('conversation_id', $conversation->id)
            ->when(isset($data['before']), fn ($q) => $q->where('id', '<', $data['before']))->orderByDesc('id')->limit(51)->get() : collect();
        return response()->json(['messages' => $messages->take(50)->reverse()->values()->map(fn ($message) => $this->present($message, $actor)),
            'has_more' => $messages->count() > 50]);
    }

    public function send(Request $request, int $user): JsonResponse
    {
        $actor = $this->actor($request);
        $peer = $this->peer($actor, $user);
        abort_unless($this->access->allowed($actor, $peer), 403, 'Solicite autorização para conversar com esta pessoa.');
        $format = $this->prepareFormat($request);
        $data = $request->validate([
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachment_descriptions' => ['nullable', 'array', 'max:3'],
            'attachment_descriptions.*' => ['nullable', 'string', 'max:500'],
            'attachments.*' => ['bail', 'required', 'file', 'max:2048', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp,zip', 'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,webp,zip', function ($attribute, $file, $fail) {
                if (in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) && ! $this->validImage($file->getRealPath())) {
                    $fail('Use uma imagem JPG, PNG ou WebP válida, com até 8192 × 8192 pixels.');
                }
            }],
        ], $this->validationMessages());
        $stored = [];
        try {
            $message = DB::transaction(function () use ($actor, $peer, $data, $format, &$stored) {
                $conversation = $this->access->conversation($actor, $peer, true);
                ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->access->allowed($actor, $peer), 403);
                $message = ChatMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $actor->id, 'body' => $data['body'] ?? '', 'body_format' => $format]);
                foreach ($data['attachments'] ?? [] as $index => $file) {
                    $path = $file->store('chat/'.$conversation->id, 'local');
                    if (! $path) throw new \RuntimeException('Não foi possível armazenar o anexo.');
                    $stored[] = $path;
                    $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'arquivo', 0, 200);
                    $message->attachments()->create(['name' => $name, 'path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
                        'alt_text' => in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) ? ($data['attachment_descriptions'][$index] ?? null) : null]);
                }
                DB::table('chat_message_revisions')->insert(['message_id' => $message->id, 'version' => 1, 'body' => $message->body, 'body_format' => $format ? json_encode($format) : null, 'created_at' => now()]);
                InboxItem::create(['user_id' => $peer->id, 'type' => 'update', 'title' => 'Nova mensagem de chat',
                    'body' => 'Abra o chat para ler e responder.', 'actor_name' => $actor->name, 'data' => ['chat_user_id' => $actor->id]]);
                return $message->fresh();
            });
        } catch (\Throwable $failure) {
            Storage::disk('local')->delete($stored);
            throw $failure;
        }
        return response()->json(['message' => $this->present($message, $actor)], 201);
    }

    public function download(Request $request, ChatAttachment $attachment): StreamedResponse
    {
        $message = ChatMessage::findOrFail($attachment->message_id);
        $this->messageActor($request, $message);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->name, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validImage(string $path): bool
    {
        $info = @getimagesize($path);
        return $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            && $info[0] > 0 && $info[1] > 0 && $info[0] <= 8192 && $info[1] <= 8192;
    }

    public function preview(Request $request, ChatAttachment $attachment): StreamedResponse
    {
        $this->messageActor($request, ChatMessage::findOrFail($attachment->message_id));
        abort_unless(in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path) && $this->validImage($disk->path($attachment->path)), 404);
        return $disk->response($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function messageActor(Request $request, ChatMessage $message): User
    {
        $actor = $this->actor($request);
        $conversation = ChatConversation::findOrFail($message->conversation_id);
        abort_unless($conversation->company_id === $actor->current_company_id
            && in_array($actor->id, [$conversation->first_user_id, $conversation->second_user_id], true), 404);
        $peer = $this->peer($actor, $conversation->first_user_id === $actor->id ? $conversation->second_user_id : $conversation->first_user_id);
        abort_unless($this->access->allowed($actor, $peer), 403, 'O acesso a esta conversa não está disponível.');
        return $actor;
    }

    public function edit(Request $request, ChatMessage $message): JsonResponse
    {
        $actor = $this->messageActor($request, $message);
        abort_unless($message->sender_id === $actor->id, 403, 'Somente o autor pode editar a mensagem.');
        $format = $this->prepareFormat($request);
        $data = $request->validate(['body' => [$message->attachments()->exists() ? 'nullable' : 'required', 'string', 'max:5000'], 'version' => ['required', 'integer', 'min:1']], $this->validationMessages());
        $data['body'] = $data['body'] ?? '';
        return DB::transaction(function () use ($message, $actor, $data, $request, $format) {
            ChatConversation::whereKey($message->conversation_id)->lockForUpdate()->firstOrFail();
            $this->messageActor($request, $message);
            $locked = ChatMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_if(now()->greaterThan($locked->created_at->copy()->addHours(24)), 409, 'O prazo de 24 horas para editar esta mensagem terminou.');
            abort_unless($locked->version === $data['version'], 409, 'A mensagem foi alterada. Atualize a conversa antes de editar novamente.');
            if ($locked->body !== $data['body'] || $locked->body_format !== $format) {
                $locked->update(['body' => $data['body'], 'body_format' => $format, 'version' => $locked->version + 1, 'edited_at' => now()]);
                DB::table('chat_message_revisions')->insert(['message_id' => $locked->id, 'version' => $locked->version, 'body' => $locked->body, 'body_format' => $format ? json_encode($format) : null, 'created_at' => now()]);
            }
            return response()->json(['message' => $this->present($locked->fresh(), $actor)]);
        });
    }

    public function revisions(Request $request, ChatMessage $message): JsonResponse
    {
        $this->messageActor($request, $message);
        return response()->json(['revisions' => DB::table('chat_message_revisions')->where('message_id', $message->id)->orderBy('version')->get(['version', 'body', 'body_format', 'created_at'])->map(fn ($entry) => ['version' => $entry->version, 'body' => $entry->body, 'body_format' => $entry->body_format ? json_decode($entry->body_format, true) : null, 'created_at' => $entry->created_at])]);
    }

    private function prepareFormat(Request $request): ?array
    {
        $formatting = new ChatFormatting();
        $format = $formatting->normalize($request->input('body_format'));
        if ($format !== null) $request->merge(['body' => $formatting->text($format)]);
        return $format;
    }

    private function validationMessages(): array
    {
        return [
            'body.required' => 'Escreva uma mensagem antes de enviar.',
            'body.required_without' => 'Escreva uma mensagem ou selecione um anexo.',
            'attachment_descriptions.array' => 'Informe descrições válidas para as imagens.',
            'attachment_descriptions.max' => 'Informe até 3 descrições de imagem.',
            'attachment_descriptions.*.string' => 'A descrição da imagem deve conter texto.',
            'attachment_descriptions.*.max' => 'Use no máximo 500 caracteres na descrição da imagem.',
            'attachments.array' => 'Selecione arquivos válidos para anexar.',
            'attachments.max' => 'Anexe até 3 arquivos por mensagem.',
            'attachments.*.uploaded' => 'Não foi possível carregar o arquivo. Use arquivos de até 2 MB.',
            'attachments.*.file' => 'Selecione um arquivo válido.',
            'attachments.*.max' => 'Cada arquivo deve ter no máximo 2 MB.',
            'attachments.*.extensions' => 'Use PDF, documentos do Office, TXT, CSV, imagens JPG/PNG/WebP ou ZIP.',
            'attachments.*.mimes' => 'Use PDF, documentos do Office, TXT, CSV, imagens JPG/PNG/WebP ou ZIP.',
            'body.string' => 'A mensagem deve conter texto.',
            'body.max' => 'Use no máximo 5.000 caracteres na mensagem.',
            'reviewer_id.required' => 'Selecione quem deve analisar sua solicitação.',
            'reviewer_id.integer' => 'Selecione um aprovador válido.',
            'reason.required' => 'Explique por que precisa conversar com esta pessoa.',
            'reason.string' => 'O motivo deve conter texto.',
            'reason.max' => 'Use no máximo 1.000 caracteres no motivo.',
            'version.required' => 'Atualize a conversa antes de editar novamente.',
            'version.integer' => 'Atualize a conversa antes de editar novamente.',
            'version.min' => 'Atualize a conversa antes de editar novamente.',
        ];
    }

    private function present(ChatMessage $message, User $actor): array
    {
        $message->loadMissing(['sender:id,name', 'attachments']);
        return [...$message->only(['id', 'sender_id', 'body', 'body_format', 'version']), 'is_mine' => $message->sender_id === $actor->id,
            'sender_name' => $message->sender?->name,
            'attachments' => $message->attachments->map(fn ($file) => [...$file->only(['id', 'name', 'mime_type', 'size', 'alt_text']),
                'preview_url' => in_array($file->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true) ? '/api/v1/chat/attachments/'.$file->id.'/preview' : null,
                'download_url' => '/api/v1/chat/attachments/'.$file->id.'/download'])->values(), 'created_at' => $message->created_at->toIso8601String(),
            'edited_at' => $message->edited_at?->toIso8601String(), 'editable_until' => $message->created_at->copy()->addHours(24)->toIso8601String(),
            'can_edit' => $message->sender_id === $actor->id && now()->lessThanOrEqualTo($message->created_at->copy()->addHours(24))];
    }

    public function requestAccess(Request $request, int $user): JsonResponse
    {
        $actor = $this->actor($request);
        $peer = $this->peer($actor, $user);
        abort_if($this->access->allowed($actor, $peer), 409, 'Esta conversa já está disponível.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'reviewer_id' => ['required', 'integer']], $this->validationMessages());
        $reviewers = User::with('profiles')->where('current_company_id', $actor->current_company_id)->where('id', '!=', $actor->id)->get()
            ->filter(fn ($person) => $this->access->canReview($person));
        abort_if($reviewers->isEmpty(), 409, 'Não há administrador ou gerente administrador disponível. Peça à administração para verificar os perfis.');
        $reviewer = $reviewers->firstWhere('id', (int) $data['reviewer_id']);
        if (! $reviewer) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reviewer_id' => 'Selecione um administrador ou gerente administrador ativo desta empresa, diferente de você.']);
        }
        return DB::transaction(function () use ($actor, $peer, $data, $reviewer) {
            $conversation = $this->access->conversation($actor, $peer, true);
            ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_if($this->access->allowed($actor, $peer), 409, 'Esta conversa já está disponível.');
            abort_if(ChatAccessRequest::where('conversation_id', $conversation->id)->where('status', 'pending')->exists(), 409, 'Já existe uma solicitação pendente para esta conversa.');
            $entry = ChatAccessRequest::create(['conversation_id' => $conversation->id, 'requester_id' => $actor->id, 'assigned_reviewer_id' => $reviewer->id, 'reason' => $data['reason']]);
            $this->notify($reviewer->id, 'Solicitação de chat', $actor->name.' → '.$peer->name, $actor, $entry->id);
            return response()->json(['request_id' => $entry->id], 201);
        });
    }

    public function decide(Request $request, ChatAccessRequest $chatRequest): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canReview($actor), 403);
        $conversation = $chatRequest->conversation()->with(['firstUser.profiles', 'secondUser.profiles'])->firstOrFail();
        abort_unless($conversation->company_id === $actor->current_company_id, 404);
        abort_if($chatRequest->requester_id === $actor->id, 403, 'Peça a outro administrador ou gerente administrador para analisar sua solicitação.');
        abort_unless($this->access->pairEligible($conversation->firstUser, $conversation->secondUser), 409, 'Um dos participantes não está disponível para chat.');
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'string', 'max:1000']]);
        return DB::transaction(function () use ($chatRequest, $actor, $data, $conversation) {
            ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $entry = ChatAccessRequest::whereKey($chatRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($entry->assigned_reviewer_id && $entry->assigned_reviewer_id !== $actor->id, 403, 'Esta solicitação foi direcionada a outro aprovador.');
            abort_unless($entry->status === 'pending', 409, 'Esta solicitação já foi respondida.');
            $entry->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'reviewer_id' => $actor->id, 'decision_note' => $data['note'] ?? null, 'decided_at' => now()]);
            foreach ([$conversation->first_user_id, $conversation->second_user_id] as $id) {
                $this->notify($id, 'Solicitação de chat respondida', $entry->status === 'approved' ? 'Conversa autorizada.' : 'Solicitação recusada.', $actor, $entry->id);
            }
            return response()->json(['status' => $entry->status]);
        });
    }

    public function revoke(Request $request, ChatAccessRequest $chatRequest): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->access->canReview($actor), 403);
        $conversation = $chatRequest->conversation;
        abort_unless($conversation->company_id === $actor->current_company_id, 404);
        return DB::transaction(function () use ($chatRequest, $actor, $conversation) {
            ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $entry = ChatAccessRequest::whereKey($chatRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($entry->status === 'approved', 409, 'Esta autorização não está ativa.');
            $entry->update(['status' => 'revoked', 'revoked_at' => now()]);
            foreach ([$conversation->first_user_id, $conversation->second_user_id] as $id) {
                $this->notify($id, 'Autorização de chat revogada', 'Solicite nova autorização se precisar retomar a conversa.', $actor, $entry->id);
            }
            return response()->json(['status' => 'revoked']);
        });
    }

    private function notify(int $userId, string $title, string $body, User $actor, int $requestId): void
    {
        InboxItem::create(['user_id' => $userId, 'type' => 'update', 'title' => $title, 'body' => $body,
            'actor_name' => $actor->name, 'data' => ['chat_request_id' => $requestId]]);
    }

    public function translate(Request $request, ChatMessage $message): JsonResponse
    {
        $this->messageActor($request, $message);
        abort_if(trim($message->body) === '', 422, 'Esta mensagem contém apenas anexos. Não há texto para traduzir.');
        $data = $request->validate(['target' => ['required', Rule::in(['pt', 'en', 'es'])]]);
        $url = config('services.chat_translation.url');
        abort_unless(filled($url), 503, 'A tradução de mensagens ainda não foi configurada. Procure a administração.');
        $key = 'chat.translation.'.$message->id.'.'.$message->version.'.'.$data['target'];
        $translated = Cache::get($key);
        if (! is_string($translated)) {
            try {
                $response = Http::connectTimeout(3)->timeout(15)->acceptJson()->post(rtrim($url, '/').'/translate', array_filter([
                    'q' => $message->body, 'source' => 'auto', 'target' => $data['target'], 'format' => 'text',
                    'api_key' => config('services.chat_translation.key'),
                ], fn ($value) => $value !== null && $value !== ''));
                $translated = $response->json('translatedText');
                abort_unless($response->successful() && is_string($translated) && trim($translated) !== '', 503, 'Não foi possível traduzir agora. Tente novamente.');
            } catch (\Illuminate\Http\Client\ConnectionException $exception) {
                abort(503, 'Não foi possível traduzir agora. Tente novamente.');
            }
            Cache::put($key, $translated, now()->addDay());
        }
        return response()->json(['text' => $translated, 'target' => $data['target'], 'version' => $message->version]);
    }
}
