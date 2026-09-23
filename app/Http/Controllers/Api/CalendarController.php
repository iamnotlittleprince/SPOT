<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['start' => ['required','date'], 'end' => ['required','date','after:start']]);
        $user = $request->user(); $events = []; $errors = [];
        foreach (CalendarEvent::where('user_id',$user->id)->where('provider','spot')->where('starts_at','<',$data['end'])->where('ends_at','>',$data['start'])->get() as $event) {
            $events[] = $this->format($event->title,$event->starts_at,$event->ends_at,$event->provider,$event->meeting_url,$event->id,$event->description);
        }
        if ($user->google_access_token) try {
            $response = Http::withToken($this->googleToken($user))->get('https://www.googleapis.com/calendar/v3/calendars/primary/events',['timeMin'=>Carbon::parse($data['start'])->toRfc3339String(),'timeMax'=>Carbon::parse($data['end'])->toRfc3339String(),'singleEvents'=>'true','orderBy'=>'startTime']);
            $response->throw(); foreach ($response->json('items',[]) as $item) $events[]=$this->format($item['summary']??'Sem título',$item['start']['dateTime']??$item['start']['date'],$item['end']['dateTime']??$item['end']['date'],'google',$item['hangoutLink']??null,$item['id']??null,$item['description']??null);
        } catch (Throwable) { $errors[]='Não foi possível sincronizar o Google Calendar.'; }
        if ($user->microsoft_access_token) try {
            $response = Http::withToken($this->microsoftToken($user))->withHeaders(['Prefer'=>'outlook.timezone="'.$user->timezone.'"'])->get('https://graph.microsoft.com/v1.0/me/calendarView',['startDateTime'=>Carbon::parse($data['start'])->toIso8601String(),'endDateTime'=>Carbon::parse($data['end'])->toIso8601String(),'$orderby'=>'start/dateTime']);
            $response->throw(); foreach ($response->json('value',[]) as $item) $events[]=$this->format($item['subject']??'Sem título',$item['start']['dateTime'],$item['end']['dateTime'],$item['isOnlineMeeting']?'teams':'microsoft',$item['onlineMeeting']['joinUrl']??$item['webLink']??null,$item['id']??null,$item['bodyPreview']??null);
        } catch (Throwable) { $errors[]='Não foi possível sincronizar o Microsoft 365.'; }
        usort($events,fn($a,$b)=>strcmp($a['start'],$b['start']));
        return response()->json(['events'=>$events,'providers'=>['google'=>(bool)$user->google_access_token,'microsoft'=>(bool)$user->microsoft_access_token],'errors'=>$errors]);
    }

    public function store(Request $request): JsonResponse
    {
        $data=$request->validate(
            ['title'=>['required','string','max:150'],'description'=>['nullable','string','max:3000'],'starts_at'=>['required','date'],'ends_at'=>['required','date','after:starts_at'],'provider'=>['required',Rule::in(['spot','google','microsoft','teams'])],'location'=>['nullable','string','max:200']],
            [
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
        $user=$request->user(); $externalId=null; $meetingUrl=null;
        if ($data['provider']==='google') {
            abort_unless($user->google_access_token,422,'Conecte uma conta Google primeiro.');
            $response=Http::withToken($this->googleToken($user))->post('https://www.googleapis.com/calendar/v3/calendars/primary/events',['summary'=>$data['title'],'description'=>$data['description']??null,'location'=>$data['location']??null,'start'=>['dateTime'=>Carbon::parse($data['starts_at'])->toRfc3339String()],'end'=>['dateTime'=>Carbon::parse($data['ends_at'])->toRfc3339String()]]); $response->throw(); $externalId=$response->json('id');
        }
        if (in_array($data['provider'],['microsoft','teams'],true)) {
            abort_unless($user->microsoft_access_token,422,'Conecte uma conta Microsoft primeiro.');
            $payload=['subject'=>$data['title'],'body'=>['contentType'=>'text','content'=>$data['description']??''],'start'=>['dateTime'=>Carbon::parse($data['starts_at'])->format('Y-m-d\TH:i:s'),'timeZone'=>$user->timezone],'end'=>['dateTime'=>Carbon::parse($data['ends_at'])->format('Y-m-d\TH:i:s'),'timeZone'=>$user->timezone],'location'=>['displayName'=>$data['location']??'']];
            if ($data['provider']==='teams') $payload+=['isOnlineMeeting'=>true,'onlineMeetingProvider'=>'teamsForBusiness'];
            $response=Http::withToken($this->microsoftToken($user))->post('https://graph.microsoft.com/v1.0/me/events',$payload); $response->throw(); $externalId=$response->json('id'); $meetingUrl=$response->json('onlineMeeting.joinUrl');
        }
        $event=CalendarEvent::create($data+['user_id'=>$user->id,'external_id'=>$externalId,'meeting_url'=>$meetingUrl]);
        return response()->json(['event'=>$this->format($event->title,$event->starts_at,$event->ends_at,$event->provider,$event->meeting_url,$event->id,$event->description)],201);
    }

    private function format($title,$start,$end,$provider,$url=null,$id=null,$description=null): array { return ['id'=>(string)$provider.':'.$id,'title'=>$title,'start'=>Carbon::parse($start)->toIso8601String(),'end'=>Carbon::parse($end)->toIso8601String(),'provider'=>$provider,'url'=>$url,'description'=>$description]; }
    private function googleToken($user): string { if (!$user->google_token_expires_at?->isPast()) return $user->google_access_token; abort_unless($user->google_refresh_token,401,'Reconecte sua conta Google.'); $r=Http::asForm()->post('https://oauth2.googleapis.com/token',['client_id'=>config('services.google.client_id'),'client_secret'=>config('services.google.client_secret'),'refresh_token'=>$user->google_refresh_token,'grant_type'=>'refresh_token'])->throw(); $user->forceFill(['google_access_token'=>$r->json('access_token'),'google_token_expires_at'=>now()->addSeconds($r->json('expires_in',3600))])->save(); return $user->google_access_token; }
    private function microsoftToken($user): string { if (!$user->microsoft_token_expires_at?->isPast()) return $user->microsoft_access_token; abort_unless($user->microsoft_refresh_token,401,'Reconecte sua conta Microsoft.'); $tenant=config('services.microsoft.tenant','common'); $r=Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",['client_id'=>config('services.microsoft.client_id'),'client_secret'=>config('services.microsoft.client_secret'),'refresh_token'=>$user->microsoft_refresh_token,'grant_type'=>'refresh_token','scope'=>'offline_access Calendars.ReadWrite'])->throw(); $user->forceFill(['microsoft_access_token'=>$r->json('access_token'),'microsoft_refresh_token'=>$r->json('refresh_token')?:$user->microsoft_refresh_token,'microsoft_token_expires_at'=>now()->addSeconds($r->json('expires_in',3600))])->save(); return $user->microsoft_access_token; }
}
