<?php
namespace App\Domain\Access;

use App\Domain\Auth\MicrosoftToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class TeamsAccessWindow
{
    private function request(User $user, string $path, array $query = []): array
    {
        $token = app(MicrosoftToken::class)->get($user);
        return Http::connectTimeout(3)->timeout(10)->withToken($token)->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
            ->get('https://graph.microsoft.com/v1.0/me/'.$path, $query)->throw()->json();
    }
    public function event(User $user, string $id): array
    {
        $event = $this->request($user, 'events/'.rawurlencode($id), ['$select' => 'id,subject,start,end,isOnlineMeeting,isCancelled,onlineMeetingProvider']);
        if (empty($event['isOnlineMeeting']) || ($event['onlineMeetingProvider'] ?? '') !== 'teamsForBusiness' || ! empty($event['isCancelled'])) {
            throw ValidationException::withMessages(['teams_event_id' => 'Selecione uma reunião ativa do Teams.']);
        }
        return $this->format($event);
    }
    public function upcoming(User $user): array
    {
        $response = $this->request($user, 'calendarView', ['startDateTime' => now()->startOfDay()->toRfc3339String(), 'endDateTime' => now()->addMonth()->toRfc3339String(), '$top' => 100, '$orderby' => 'start/dateTime', '$select' => 'id,subject,start,end,isOnlineMeeting,isCancelled,onlineMeetingProvider']);
        return collect($response['value'] ?? [])->filter(fn ($e) => ! empty($e['isOnlineMeeting']) && ($e['onlineMeetingProvider'] ?? '') === 'teamsForBusiness' && empty($e['isCancelled']))->map(fn ($e) => $this->format($e))->values()->all();
    }
    private function format(array $event): array
    {
        return ['id' => $event['id'], 'title' => $event['subject'] ?? 'Reunião Teams', 'start' => Carbon::parse($event['start']['dateTime'], 'UTC'), 'end' => Carbon::parse($event['end']['dateTime'], 'UTC')];
    }
}
