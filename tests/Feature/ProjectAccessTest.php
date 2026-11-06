<?php
namespace Tests\Feature;

use App\Domain\Access\ProjectReportAccess;
use App\Models\{Company, InboxItem, Profile, Project, ProjectAccessRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;
    private function setupAccess(): array
    {
        Http::preventStrayRequests();
        $company = Company::create(['legal_name' => 'Teste']);
        $manager = $this->person($company, 'gestor');
        $analyst = $this->person($company, 'analista');
        $project = Project::create(['company_id' => $company->id, 'user_id' => $manager->id, 'project_manager_id' => $manager->id, 'name' => 'Projeto A', 'status' => 'in_progress', 'contract_value' => 100, 'progress' => 10]);
        $project->members()->create(['user_id'=>$analyst->id,'role'=>'analyst','active'=>true]);
        return [$company, $manager, $analyst, $project];
    }
    private function person(Company $company, string $role): User
    {
        $u = User::factory()->create(['current_company_id' => $company->id]);
        $p = Profile::firstOrCreate(['slug' => $role], ['name' => $role]);
        $u->profiles()->attach($p, ['company_id' => $company->id]);
        return $u;
    }
    private function ask(User $analyst, User $manager, Project $p): int
    {
        return $this->actingAs($analyst)->postJson('/api/v1/project-access', ['project_id' => $p->id, 'reviewer_id' => $manager->id, 'message' => 'Preciso apresentar os indicadores.'])->assertCreated()->json('request.id');
    }
    private function approve(User $manager, int $id, string $mode = 'view', string $duration = '24h', array $extra = []): void
    {
        $this->actingAs($manager)->postJson("/api/v1/project-access/{$id}/decision", $extra + ['decision' => 'approve', 'access_mode' => $mode, 'duration' => $duration])->assertOk();
    }
    public function test_catalog_lists_assigned_projects_and_both_managers_for_analyst(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        $requester=$a;
        $accountManager=$this->person($c,'gestor');
        $p->update(['account_manager_id'=>$accountManager->id]);
        $unassigned=Project::create(['company_id'=>$c->id,'user_id'=>$m->id,'name'=>'Z sem gerente']);
        $unassigned->members()->create(['user_id'=>$a->id,'role'=>'analyst','active'=>true]);
        $foreign=Company::create(['legal_name'=>'Outra']);
        Project::create(['company_id'=>$foreign->id,'user_id'=>$m->id,'name'=>'Segredo externo']);
        $response=$this->actingAs($requester)->getJson('/api/v1/project-access')->assertOk()->assertJsonCount(2,'projects');
        $options=collect($response->json('projects'))->keyBy('id');
        $this->assertEqualsCanonicalizing([$m->id,$accountManager->id],array_column($options[$p->id]['reviewers'],'id'));
        $this->assertSame([],$options[$unassigned->id]['reviewers']);
        $this->assertArrayNotHasKey('contract_value',$options[$p->id]);
        $this->ask($requester,$accountManager,$p);
    }

    public function test_administration_and_managers_cannot_use_analyst_request_flow_even_with_analyst_profile(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        foreach (['administrador','gestor-administrador','gestor'] as $role) {
            $u=$this->person($c,$role);
            $u->profiles()->attach(Profile::where('slug','analista')->firstOrFail(),['company_id'=>$c->id]);
            $p->members()->create(['user_id'=>$u->id,'role'=>'analyst','active'=>true]);
            $this->actingAs($u)->getJson('/api/v1/project-access')->assertOk()->assertJsonPath('can_request',false)->assertJsonCount(0,'projects');
            $this->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$m->id,'message'=>'Pedido indevido'])->assertForbidden();
        }
        $p->members()->where('user_id',$a->id)->update(['role'=>'manager']);
        $this->actingAs($a)->getJson('/api/v1/project-access')->assertOk()->assertJsonCount(0,'projects');
        $this->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$m->id,'message'=>'Pedido como gerente'])->assertForbidden();
    }

    public function test_pending_analyst_request_cannot_be_decided_after_requester_becomes_manager(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        $id=$this->ask($a,$m,$p);
        $a->profiles()->attach(Profile::where('slug','gestor')->firstOrFail(),['company_id'=>$c->id]);
        $this->actingAs($m)->getJson('/api/v1/project-access')->assertOk()->assertJsonPath('requests.0.can_decide',false);
        $this->postJson("/api/v1/project-access/{$id}/decision",['decision'=>'approve','access_mode'=>'view','duration'=>'24h'])->assertForbidden();
        $this->assertDatabaseHas('project_access_requests',['id'=>$id,'status'=>'pending']);
    }

    public function test_analyst_only_lists_and_requests_projects_with_active_participation(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        $other=Project::create(['company_id'=>$c->id,'user_id'=>$m->id,'project_manager_id'=>$m->id,'name'=>'Não participa']);
        $this->actingAs($a)->getJson('/api/v1/project-access')->assertOk()->assertJsonCount(1,'projects')->assertJsonPath('projects.0.id',$p->id);
        $this->postJson('/api/v1/project-access',['project_id'=>$other->id,'reviewer_id'=>$m->id,'message'=>'Tentativa direta'])->assertForbidden();
        $id=$this->ask($a,$m,$p);$this->approve($m,$id);
        $p->members()->where('user_id',$a->id)->update(['access_revoked_at'=>now()]);
        $this->actingAs($a)->getJson('/api/v1/project-access')->assertOk()->assertJsonCount(0,'projects');
        $this->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$m->id,'message'=>'Tentativa revogada'])->assertForbidden();
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
    }

    public function test_active_team_manager_is_recipient_even_with_global_analyst_profile(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        $teamManager=$this->person($c,'analista');
        $member=$p->members()->create(['user_id'=>$teamManager->id,'role'=>'manager','relationship_type'=>'responsible_manager','active'=>true]);
        $response=$this->actingAs($a)->getJson('/api/v1/project-access')->assertOk()->assertJsonCount(2,'projects.0.reviewers');
        $this->assertContains($teamManager->id,array_column($response->json('projects.0.reviewers'),'id'));
        $id=$this->ask($a,$teamManager,$p);$this->approve($teamManager,$id);
        $this->assertSame([$p->id],app(ProjectReportAccess::class)->allowedIds($a));
        $member->update(['role'=>'analyst','relationship_type'=>'cpt_internal_analyst']);
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $this->actingAs($a)->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$teamManager->id,'message'=>'Gerente removido'])->assertForbidden();
    }

    public function test_request_notifies_both_sides_and_scopes_approved_access(): void
    {
        [$c,$m,$a,$p] = $this->setupAccess();
        $this->actingAs($a)->getJson('/api/v1/reports/project-analytics')->assertForbidden();
        $id = $this->ask($a,$m,$p);
        $this->assertDatabaseHas('inbox_items', ['user_id' => $m->id, 'title' => 'Acesso solicitado']);
        $this->assertDatabaseHas('inbox_items', ['user_id' => $a->id, 'title' => 'Solicitação de acesso enviada']);
        $this->actingAs($m)->getJson('/api/v1/project-access')->assertOk()->assertJsonPath('requests.0.can_decide', true);
        $this->approve($m,$id);
        $this->getJson('/api/v1/project-access')->assertOk()->assertJsonPath('requests.0.effective_status', 'active')->assertJsonMissingPath('requests.0.reviewer.microsoft_access_token');
        $this->assertSame([$p->id], app(ProjectReportAccess::class)->allowedIds($a));
        $this->actingAs($a)->getJson("/api/v1/projects/{$p->id}/financial-result")->assertOk();
        $other = Project::create(['company_id'=>$c->id,'user_id'=>$m->id,'name'=>'Outro']);
        $this->getJson("/api/v1/projects/{$other->id}/financial-result")->assertForbidden();
        $this->getJson('/api/v1/reports/project-analytics')->assertOk()->assertJsonCount(1, 'projects')->assertJsonPath('projects.0.id', $p->id);
        $this->getJson('/api/v1/reports/portfolio')->assertForbidden();
        $this->assertDatabaseHas('inbox_items', ['user_id' => $a->id, 'title' => 'Acesso concedido']);
    }
    public function test_manager_cannot_receive_or_approve_another_managers_project(): void
    {
        [$c,$m,$a,$p] = $this->setupAccess();
        $other = $this->person($c,'gestor');
        $this->actingAs($a)->postJson('/api/v1/project-access', ['project_id'=>$p->id,'reviewer_id'=>$other->id,'message'=>'Teste'])->assertForbidden();
        $id=$this->ask($a,$m,$p);
        $this->actingAs($other)->postJson("/api/v1/project-access/{$id}/decision", ['decision'=>'reject'])->assertForbidden();
        $p->update(['project_manager_id'=>$other->id]);
        $this->actingAs($m)->postJson("/api/v1/project-access/{$id}/decision", ['decision'=>'reject'])->assertForbidden();
    }
    public function test_both_administration_profiles_require_active_requester_membership(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        $p->members()->where('user_id',$a->id)->update(['active'=>false]);
        $admin=$this->person($c,'gestor-administrador');
        $this->actingAs($a)->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$admin->id,'message'=>'Teste'])->assertForbidden();
        $p->members()->where('user_id',$a->id)->update(['active'=>true]);
        $id=$this->ask($a,$admin,$p);$this->approve($admin,$id);
        $this->assertSame([$p->id],app(ProjectReportAccess::class)->allowedIds($a));
        $p->members()->update(['active'=>false]);
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $plain=$this->person($c,'administrador');
        $this->actingAs($a)->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$plain->id,'message'=>'Teste'])->assertForbidden();
        $p->members()->update(['active'=>true]);
        $plainId=$this->ask($a,$plain,$p);
        $this->approve($plain,$plainId);
    }
    public function test_foreign_company_self_grant_duplicate_and_inactive_users_are_blocked(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        $foreign=$this->person(Company::create(['legal_name'=>'Outra']),'gestor');
        $this->actingAs($a)->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$foreign->id,'message'=>'Teste'])->assertForbidden();
        $id=$this->ask($a,$m,$p);
        $this->actingAs($a)->postJson('/api/v1/project-access',['project_id'=>$p->id,'reviewer_id'=>$m->id,'message'=>'Teste'])->assertUnprocessable();
        $this->actingAs($a)->postJson("/api/v1/project-access/{$id}/decision",['decision'=>'reject'])->assertForbidden();
        $m->update(['active'=>false]);
        $this->actingAs($m)->postJson("/api/v1/project-access/{$id}/decision",['decision'=>'reject'])->assertForbidden();
    }
    public function test_expiration_revocation_and_role_loss_are_enforced(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();$id=$this->ask($a,$m,$p);$this->approve($m,$id);
        $this->travel(24)->hours();
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $this->travelBack();
        $m->profiles()->detach();
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $this->actingAs($m)->postJson("/api/v1/project-access/{$id}/revoke")->assertOk();
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
    }
    public function test_view_and_visual_cannot_write_data_but_data_grant_can_and_is_audited(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();$id=$this->ask($a,$m,$p);$this->approve($m,$id,'visual');
        $payload=['progress'=>80,'contract_value'=>120,'start_date'=>'2026-11-01','end_date'=>'2026-12-01','project_manager_id'=>$a->id];
        $this->actingAs($a)->patchJson("/api/v1/project-access/projects/{$p->id}/data",$payload)->assertForbidden();
        $this->assertSame([$p->id],app(ProjectReportAccess::class)->allowedIds($a,'visual'));
        ProjectAccessRequest::find($id)->update(['access_mode'=>'data']);
        $this->patchJson("/api/v1/project-access/projects/{$p->id}/data",$payload)->assertOk();
        $this->assertSame(80,$p->fresh()->progress);
        $this->assertSame($m->id,$p->fresh()->project_manager_id);
        $this->assertDatabaseHas('audit_logs',['action'=>'project.report_data_updated','user_id'=>$a->id]);
        $this->patchJson("/api/v1/project-access/projects/{$p->id}/data",array_replace($payload,['progress'=>101]))->assertUnprocessable();
    }
    public function test_teams_window_comes_from_microsoft_and_tracks_reschedule_cancellation_and_failure(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();$m->update(['microsoft_access_token'=>'teams-secret','microsoft_token_expires_at'=>now()->addHour()]);
        $event=['id'=>'meeting-id','subject'=>'Reunião','start'=>['dateTime'=>now()->addMinutes(5)->format('Y-m-d\TH:i:s')],'end'=>['dateTime'=>now()->addHour()->format('Y-m-d\TH:i:s')],'isOnlineMeeting'=>true,'onlineMeetingProvider'=>'teamsForBusiness','isCancelled'=>false];
        $status = 200;
        Http::fake(['https://graph.microsoft.com/*'=>function () use (&$event, &$status) { return Http::response($event, $status); }]);
        $id=$this->ask($a,$m,$p);$this->approve($m,$id,'view','meeting',['teams_event_id'=>'meeting-id']);
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $this->travel(10)->minutes();
        $this->assertSame([$p->id],app(ProjectReportAccess::class)->allowedIds($a));
        $oldStart = $event['start'];
        $event['start']['dateTime'] = now()->addMinutes(20)->format('Y-m-d\TH:i:s');
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $event['start'] = $oldStart;
        $this->travel(60)->minutes();
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $this->travel(-60)->minutes();
        $event['isCancelled']=true;
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $status = 503;
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        Http::assertSent(fn($r)=>$r->hasHeader('Authorization','Bearer teams-secret'));
    }
    public function test_all_fixed_durations_and_project_closure_revoke_access_permanently(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();
        foreach (['24h'=>24,'48h'=>48,'72h'=>72,'week'=>168,'month'=>null,'project'=>null] as $duration=>$hours) {
            $id=$this->ask($a,$m,$p);$this->approve($m,$id,'view',$duration);
            $grant=ProjectAccessRequest::find($id);
            $this->assertTrue($grant->starts_at->lte(now()));
            if ($hours) $this->assertEqualsWithDelta($hours, now()->diffInHours($grant->expires_at), .01);
            if ($duration==='month') $this->assertTrue($grant->expires_at->equalTo(now()->addMonthNoOverflow()->startOfSecond()));
            if ($duration==='project') $this->assertNull($grant->expires_at);
        }
        app(\App\Domain\Projects\Actions\FinalizeProject::class)->execute($p,$m,'Entrega concluída e validada.');
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
        $this->assertDatabaseMissing('project_access_requests',['project_id'=>$p->id,'status'=>'approved']);
        $p->forceFill(['finalized_at'=>null])->save();
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
    }

    public function test_rejection_is_final_and_notifies_requester(): void
    {
        [$c,$m,$a,$p]=$this->setupAccess();$id=$this->ask($a,$m,$p);
        $this->actingAs($m)->postJson("/api/v1/project-access/{$id}/decision",['decision'=>'reject'])->assertOk();
        $this->postJson("/api/v1/project-access/{$id}/decision",['decision'=>'reject'])->assertConflict();
        $this->assertDatabaseHas('inbox_items',['user_id'=>$a->id,'title'=>'Acesso recusado']);
        $this->assertSame([],app(ProjectReportAccess::class)->allowedIds($a));
    }
}
