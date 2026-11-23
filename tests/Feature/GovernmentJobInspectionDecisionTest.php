<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\JobInspectionDecisionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobInspectionDecisionTest extends GovernmentJobInspectionTestCase
{
    private function job(array $changes = [], ?CompanyProfile $company = null): JobPosting
    {
        $company ??= CompanyProfile::factory()->create(['verification_status' => 'verified']);

        return $this->listing(array_merge([
            'title' => 'Pengembang Aplikasi', 'description' => 'Membangun dan merawat aplikasi perusahaan.',
            'requirements' => 'Menguasai pengembangan aplikasi dan bekerja dalam tim.',
            'deadline' => today()->addDays(2)->toDateString(),
        ], $changes), $company);
    }

    private function endpoint(JobPosting|string $job): string
    {
        return '/pemerintah/api/pemeriksaan-lowongan/'.($job instanceof JobPosting ? $job->id : $job).'/keputusan';
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_government_can_store(?string $role, bool $active, int $status): void
    {
        $job = $this->job();
        $before = $job->fresh()->getRawOriginal();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $this->post($this->endpoint($job), ['action' => 'approve'])->assertStatus($status)
            ->assertHeader('Content-Type', 'application/json')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true, 401], ['pemerintah', false, 403], ['perusahaan', true, 403], ['pencari_kerja', true, 403]];
    }

    public function test_approve_sets_server_approver_clears_reason_and_publishes_without_changing_company(): void
    {
        $this->admin();
        $job = $this->job(['approved_by' => User::factory()->create()->id, 'rejection_reason' => 'Catatan lama']);
        $beforeCompany = $job->company->getRawOriginal();
        $response = $this->post($this->endpoint($job), ['action' => 'approve', 'rejection_reason' => 'Abaikan catatan input']);
        $response->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.approved_by', auth()->id())
            ->assertJsonPath('data.rejection_reason', null)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['id', 'status', 'approved_by', 'rejection_reason'], array_keys($response->json('data')));
        $this->assertSame('published', $job->fresh()->status);
        $this->assertSame(auth()->id(), $job->fresh()->approved_by);
        $this->assertSame($beforeCompany, $job->company->fresh()->getRawOriginal());
        $this->get('/lowongan/'.$job->id)->assertOk();
        $this->get('/pemerintah/api/pemeriksaan-lowongan/'.$job->id)->assertNotFound();
    }

    #[DataProvider('negativeActions')]
    public function test_reject_and_request_changes_store_trimmed_note_and_clear_previous_approval(string $action): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => 'pending']);
        $job = $this->job(['approved_by' => User::factory()->create()->id, 'deadline' => null, 'description' => ''], $company);
        $this->post($this->endpoint($job), ['action' => $action, 'rejection_reason' => "  Perbaiki informasi lowongan. \n"])
            ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.approved_by', null)
            ->assertJsonPath('data.rejection_reason', 'Perbaiki informasi lowongan.');
        $this->assertSame('Perbaiki informasi lowongan.', $job->fresh()->rejection_reason);
        $this->assertNull($job->fresh()->approved_by);
        $this->assertSame('pending', $company->fresh()->verification_status);
        $this->get('/lowongan/'.$job->id)->assertNotFound();
    }

    public static function negativeActions(): array
    {
        return [['reject'], ['request_changes']];
    }

    #[DataProvider('unverifiedCompanies')]
    public function test_unverified_company_cannot_be_approved_or_automatically_rejected(string $status): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => $status]);
        $job = $this->job([], $company);
        $before = $job->fresh()->getRawOriginal();
        $this->post($this->endpoint($job), ['action' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors('company');
        $this->assertSame($before, $job->fresh()->getRawOriginal());
        $this->assertSame($status, $company->fresh()->verification_status);
    }

    public static function unverifiedCompanies(): array
    {
        return [['pending'], ['rejected'], ['unknown']];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_never_changes_data(array $input, string $field): void
    {
        $this->admin();
        $job = $this->job(['rejection_reason' => 'Alasan lama']);
        $before = $job->fresh()->getRawOriginal();
        $this->post($this->endpoint($job), $input)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public static function invalidPayloads(): array
    {
        return [
            [[], 'action'], [['action' => 'other'], 'action'], [['action' => ['approve']], 'action'],
            [['action' => 'reject'], 'rejection_reason'], [['action' => 'reject', 'rejection_reason' => " \n\t "], 'rejection_reason'],
            [['action' => 'request_changes'], 'rejection_reason'], [['action' => 'request_changes', 'rejection_reason' => '   '], 'rejection_reason'],
            [['action' => 'reject', 'rejection_reason' => ['bad']], 'rejection_reason'],
            [['action' => 'reject', 'rejection_reason' => str_repeat('a', 1001)], 'rejection_reason'],
            [['action' => 'approve', 'approved_by' => 999], 'approved_by'], [['action' => 'approve', 'status' => 'published'], 'status'],
            [['action' => 'approve', 'company_profile_id' => 999], 'company_profile_id'],
        ];
    }

    #[DataProvider('invalidStoredJobs')]
    public function test_approval_revalidates_existing_company_rules(string $field, mixed $value): void
    {
        $this->admin();
        if ($field === 'deadline' && in_array($value, ['__today__', '__yesterday__'], true)) {
            $value = $value === '__today__' ? today()->toDateString() : today()->subDay()->toDateString();
        }
        $job = $this->job([$field => $value]);
        $before = $job->fresh()->getRawOriginal();
        $this->post($this->endpoint($job), ['action' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public static function invalidStoredJobs(): array
    {
        return [
            ['title', ''], ['title', 'Programmer 123'], ['category', ''], ['category', str_repeat('a', 101)],
            ['job_type', ''], ['location', 'Jakarta 123'], ['education_requirement', ''],
            ['description', 'Short'], ['requirements', 'Short'], ['quota', 0],
            ['salary_around', 999999], ['salary_around', null],
            ['deadline', null], ['deadline', '__today__'], ['deadline', '__yesterday__'],
        ];
    }

    public function test_rejection_reason_boundary_and_correction_length_are_not_silently_conflated(): void
    {
        $this->admin();
        $reason = str_repeat('a', 1000);
        $this->post($this->endpoint($this->job()), ['action' => 'reject', 'rejection_reason' => ' '.$reason.' '])
            ->assertOk()->assertJsonPath('data.rejection_reason', $reason);
        // The working instruction fixes 1000 for rejection only, not a new limit for correction notes.
        $this->post($this->endpoint($this->job()), ['action' => 'request_changes', 'rejection_reason' => str_repeat('b', 1001)])
            ->assertOk();
    }

    #[DataProvider('otherStatuses')]
    public function test_only_pending_status_can_receive_a_decision(string $status): void
    {
        $this->admin();
        $job = $this->job(['status' => $status]);
        $before = $job->fresh()->getRawOriginal();
        $this->post($this->endpoint($job), ['action' => 'approve'])->assertStatus(409)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public static function otherStatuses(): array
    {
        return [['draft'], ['published'], ['rejected'], ['closed']];
    }

    public function test_second_reviewer_or_replayed_request_cannot_overwrite_first_decision(): void
    {
        $this->admin();
        $job = $this->job();
        $this->post($this->endpoint($job), ['action' => 'approve'])->assertOk();
        $first = $job->fresh()->getRawOriginal();
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]));
        foreach ([['action' => 'approve'], ['action' => 'reject', 'rejection_reason' => 'Keputusan kedua'],
            ['action' => 'request_changes', 'rejection_reason' => 'Perubahan kedua']] as $input) {
            $this->post($this->endpoint($job), $input)->assertStatus(409);
            $this->assertSame($first, $job->fresh()->getRawOriginal());
        }
    }

    public function test_transaction_rolls_back_when_database_write_fails(): void
    {
        $this->admin();
        $job = $this->job(['rejection_reason' => 'Sebelum transaksi']);
        $before = $job->fresh()->getRawOriginal();
        DB::unprepared("CREATE TRIGGER fail_decision AFTER UPDATE ON job_postings WHEN NEW.status <> OLD.status BEGIN SELECT RAISE(ABORT, 'synthetic decision failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post($this->endpoint($job), ['action' => 'approve']);
            $this->fail('Expected database failure.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('synthetic decision failure', $exception->getMessage());
        }
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public function test_service_enforces_authorization_and_validates_input_when_reused_without_http(): void
    {
        $job = $this->job();
        $companyUser = User::factory()->create(['role' => 'perusahaan']);
        try {
            app(JobInspectionDecisionService::class)->record((string) $job->id, $companyUser, ['action' => 'approve']);
            $this->fail('Expected forbidden service call.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertSame('pending_approval', $job->fresh()->status);
        }
        $reviewer = User::factory()->create(['role' => 'pemerintah', 'is_active' => true]);
        try {
            app(JobInspectionDecisionService::class)->record((string) $job->id, $reviewer, ['action' => 'reject', 'rejection_reason' => '  ']);
            $this->fail('Expected invalid reason.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('rejection_reason', $exception->errors());
        }
        $this->assertSame('pending_approval', $job->fresh()->status);
    }

    public function test_csrf_blocks_missing_token_and_allows_matching_session_token(): void
    {
        $this->admin();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            fn ($app) => new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
                protected function runningUnitTests() { return false; }
            });
        $job = $this->job();
        $this->post($this->endpoint($job), ['action' => 'approve'])->assertStatus(419);
        $this->assertSame('pending_approval', $job->fresh()->status);
        $this->withSession(['_token' => 'synthetic-csrf'])->post($this->endpoint($job), ['action' => 'approve', '_token' => 'synthetic-csrf'])
            ->assertOk()->assertJsonPath('data.status', 'published');
    }

    #[DataProvider('missingIds')]
    public function test_invalid_or_missing_identifier_returns_json_404(string $identifier): void
    {
        $this->admin();
        $this->post($this->endpoint($identifier), ['action' => 'approve'])->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public static function missingIds(): array
    {
        return [['999999'], ['abc'], [str_repeat('9', 40)]];
    }

    public function test_company_form_still_uses_same_validation_and_workflow(): void
    {
        $company = CompanyProfile::factory()->create();
        $this->actingAs($company->user);
        $input = [
            'title' => 'Pengembang Aplikasi', 'category' => 'Teknologi Informasi', 'job_type' => 'Kontrak',
            'location' => 'Bandung', 'education_requirement' => 'S1', 'description' => 'Deskripsi pekerjaan lebih dari dua puluh karakter.',
            'requirements' => 'Persyaratan pekerjaan lebih dari dua puluh karakter.', 'quota' => 1,
            'salary_around' => 1000000, 'deadline' => today()->addDay()->toDateString(),
        ];
        $this->post(route('company.jobs.store'), array_merge($input, ['description' => 'Short']))->assertSessionHasErrors('description');
        $this->assertSame(0, JobPosting::count());
        $this->post(route('company.jobs.store'), $input)->assertRedirect(route('company.jobs.index'));
        $this->assertSame('pending_approval', JobPosting::first()->status);
        $this->assertSame('pending', $company->fresh()->verification_status);
    }
}
