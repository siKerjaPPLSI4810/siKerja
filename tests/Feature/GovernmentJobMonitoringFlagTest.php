<?php

namespace Tests\Feature;

use App\Models\JobPosting;
use App\Models\JobPostingReviewFlag;
use App\Models\User;
use App\Services\JobPostingReviewFlagService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobMonitoringFlagTest extends GovernmentJobInspectionTestCase
{
    private function url(JobPosting|string $job): string
    {
        return '/pemerintah/api/monitoring-lowongan/'.($job instanceof JobPosting ? $job->id : $job).'/penandaan';
    }

    private function job(): JobPosting
    {
        return $this->listing(['status'=>'published','approved_by'=>User::factory()->create()->id,'rejection_reason'=>'Metadata persetujuan lama','deadline'=>null]);
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_authorization_for_mark_and_clear(?string $role,bool $active,int $status): void
    {
        $job=$this->job(); $before=$job->fresh()->getRawOriginal();
        if ($role!==null) $this->actingAs(User::factory()->create(['role'=>$role,'is_active'=>$active]));
        foreach (['post','delete'] as $method) {
            $this->{$method}($this->url($job),$method==='post'?['reason'=>'Alasan sintetis']:[])->assertStatus($status)->assertHeader('Content-Type','application/json');
        }
        $this->assertSame(0,JobPostingReviewFlag::count()); $this->assertSame($before,$job->fresh()->getRawOriginal());
    }

    public static function unauthorizedUsers(): array
    {
        return [[null,true,401],['pemerintah',false,403],['perusahaan',true,403],['pencari_kerja',true,403]];
    }

    #[DataProvider('invalidReasons')]
    public function test_reason_validation_never_changes_data(mixed $reason): void
    {
        $this->admin(); $job=$this->job(); $before=$job->fresh()->getRawOriginal();
        $this->post($this->url($job),['reason'=>$reason])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(0,JobPostingReviewFlag::count()); $this->assertSame($before,$job->fresh()->getRawOriginal());
    }

    public static function invalidReasons(): array
    {
        return [[null],[''],[" \n\t "],[str_repeat('a',1001)],[['bad']]];
    }

    #[DataProvider('forgedFields')]
    public function test_actor_time_job_and_approval_cannot_be_spoofed(string $field): void
    {
        $this->admin(); $job=$this->job();
        $this->post($this->url($job),['reason'=>'Perlu pemeriksaan',$field=>'999'])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0,JobPostingReviewFlag::count());
        $this->delete($this->url($job),[$field=>'999'])->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function forgedFields(): array
    {
        return array_map(fn($field)=>[$field],['flagged_by','flagged_at','cleared_by','cleared_at','job_posting_id','status','approved_by','rejection_reason']);
    }

    public function test_mark_clear_repeat_clear_and_reflag_keep_one_row_and_last_cycle(): void
    {
        $this->admin(); $job=$this->job(); $before=$job->fresh()->getRawOriginal(); $firstActor=auth()->id();
        $this->travelTo(now()->startOfSecond());
        $this->post($this->url($job),['reason'=>'  Periksa keterangan lowongan.  '])->assertOk()->assertJsonPath('data.active',true)->assertHeader('Cache-Control','no-store, private');
        $flag=JobPostingReviewFlag::sole(); $id=$flag->id;
        $this->assertSame('Periksa keterangan lowongan.',$flag->reason); $this->assertSame($firstActor,$flag->flagged_by);
        $this->assertTrue($flag->flagged_at->equalTo(now())); $this->assertNull($flag->cleared_at); $this->assertNull($flag->cleared_by);
        $marked=$flag->getRawOriginal();
        $this->post($this->url($job),['reason'=>'Tidak boleh menimpa'])->assertStatus(409);
        $this->assertSame($marked,$flag->fresh()->getRawOriginal());
        $second=User::factory()->create(['role'=>'pemerintah','is_active'=>true]); $this->actingAs($second); $this->travel(1)->minutes();
        $this->delete($this->url($job))->assertOk()->assertJsonPath('data.active',false);
        $flag->refresh(); $this->assertFalse($flag->isActive()); $this->assertSame($second->id,$flag->cleared_by); $this->assertTrue($flag->cleared_at->equalTo(now()));
        $cleared=$flag->getRawOriginal(); $this->travel(1)->minutes();
        $this->delete($this->url($job))->assertOk(); $this->assertSame($cleared,$flag->fresh()->getRawOriginal());
        $this->post($this->url($job),['reason'=>str_repeat('a',1000)])->assertOk();
        $flag->refresh(); $this->assertSame($id,$flag->id); $this->assertSame(1,JobPostingReviewFlag::count());
        $this->assertSame($second->id,$flag->flagged_by); $this->assertTrue($flag->flagged_at->equalTo(now()));
        $this->assertNull($flag->cleared_by); $this->assertNull($flag->cleared_at); $this->assertSame(str_repeat('a',1000),$flag->reason);
        $this->assertSame($before,$job->fresh()->getRawOriginal());
    }

    public function test_clear_without_flag_is_noop(): void
    {
        $this->admin(); $job=$this->job(); $before=$job->fresh()->getRawOriginal();
        $this->delete($this->url($job))->assertOk()->assertJsonPath('data.active',false);
        $this->assertSame(0,JobPostingReviewFlag::count()); $this->assertSame($before,$job->fresh()->getRawOriginal());
    }

    #[DataProvider('otherStatuses')]
    public function test_nonpublished_cannot_be_marked(string $status): void
    {
        $this->admin(); $job=$this->listing(['status'=>$status]);
        $this->post($this->url($job),['reason'=>'Pemeriksaan'])->assertStatus(409); $this->assertSame(0,JobPostingReviewFlag::count());
    }

    public static function otherStatuses(): array
    {
        return [['pending_approval'],['draft'],['rejected'],['closed']];
    }

    #[DataProvider('missingIds')]
    public function test_missing_or_invalid_job_is_404(string $id): void
    {
        $this->admin(); $this->post($this->url($id),['reason'=>'Pemeriksaan'])->assertNotFound(); $this->delete($this->url($id))->assertNotFound();
    }

    public static function missingIds(): array
    {
        return [['999999'],['abc'],[str_repeat('9',40)]];
    }

    public function test_status_changes_retain_flag_and_republication_shows_same_flag(): void
    {
        $this->admin(); $job=$this->job(); $this->post($this->url($job),['reason'=>'Catatan tetap'])->assertOk();
        $flag=JobPostingReviewFlag::sole(); $before=$flag->getRawOriginal();
        foreach (['closed','pending_approval','published'] as $status) {
            $job->update(['status'=>$status]); $this->assertSame($before,$flag->fresh()->getRawOriginal());
        }
        $this->get(route('government.job-monitoring.show',$job))->assertOk()->assertSee('Perlu Ditinjau')->assertSee('Catatan tetap');
        $job->update(['status'=>'closed']); $this->delete($this->url($job))->assertOk();
        $this->assertFalse($flag->fresh()->isActive()); $this->assertSame('closed',$job->fresh()->status);
    }

    public function test_browser_forms_feedback_real_data_reload_and_privacy(): void
    {
        $this->admin(); $job=$this->job(); $before=$job->fresh()->getRawOriginal(); $reason='<script>alert("catatan privat")</script>';
        $detail=route('government.job-monitoring.show',$job); $headers=['Accept'=>'text/html'];
        $this->get($detail)->assertOk()->assertSee('Tandai untuk Ditinjau')->assertSee('name="_token"',false)->assertDontSee('Hapus Tanda');
        $this->post($this->url($job),['reason'=>'   ','_flag_form'=>'1'],$headers)->assertRedirect($detail)->assertSessionHasErrors('reason');
        $this->post($this->url($job),['reason'=>$reason,'_flag_form'=>'1'],$headers)->assertRedirect($detail)->assertSessionHas('status','Lowongan berhasil ditandai untuk ditinjau.');
        $this->get($detail)->assertOk()->assertSee($reason)->assertDontSee($reason,false)->assertSee(auth()->user()->name)->assertSee('Waktu penandaan')->assertSee('Hapus Tanda')->assertSee('window.confirm',false)->assertSee('value="DELETE"',false)->assertDontSee('Tandai untuk Ditinjau');
        $this->get(route('government.job-monitoring.index'))->assertOk()->assertSee('Perlu Ditinjau')->assertDontSee($reason);
        $this->post($this->url($job),['reason'=>'Tanda kedua','_flag_form'=>'1'],$headers)->assertRedirect($detail)->assertSessionHasErrors('flag');
        $this->get('/pemerintah/api/monitoring-lowongan')->assertOk()->assertDontSee('catatan privat')->assertDontSee('flagged_by');
        $this->get('/lowongan/'.$job->id)->assertOk()->assertDontSee('catatan privat')->assertDontSee('Perlu Ditinjau');
        $this->assertArrayNotHasKey('review_flag',$job->load('reviewFlag')->toArray());
        $this->assertArrayNotHasKey('reason',JobPostingReviewFlag::sole()->toArray());
        $this->delete($this->url($job),['_flag_form'=>'1'],$headers)->assertRedirect($detail);
        $this->get($detail)->assertOk()->assertSee('Tidak ada tanda tinjauan aktif')->assertSee('Tanda terakhir dicabut oleh')->assertSee('Tandai untuk Ditinjau')->assertDontSee('Hapus Tanda')->assertDontSee('catatan privat');
        $this->get(route('government.job-monitoring.index'))->assertOk()->assertDontSee('Perlu Ditinjau');
        $this->assertSame($before,$job->fresh()->getRawOriginal());
        $this->actingAs($job->company->user);
        $this->get(route('company.jobs.index'))->assertOk()->assertDontSee('catatan privat')->assertDontSee('Perlu Ditinjau');
    }

    public function test_real_csrf_blocks_missing_token_and_accepts_browser_form_payload(): void
    {
        $this->admin(); $job=$this->job();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            fn($app)=>new class($app,$app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery { protected function runningUnitTests() { return false; } });
        $this->post($this->url($job),['reason'=>'Periksa'])->assertStatus(419);
        $this->withSession(['_token'=>'synthetic-flag-token'])->post($this->url($job),['reason'=>'Periksa','_token'=>'synthetic-flag-token'])->assertOk();
        $this->delete($this->url($job))->assertStatus(419);
        $this->post($this->url($job),['_method'=>'DELETE','_token'=>'synthetic-flag-token','_flag_form'=>'1'],['Accept'=>'text/html'])->assertRedirect(route('government.job-monitoring.show',$job));
        $this->assertFalse(JobPostingReviewFlag::sole()->isActive());
    }

    public function test_unique_constraint_and_rollback_protect_existing_flag(): void
    {
        $this->admin(); $job=$this->job(); $this->post($this->url($job),['reason'=>'Pertama'])->assertOk();
        $flag=JobPostingReviewFlag::sole(); $before=$flag->getRawOriginal();
        try { JobPostingReviewFlag::create(['job_posting_id'=>$job->id,'reason'=>'Duplikat','flagged_by'=>auth()->id(),'flagged_at'=>now()]); $this->fail('Expected unique constraint'); }
        catch(QueryException $e) { $this->assertSame(1,JobPostingReviewFlag::count()); }
        DB::unprepared("CREATE TRIGGER fail_flag AFTER UPDATE ON job_posting_review_flags BEGIN SELECT RAISE(ABORT, 'synthetic flag failure'); END");
        try { app(JobPostingReviewFlagService::class)->clear((string)$job->id,auth()->user()); $this->fail('Expected rollback'); }
        catch(QueryException $e) { $this->assertStringContainsString('synthetic flag failure',$e->getMessage()); }
        $this->assertSame($before,$flag->fresh()->getRawOriginal());
    }

    public function test_service_cannot_bypass_government_authorization(): void
    {
        $job=$this->job(); $actor=User::factory()->create(['role'=>'perusahaan']);
        foreach (['mark','clear'] as $method) {
            try { app(JobPostingReviewFlagService::class)->{$method}((string)$job->id,$actor,['reason'=>'Pemeriksaan']); $this->fail('Expected forbidden service call'); }
            catch(\Illuminate\Auth\Access\AuthorizationException $e) { $this->assertSame(0,JobPostingReviewFlag::count()); }
        }
    }
}
