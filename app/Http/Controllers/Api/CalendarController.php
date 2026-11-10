<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;
use Throwable;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after:start']]);
        $user = $request->user();
        $events = [];
        $errors = [];
        foreach (CalendarEvent::where('user_id', $user->id)->where('provider', 'spot')->where('starts_at', '<', $data['end'])->where('ends_at', '>', $data['start'])->get() as $event) {
            $events[] = $this->format($event->title, $event->starts_at, $event->ends_at, $event->provider, $event->meeting_url, $event->id, $event->description);
        }
        if ($user->google_access_token) {
            try {
                $pageToken = null;
                $token = $this->googleToken($user);
                $page = 0;
                do {
                    $params = ['timeMin' => Carbon::parse($data['start'])->toRfc3339String(), 'timeMax' => Carbon::parse($data['end'])->toRfc3339String(), 'singleEvents' => 'true', 'orderBy' => 'startTime', 'maxResults' => 250];
                    if ($pageToken) {
                        $params['pageToken'] = $pageToken;
                    }
                    $response = Http::timeout(15)->connectTimeout(5)->withToken($token)->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', $params)->throw();
                    foreach ($response->json('items', []) as $item) {
                        if (($item['status'] ?? '') === 'cancelled') {
                            continue;
                        }
                        $start = $item['start']['dateTime'] ?? Carbon::parse($item['start']['date'], $user->timezone ?: 'UTC')->toIso8601String();
                        $end = $item['end']['dateTime'] ?? Carbon::parse($item['end']['date'], $user->timezone ?: 'UTC')->toIso8601String();
                        $events[] = array_merge($this->format($item['summary'] ?? 'Sem título', $start, $end, 'google', $item['hangoutLink'] ?? $item['htmlLink'] ?? null, $item['id'] ?? null, $item['description'] ?? null), ['source_url' => $item['htmlLink'] ?? null, 'location' => $item['location'] ?? null]);
                    }
                    $pageToken = $response->json('nextPageToken');
                    $page++;
                } while ($pageToken && $page < 20);
                if ($pageToken) {
                    $errors[] = 'Existem mais eventos Google. Selecione um período menor.';
                }
            } catch (Throwable) {
                $errors[] = 'Não foi possível sincronizar o Google Calendar. Confira a conexão em Apps.';
            }
        }
        if ($user->microsoft_access_token) {
            try {
                $token = $this->microsoftToken($user);
                $url = 'https://graph.microsoft.com/v1.0/me/calendarView';
                $params = ['startDateTime' => Carbon::parse($data['start'])->toIso8601String(), 'endDateTime' => Carbon::parse($data['end'])->toIso8601String(), '$orderby' => 'start/dateTime', '$top' => 250];
                $page = 0;
                do {
                    // nextLink vem do provedor, mas nunca pode encaminhar o bearer a outro host.
                    if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'graph.microsoft.com' || parse_url($url, PHP_URL_USER) !== null) {
                        throw new \UnexpectedValueException('Página Microsoft inválida.');
                    }
                    $response = Http::timeout(15)->connectTimeout(5)->withToken($token)->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])->get($url, $params)->throw();
                    foreach ($response->json('value', []) as $item) {
                        if ($item['isCancelled'] ?? false) {
                            continue;
                        }
                        $events[] = array_merge($this->format($item['subject'] ?? 'Sem título', Carbon::parse($item['start']['dateTime'], 'UTC')->toIso8601String(), Carbon::parse($item['end']['dateTime'], 'UTC')->toIso8601String(), ($item['isOnlineMeeting'] ?? false) ? 'teams' : 'microsoft', $item['onlineMeeting']['joinUrl'] ?? $item['webLink'] ?? null, $item['id'] ?? null, $item['bodyPreview'] ?? null), ['source_url' => $item['webLink'] ?? null, 'location' => $item['location']['displayName'] ?? null]);
                    }
                    $url = ($response->json()['@odata.nextLink'] ?? null);
                    $params = [];
                    $page++;
                } while ($url && $page < 20);
                if ($url) {
                    $errors[] = 'Existem mais eventos Microsoft. Selecione um período menor.';
                }
            } catch (Throwable) {
                $errors[] = 'Não foi possível sincronizar o Microsoft 365. Confira a conexão em Apps.';
            }
        }
        $saved = CalendarEvent::where('user_id', $user->id)->get()->keyBy(fn ($event) =>
            ($event->provider === 'teams' ? 'microsoft' : $event->provider).':'.($event->provider === 'spot' ? $event->id : $event->external_id));
        foreach ($events as &$entry) {
            $key = preg_replace('/^teams:/', 'microsoft:', $entry['id']);
            if ($local = $saved->get($key)) $entry = array_merge($entry, $this->details($local, $user), ['url' => $local->meeting_url ?: $entry['url']]);
        }
        unset($entry);
        usort($events, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return response()->json(['events' => $events, 'providers' => ['google' => (bool) $user->google_access_token, 'microsoft' => (bool) $user->microsoft_access_token], 'errors' => $errors, 'projects' => $this->projects($user)->orderBy('name')->get(['id', 'name']), 'tasks' => $this->tasks($user)->orderBy('title')->get(['id', 'title', 'project_id'])]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('providers')) return $this->storeMany($request);
        $data = $this->validatedEvent($request);
        $event = $this->saveEvent($data, $request->user());
        return response()->json(['event' => $this->eventPayload($event, $request->user())], 201);
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        abort_unless($calendarEvent->user_id === $request->user()->id, 404);
        $user = $request->user();
        try {
            if ($calendarEvent->provider !== 'spot') {
                abort_unless($calendarEvent->external_id, 409, 'O evento não possui identificação na agenda conectada.');
                if ($calendarEvent->provider === 'google') {
                    abort_unless($user->google_access_token, 422, 'Conecte uma conta Google primeiro.');
                    $response = Http::timeout(15)->connectTimeout(5)->withToken($this->googleToken($user))
                        ->delete('https://www.googleapis.com/calendar/v3/calendars/primary/events/'.rawurlencode($calendarEvent->external_id));
                } else {
                    abort_unless($user->microsoft_access_token, 422, 'Conecte uma conta Microsoft primeiro.');
                    $response = Http::timeout(15)->connectTimeout(5)->withToken($this->microsoftToken($user))
                        ->delete('https://graph.microsoft.com/v1.0/me/events/'.rawurlencode($calendarEvent->external_id));
                }
                // A missing event has already been removed from this connected calendar.
                if (!in_array($response->status(), [404, 410], true)) $response->throw();
            }
        } catch (RequestException) {
            return response()->json(['message' => 'Não foi possível excluir o evento na agenda de origem. Confira a conexão e a permissão e tente novamente.'], 422);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return response()->json(['message' => 'Não foi possível confirmar a exclusão. Confira a agenda e tente novamente.'], 503);
        }
        $calendarEvent->delete();
        return response()->json(['message' => 'Evento excluído.']);
    }

    public function update(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        abort_unless($calendarEvent->user_id === $request->user()->id, 404);
        $user = $request->user();
        $validation = clone $request;
        // Editing updates this copy in its existing calendar; it never creates a new destination.
        $validation->merge(['provider' => $calendarEvent->provider]);
        $data = $this->validatedEvent($validation);
        $data['starts_at'] = Carbon::parse($data['starts_at'], $user->timezone ?: 'UTC')->utc()->toIso8601String();
        $data['ends_at'] = Carbon::parse($data['ends_at'], $user->timezone ?: 'UTC')->utc()->toIso8601String();
        $description = trim(($data['description'] ?? '').(!empty($data['meeting_url']) ? "\n\n".$data['meeting_url'] : ''));
        try {
            if ($calendarEvent->provider === 'google') {
                abort_unless($user->google_access_token, 422, 'Conecte uma conta Google primeiro.');
                abort_unless($calendarEvent->external_id, 409, 'O evento não possui identificação na agenda conectada.');
                Http::timeout(15)->connectTimeout(5)->withToken($this->googleToken($user))->patch(
                    'https://www.googleapis.com/calendar/v3/calendars/primary/events/'.rawurlencode($calendarEvent->external_id),
                    ['summary' => $data['title'], 'description' => $description, 'location' => $data['location'] ?? '',
                        'start' => ['dateTime' => Carbon::parse($data['starts_at'])->toRfc3339String()],
                        'end' => ['dateTime' => Carbon::parse($data['ends_at'])->toRfc3339String()]]
                )->throw();
            }
            if (in_array($calendarEvent->provider, ['microsoft', 'teams'], true)) {
                abort_unless($user->microsoft_access_token, 422, 'Conecte uma conta Microsoft primeiro.');
                abort_unless($calendarEvent->external_id, 409, 'O evento não possui identificação na agenda conectada.');
                $client = Http::timeout(15)->connectTimeout(5)->withToken($this->microsoftToken($user));
                $url = 'https://graph.microsoft.com/v1.0/me/events/'.rawurlencode($calendarEvent->external_id);
                $payload = ['subject' => $data['title'],
                    'start' => ['dateTime' => Carbon::parse($data['starts_at'])->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => Carbon::parse($data['ends_at'])->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                    'location' => ['displayName' => $data['location'] ?? '']];
                if (($data['description'] ?? null) !== $calendarEvent->description ||
                    ($data['meeting_url'] ?? null) !== $calendarEvent->meeting_url) {
                    $remote = $client->get($url)->throw()->json();
                    if (($remote['isOnlineMeeting'] ?? false) || $calendarEvent->provider === 'teams') {
                        // Preserve the provider's meeting body, including its join instructions.
                        $body = $remote['body']['content'] ?? '';
                        if (strtolower($remote['body']['contentType'] ?? 'html') !== 'html') $body = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                        $section = '<!-- spot-edit-start --><div><strong>Spot</strong><p>'.
                            nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p></div><!-- spot-edit-end -->';
                        if (str_contains($body, '<!-- spot-edit-start -->')) {
                            $body = preg_replace('/<!-- spot-edit-start -->.*?<!-- spot-edit-end -->/s', '', $body);
                        }
                        $body = preg_match('/<body\b[^>]*>/i', $body)
                            ? preg_replace_callback('/<body\b[^>]*>/i', fn ($match) => $match[0].$section, $body, 1)
                            : $section.$body;
                        $payload['body'] = ['contentType' => 'html', 'content' => $body];
                    } else {
                        $payload['body'] = ['contentType' => 'text', 'content' => $description];
                    }
                }
                if ($request->boolean('enable_teams') && $calendarEvent->provider === 'microsoft') $payload += ['isOnlineMeeting' => true, 'onlineMeetingProvider' => 'teamsForBusiness'];
                $updated = $client->patch($url, $payload)->throw();
                if ($request->boolean('enable_teams') && $calendarEvent->provider === 'microsoft') {
                    $data['provider'] = 'teams';
                    if (empty($data['meeting_url'])) $data['meeting_url'] = $updated->json('onlineMeeting.joinUrl') ?: $calendarEvent->meeting_url;
                }
            }
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            return response()->json(['message' => in_array($status, [401, 403], true)
                ? 'Confira a conexão e a permissão para editar o evento na agenda de origem.'
                : 'Não foi possível confirmar a atualização. Confira a agenda e tente novamente.'], 422);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return response()->json(['message' => 'Não foi possível confirmar a atualização. Confira a agenda e tente novamente.'], 503);
        }
        $calendarEvent->update($data);
        return response()->json(['event' => $this->eventPayload($calendarEvent->fresh(), $user)]);
    }

    public function changeAgendas(Request $request, int $eventId): JsonResponse
    {
        $selection = $request->validate([
            'providers' => ['required', 'array', 'min:1', 'max:4'],
            'providers.*' => ['required', 'distinct', Rule::in(['spot', 'google', 'microsoft', 'teams'])],
            'request_key' => ['required', 'uuid'],
        ], ['providers.required' => 'Selecione pelo menos uma agenda.', 'providers.min' => 'Selecione pelo menos uma agenda.']);
        $providers = $selection['providers'];
        if (in_array('teams', $providers, true)) $providers = array_values(array_diff($providers, ['microsoft']));
        sort($providers);
        $user = $request->user();
        $operations = DB::table('calendar_event_changes')->where('user_id', $user->id)->where('request_key', $selection['request_key']);
        $lock = Cache::lock('calendar-change:'.$user->id.':'.$selection['request_key'], 300);
        if (!$lock->get()) return response()->json(['message' => 'Esta gravação ainda está em andamento. Aguarde e tente novamente.'], 409);
        $groupLock = null;
        try {
            $operation = (clone $operations)->first();
            $source = CalendarEvent::where('user_id', $user->id)->find($eventId);
            abort_unless($source || ($operation && (int) $operation->source_event_id === $eventId), 404);
            $validation = clone $request;
            $validation->merge(['provider' => $providers[0]]);
            $data = $this->validatedEvent($validation);
            unset($data['provider']);
            $hash = hash('sha256', json_encode([$eventId, $providers, $data]));
            if ($operation && $operation->payload_hash !== $hash) {
                return response()->json(['message' => 'Esta tentativa pertence a outro evento. Feche e abra um novo evento.'], 409);
            }
            $group = $operation?->group_key ?: $source->group_key;
            if (!$group) {
                $group = (string) \Illuminate\Support\Str::uuid();
                $source->update(['group_key' => $group]);
            }
            $groupLock = Cache::lock('calendar-group:'.$user->id.':'.$group, 300);
            if (!$groupLock->get()) return response()->json(['message' => 'Esta gravação ainda está em andamento. Aguarde e tente novamente.'], 409);
            if (!$operation) {
                $canonical = fn ($provider) => $provider === 'teams' ? 'microsoft' : $provider;
                $members = CalendarEvent::where('user_id', $user->id)->where('group_key', $group)->get()->keyBy(fn ($entry) => $canonical($entry->provider));
                $steps = [];
                if (in_array('teams', $providers, true)) $providers = ['teams', ...array_values(array_diff($providers, ['teams']))];
                foreach ($providers as $provider) {
                    $member = $members->get($canonical($provider));
                    $steps[] = ['provider' => $provider, 'action' => $member ? 'update' : 'add', 'event_id' => $member?->id, 'status' => 'waiting'];
                }
                $desired = array_map($canonical, $providers);
                foreach ($members as $key => $member) {
                    if (!in_array($key, $desired, true)) $steps[] = ['provider' => $member->provider, 'action' => 'remove', 'event_id' => $member->id, 'status' => 'waiting'];
                }
                DB::table('calendar_event_changes')->insertGetId([
                    'user_id' => $user->id, 'request_key' => $selection['request_key'], 'source_event_id' => $eventId,
                    'group_key' => $group, 'payload_hash' => $hash, 'steps' => json_encode($steps), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $operation = (clone $operations)->first();
            } else {
                $steps = json_decode($operation->steps, true);
            }
            $persist = function () use (&$steps, $operations) {
                (clone $operations)->update(['steps' => json_encode($steps), 'updated_at' => now()]);
            };
            foreach ($steps as $index => $step) {
                if (in_array($step['status'], ['saved', 'removed'], true)) {
                    if ($step['provider'] === 'teams' && empty($data['meeting_url']) && $step['event_id']) {
                        $data['meeting_url'] = CalendarEvent::where('user_id', $user->id)->find($step['event_id'])?->meeting_url;
                    }
                    continue;
                }
                // Do not remove old copies until every requested destination has been saved.
                if ($step['action'] === 'remove' && collect($steps)->contains(fn ($entry) => $entry['action'] !== 'remove' && $entry['status'] !== 'saved')) continue;
                if ($step['action'] === 'add' && in_array($step['status'], ['pending', 'uncertain'], true)) {
                    $steps[$index]['status'] = 'uncertain'; $persist(); continue;
                }
                $steps[$index]['status'] = 'pending'; $persist();
                try {
                    $event = $step['event_id'] ? CalendarEvent::where('user_id', $user->id)->find($step['event_id']) : null;
                    if ($step['action'] === 'add') {
                        if (($step['provider'] === 'google' && !$user->google_access_token) ||
                            (in_array($step['provider'], ['microsoft', 'teams'], true) && !$user->microsoft_access_token)) {
                            $steps[$index]['status'] = 'failed';
                        } else {
                            $event = $this->saveEvent($data + ['provider' => $step['provider'], 'group_key' => $group], $user);
                            $steps[$index]['event_id'] = $event->id;
                            $steps[$index]['status'] = 'saved';
                        }
                    } elseif ($step['action'] === 'update') {
                        if (!$event) {
                            $steps[$index]['status'] = 'failed';
                        } else {
                            $input = clone $request;
                            $input->replace($data + ['enable_teams' => $step['provider'] === 'teams']);
                            $response = $this->update($input, $event);
                            $steps[$index]['status'] = $response->getStatusCode() === 200 ? 'saved' : 'failed';
                        }
                    } else {
                        $response = $event ? $this->destroy($request, $event) : null;
                        $steps[$index]['status'] = !$response || $response->getStatusCode() === 200 ? 'removed' : 'failed';
                    }
                    if ($step['provider'] === 'teams' && $event && empty($data['meeting_url'])) $data['meeting_url'] = $event->fresh()?->meeting_url;
                } catch (RequestException $exception) {
                    $code = $exception->response->status();
                    $steps[$index]['status'] = $step['action'] !== 'add' || ($code >= 400 && $code < 500 && $code !== 408) ? 'failed' : 'uncertain';
                } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                    $steps[$index]['status'] = 'failed';
                } catch (Throwable $exception) {
                    report($exception);
                    $steps[$index]['status'] = $step['action'] === 'add' ? 'uncertain' : 'failed';
                }
                $persist();
            }
            $complete = collect($steps)->every(fn ($step) => in_array($step['status'], ['saved', 'removed'], true));
            $kept = collect($steps)->first(fn ($step) => $step['status'] === 'saved' && $step['action'] !== 'remove');
            $event = $kept ? CalendarEvent::where('user_id', $user->id)->find($kept['event_id']) : null;
            return response()->json(['complete' => $complete, 'results' => $steps, 'event' => $event ? $this->eventPayload($event, $user) : null], $complete ? 200 : 207);
        } finally {
            $groupLock?->release();
            $lock->release();
        }
    }

    private function validatedEvent(Request $request): array
    {
        $data = $request->validate(
            ['title' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:3000'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'provider' => ['required', Rule::in(['spot', 'google', 'microsoft', 'teams'])], 'location' => ['nullable', 'string', 'max:200'], 'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'meeting_url' => ['nullable', 'url:http,https', 'max:2000'], 'project_id' => ['nullable', 'integer'], 'task_id' => ['nullable', 'integer']],
            [
                'color.regex' => 'Escolha uma cor válida para o evento.',
                'meeting_url.url' => 'Informe um link completo iniciado por https:// ou http://.',
                'meeting_url.max' => 'O link pode ter no máximo 2.000 caracteres.',
                'project_id.integer' => 'Escolha um projeto válido.',
                'task_id.integer' => 'Escolha uma tarefa válida.',
                'title.required' => 'Informe o título do evento.',
                'title.max' => 'O título pode ter no máximo 150 caracteres.',
                'starts_at.required' => 'Informe a data e o horário de início.',
                'starts_at.date' => 'Informe uma data e um horário de início válidos.',
                'ends_at.required' => 'Informe a data e o horário de término.',
                'ends_at.date' => 'Informe uma data e um horário de término válidos.',
                'ends_at.after' => 'O horário de término deve ser posterior ao horário de início.',
                'provider.required' => 'Escolha em qual agenda o evento será criado.',
                'provider.in' => 'A agenda selecionada não é válida.',
                'location.max' => 'O local pode ter no máximo 200 caracteres.',
                'description.max' => 'A descrição pode ter no máximo 3.000 caracteres.',
            ],
        );
        $user = $request->user();
        if (!empty($data['project_id']) && !$this->projects($user)->whereKey($data['project_id'])->exists()) {
            throw ValidationException::withMessages(['project_id' => 'Escolha um projeto ao qual você tenha acesso.']);
        }
        if (!empty($data['task_id'])) {
            $task = $this->tasks($user)->find($data['task_id']);
            if (!$task || (!empty($data['project_id']) && (int) $data['project_id'] !== $task->project_id)) {
                throw ValidationException::withMessages(['task_id' => 'Escolha uma tarefa acessível do projeto selecionado.']);
            }
            $data['project_id'] = $task->project_id;
        }
        return $data;
    }

    private function saveEvent(array $data, $user): CalendarEvent
    {
        $data['group_key'] ??= (string) \Illuminate\Support\Str::uuid();
        $externalId = null;
        $meetingUrl = $data['meeting_url'] ?? null;
        unset($data['meeting_url']);
        $externalDescription = trim(($data['description'] ?? '').($meetingUrl ? "\n\n".$meetingUrl : ''));
        $data['starts_at'] = Carbon::parse($data['starts_at'], $user->timezone ?: 'UTC')->utc()->toIso8601String();
        $data['ends_at'] = Carbon::parse($data['ends_at'], $user->timezone ?: 'UTC')->utc()->toIso8601String();
        if ($data['provider'] === 'google') {
            abort_unless($user->google_access_token, 422, 'Conecte uma conta Google primeiro.');
            $response = Http::timeout(15)->connectTimeout(5)->withToken($this->googleToken($user))->post('https://www.googleapis.com/calendar/v3/calendars/primary/events', ['summary' => $data['title'], 'description' => $externalDescription, 'location' => $data['location'] ?? null, 'start' => ['dateTime' => Carbon::parse($data['starts_at'])->toRfc3339String()], 'end' => ['dateTime' => Carbon::parse($data['ends_at'])->toRfc3339String()]]);
            $response->throw();
            $externalId = $response->json('id');
            if (!is_string($externalId) || $externalId === '') throw new \UnexpectedValueException('O provedor não confirmou o identificador do evento.');
        }
        if (in_array($data['provider'], ['microsoft', 'teams'], true)) {
            abort_unless($user->microsoft_access_token, 422, 'Conecte uma conta Microsoft primeiro.');
            $payload = ['subject' => $data['title'], 'body' => ['contentType' => 'text', 'content' => $externalDescription], 'start' => ['dateTime' => Carbon::parse($data['starts_at'])->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'], 'end' => ['dateTime' => Carbon::parse($data['ends_at'])->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'], 'location' => ['displayName' => $data['location'] ?? '']];
            if ($data['provider'] === 'teams') {
                $payload += ['isOnlineMeeting' => true, 'onlineMeetingProvider' => 'teamsForBusiness'];
            }
            $response = Http::timeout(15)->connectTimeout(5)->withToken($this->microsoftToken($user))->post('https://graph.microsoft.com/v1.0/me/events', $payload);
            $response->throw();
            $externalId = $response->json('id');
            if (!is_string($externalId) || $externalId === '') throw new \UnexpectedValueException('O provedor não confirmou o identificador do evento.');
            $meetingUrl = $meetingUrl ?: $response->json('onlineMeeting.joinUrl');
        }
        $event = CalendarEvent::create($data + ['user_id' => $user->id, 'external_id' => $externalId, 'meeting_url' => $meetingUrl]);

        return $event;
    }


    private function eventPayload(CalendarEvent $event, $user): array
    {
        return array_merge($this->format($event->title, $event->starts_at, $event->ends_at, $event->provider, $event->meeting_url, $event->provider === 'spot' ? $event->id : $event->external_id, $event->description), $this->details($event, $user));
    }

    private function storeMany(Request $request): JsonResponse
    {
        $request->validate(['request_key' => ['required', 'uuid']]);
        $lock = Cache::lock('calendar-create:'.$request->user()->id.':'.$request->input('request_key'), 300);
        if (!$lock->get()) return response()->json(['message' => 'Esta gravação ainda está em andamento. Aguarde e tente novamente.'], 409);
        $groupLock = Cache::lock('calendar-group:'.$request->user()->id.':'.$request->input('request_key'), 300);
        try {
            if (!$groupLock->get()) return response()->json(['message' => 'Esta gravação ainda está em andamento. Aguarde e tente novamente.'], 409);
            return $this->storeManyLocked($request);
        } finally { $groupLock->release(); $lock->release(); }
    }

    private function storeManyLocked(Request $request): JsonResponse
    {
        $selection = $request->validate([
            'providers' => ['required', 'array', 'min:1', 'max:4'],
            'providers.*' => ['required', 'distinct', Rule::in(['spot', 'google', 'microsoft', 'teams'])],
            'request_key' => ['required', 'uuid'],
        ], [
            'providers.required' => 'Selecione pelo menos uma agenda.',
            'providers.min' => 'Selecione pelo menos uma agenda.',
            'providers.*.in' => 'A agenda selecionada não é válida.',
        ]);
        $providers = $selection['providers'];
        // Outlook and Teams share the same Microsoft calendar.
        if (in_array('teams', $providers, true)) $providers = array_values(array_diff($providers, ['microsoft']));
        sort($providers);
        $validationRequest = clone $request;
        $validationRequest->merge(['provider' => $providers[0]]);
        $data = $this->validatedEvent($validationRequest);
        unset($data['provider']);
        $user = $request->user();
        $hash = hash('sha256', json_encode([$data, $providers]));
        $base = DB::table('calendar_deliveries')->where('user_id', $user->id)->where('request_key', $selection['request_key']);
        if ((clone $base)->where('payload_hash', '!=', $hash)->exists()) {
            return response()->json(['message' => 'Esta tentativa pertence a outro evento. Feche e abra um novo evento.'], 409);
        }
        $data['group_key'] = $selection['request_key'];
        $results = [];
        if (in_array('teams', $providers, true)) $providers = ['teams', ...array_values(array_diff($providers, ['teams']))];
        foreach ($providers as $provider) {
            $query = (clone $base)->where('provider', $provider);
            $inserted = DB::table('calendar_deliveries')->insertOrIgnore([
                'user_id' => $user->id, 'request_key' => $selection['request_key'], 'provider' => $provider,
                'payload_hash' => $hash, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $delivery = (clone $query)->first();
            $claimed = $inserted || (clone $query)->where('status', 'failed')->update(['status' => 'pending', 'updated_at' => now()]);
            if ($claimed) {
                $status = 'uncertain';
                try {
                    $existing = CalendarEvent::where('user_id', $user->id)->where('group_key', $selection['request_key'])
                        ->whereIn('provider', in_array($provider, ['microsoft', 'teams'], true) ? ['microsoft', 'teams'] : [$provider])->first();
                    if ($existing) {
                        (clone $query)->update(['calendar_event_id' => $existing->id]);
                        $status = 'saved';
                    } elseif (($provider === 'google' && !$user->google_access_token) ||
                        (in_array($provider, ['microsoft', 'teams'], true) && !$user->microsoft_access_token)) {
                        $status = 'failed';
                    } else {
                        $event = $this->saveEvent($data + ['provider' => $provider], $user);
                        (clone $query)->update(['calendar_event_id' => $event->id]);
                        $status = 'saved';
                    }
                } catch (RequestException $exception) {
                    // A rejected request can be retried. Timeouts/server failures may have created the event.
                    $code = $exception->response->status();
                    $status = $code >= 400 && $code < 500 && $code !== 408 ? 'failed' : 'uncertain';
                } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                    $status = $exception->getStatusCode() < 500 ? 'failed' : 'uncertain';
                } catch (Throwable $exception) {
                    report($exception);
                }
                (clone $query)->update(['status' => $status, 'updated_at' => now()]);
                $delivery = (clone $query)->first();
            }
            $event = $delivery->calendar_event_id ? CalendarEvent::where('user_id', $user->id)->find($delivery->calendar_event_id) : null;
            if ($provider === 'teams' && empty($data['meeting_url']) && $event?->meeting_url) $data['meeting_url'] = $event->meeting_url;
            $results[] = ['provider' => $provider, 'status' => $delivery->status,
                'event' => $event ? $this->eventPayload($event, $user) : null];
        }
        $complete = collect($results)->every(fn ($entry) => $entry['status'] === 'saved');
        return response()->json(['results' => $results, 'complete' => $complete], $complete ? 201 : 207);
    }

    private function projects($user)
    {
        return Project::where('company_id', $user->current_company_id)
            ->when(!$user->can('projects.view_all'), fn ($query) => $user->can('projects.view_assigned')
                ? $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id)
                    ->where(fn ($access) => $access->where('active', true)->orWhereNotNull('projects.finalized_at')))
                : $query->whereRaw('1 = 0'));
    }

    private function tasks($user)
    {
        return Task::whereIn('project_id', $this->projects($user)->select('id'))
            ->when(!$user->can('tasks.view_others'), fn ($query) => $query->where(fn ($own) =>
                $own->where('user_id', $user->id)->orWhereHas('participants', fn ($people) => $people->where('users.id', $user->id))));
    }

    private function details(CalendarEvent $event, $user): array
    {
        return [
            'edit_id' => $event->id,
            'providers' => CalendarEvent::where('user_id', $event->user_id)->where('group_key', $event->group_key)->when(!$event->group_key, fn ($query) => $query->whereKey($event->id))->pluck('provider')->all(),
            'description' => $event->description,
            'meeting_url' => $event->meeting_url,
            'color' => $event->color,
            'url' => $event->meeting_url,
            'location' => $event->location,
            'project' => $event->project_id ? $this->projects($user)->find($event->project_id, ['id', 'name']) : null,
            'task' => $event->task_id ? $this->tasks($user)->find($event->task_id, ['id', 'title', 'project_id']) : null,
        ];
    }

    private function format($title, $start, $end, $provider, $url = null, $id = null, $description = null): array
    {
        return ['id' => (string) $provider.':'.$id, 'title' => $title, 'start' => Carbon::parse($start)->toIso8601String(), 'end' => Carbon::parse($end)->toIso8601String(), 'provider' => $provider, 'url' => $url, 'description' => $description];
    }

    private function googleToken($user): string
    {
        if (! $user->google_token_expires_at?->isPast()) {
            return $user->google_access_token;
        } abort_unless($user->google_refresh_token, 401, 'Reconecte sua conta Google.');
        $r = Http::timeout(15)->connectTimeout(5)->asForm()->post('https://oauth2.googleapis.com/token', ['client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'), 'refresh_token' => $user->google_refresh_token, 'grant_type' => 'refresh_token'])->throw();
        $user->forceFill(['google_access_token' => $r->json('access_token'), 'google_token_expires_at' => now()->addSeconds($r->json('expires_in', 3600))])->save();

        return $user->google_access_token;
    }

    private function microsoftToken($user): string
    {
        return app(\App\Domain\Auth\MicrosoftToken::class)->get($user);
    }
}
