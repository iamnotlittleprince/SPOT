<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notifications\NotificationPreferences;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function update(Request $request): JsonResponse
    {
        $available = array_keys($this->availableEvents($request));
        $data = $request->validate([
            'events' => ['required', 'array'],
            'events.*.in_app' => ['required', 'boolean'],
            'events.*.email' => ['required', 'boolean'],
            'digest.frequency' => ['required', Rule::in(['none', 'daily', 'weekly'])],
            'digest.time' => ['required', 'date_format:H:i'],
            'quiet_hours.enabled' => ['required', 'boolean'],
            'quiet_hours.start' => ['required', 'date_format:H:i'],
            'quiet_hours.end' => ['required', 'date_format:H:i'],
        ]);
        if (($data['quiet_hours']['enabled'] ?? false) && $data['quiet_hours']['start'] === $data['quiet_hours']['end']) {
            throw ValidationException::withMessages(['quiet_hours.end' => 'O fim do horário de silêncio deve ser diferente do início.']);
        }
        $unknown = array_diff(array_keys($data['events']), $available);
        abort_if($unknown, 422, 'Uma ou mais preferências não estão disponíveis para este perfil.');

        $preferences = [
            'events' => collect($data['events'])->only($available)->all(),
            'digest' => $data['digest'],
            'quiet_hours' => $data['quiet_hours'],
        ];
        $request->user()->update(['notification_preferences' => $preferences]);

        return response()->json(['preferences' => $preferences]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->user()->update(['notification_preferences' => null]);

        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $events = $this->availableEvents($request);
        $defaults = app(NotificationPreferences::class)->for($request->user());
        $stored = $request->user()->notification_preferences ?? [];
        $preferences = array_replace_recursive($defaults, $stored);
        $preferences['events'] = collect($preferences['events'])->only(array_keys($events))->all();

        return ['events' => $events, 'preferences' => $preferences, 'timezone' => $request->user()->timezone, 'delivery' => ['email_enabled' => (bool) config('notifications.email_enabled'), 'scheduler_interval_seconds' => 60]];
    }

    private function availableEvents(Request $request): array
    {
        return app(NotificationPreferences::class)->events($request->user());
    }
}
