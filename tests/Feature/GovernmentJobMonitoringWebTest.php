<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobMonitoringWebTest extends GovernmentJobInspectionTestCase
{
    private const URL = '/pemerintah/monitoring-lowongan';

    private function job(array $attributes = [], ?CompanyProfile $company = null): JobPosting
    {
        return $this->listing(array_merge(['status' => 'published', 'title' => 'Pengembang Monitoring'], $attributes), $company);
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_list_and_detail_require_active_government(?string $role, bool $active): void
    {
        $job = $this->job();
        if ($role !== null) $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        foreach ([self::URL, self::URL.'/'.$job->id] as $url) {
            $response = $this->get($url)->assertDontSee('Pengembang Monitoring');
            $role === null ? $response->assertRedirect(route('login')) : $response->assertForbidden();
        }
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true], ['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    public function test_named_routes_navigation_and_published_scope_match_api_without_mutation(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Sasaran']);
        $job = $this->job(['approved_by' => null, 'deadline' => today()->subDay()->toDateString()], $company);
        foreach (['pending_approval', 'draft', 'rejected', 'closed'] as $status) $this->listing(['status' => $status], $company);
        $before = $job->fresh()->getRawOriginal();
        foreach (['index', 'show'] as $action) {
            $route = Route::getRoutes()->getByName('government.job-monitoring.'.$action);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('can:access-government', $route->gatherMiddleware());
        }
        $response = $this->get(route('government.job-monitoring.index'))->assertOk()->assertViewIs('government.job-monitoring.index')
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Pengembang Monitoring')
            ->assertSee(route('government.job-monitoring.show', $job), false)
            ->assertSee(route('government.company-inspections.index'), false)
            ->assertSee(route('government.company-verification-statuses.index'), false)
            ->assertSee(route('government.job-inspections.index'), false);
        $this->assertSame([$job->id], $response->viewData('jobs')->getCollection()->modelKeys());
        $api = $this->get('/pemerintah/api/monitoring-lowongan')->assertOk();
        $this->assertSame([$job->id], array_column($api->json('data'), 'id'));
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Monitoring Lowongan<\/a>/', $response->getContent());
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public function test_detail_displays_all_job_information_and_back_link_without_decision_controls(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Detail Monitoring']);
        $job = $this->job(['category' => 'Teknologi', 'location' => 'Bandung', 'job_type' => 'Kontrak',
            'description' => "Membangun aplikasi\nMerawat layanan", 'requirements' => "Menguasai Laravel\nBekerja dalam tim",
            'education_requirement' => 'D4/S1', 'quota' => 3, 'salary_around' => 8000000, 'deadline' => '2020-01-31'], $company);
        $beforeJob = $job->fresh()->getRawOriginal(); $beforeCompany = $company->fresh()->getRawOriginal();
        $response = $this->get(route('government.job-monitoring.show', $job))->assertOk()->assertViewIs('government.job-monitoring.show')
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Pengembang Monitoring')->assertSee('Perusahaan Detail Monitoring')
            ->assertSee('Teknologi')->assertSee('Bandung')->assertSee('Kontrak')->assertSee('D4/S1')
            ->assertSee("Membangun aplikasi\nMerawat layanan")->assertSee("Menguasai Laravel\nBekerja dalam tim")
            ->assertSee('Rp 8.000.000')->assertSee('3 orang')->assertSee('31 Jan 2020')->assertSee('datetime="2020-01-31"', false)
            ->assertSee('Disetujui / Tayang')->assertSee('Kembali ke daftar monitoring')->assertSee(route('government.job-monitoring.index'), false)
            ->assertDontSee('Setujui Lowongan')->assertDontSee('data-job-decision-panel', false);
        $this->assertTrue($response->viewData('job')->relationLoaded('company'));
        $this->assertSame($beforeJob, $job->fresh()->getRawOriginal());
        $this->assertSame($beforeCompany, $company->fresh()->getRawOriginal());
    }

    #[DataProvider('otherStatuses')]
    public function test_detail_of_other_status_is_404(string $status): void
    {
        $this->admin(); $job = $this->job(['status' => $status]);
        $this->get(self::URL.'/'.$job->id)->assertNotFound()->assertDontSee('Pengembang Monitoring');
        $this->assertSame($status, $job->fresh()->status);
    }

    public static function otherStatuses(): array
    {
        return [['draft'], ['pending_approval'], ['rejected'], ['closed'], ['unknown']];
    }

    #[DataProvider('invalidIds')]
    public function test_invalid_and_missing_detail_id_is_404(string $id): void
    {
        $this->admin(); $this->get(self::URL.'/'.$id)->assertNotFound();
    }

    public static function invalidIds(): array
    {
        return [['999999'], ['abc'], ['-1'], ['1.5'], [str_repeat('9', 40)]];
    }

    public function test_search_pagination_and_filter_options_use_shared_query(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Sasaran']);
        $first = $this->job(['title' => 'Analis Satu', 'category' => 'Teknologi'], $company);
        $second = $this->job(['title' => 'Analis Dua', 'category' => 'Teknologi'], $company);
        $other = $this->job(['category' => 'Keuangan']);
        $this->listing(['title' => 'Analis pending'], $company);
        $filters = ['q' => 'Sasaran', 'per_page' => 1];
        $response = $this->get(self::URL.'?'.http_build_query($filters))->assertOk()->assertSee('pagination')
            ->assertSee('Analis Satu')->assertDontSee('Analis Dua')->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 2);
        $this->assertSame(['Keuangan', 'Teknologi'], $response->viewData('categories')->all());
        $this->assertCount(2, $response->viewData('companies'));
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 2]))->assertOk()->assertViewHas('jobs', fn ($jobs) => $jobs->first()->id === $second->id);
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 999]))->assertRedirect(route('government.job-monitoring.index', ['q' => 'Sasaran', 'company' => null, 'category' => '', 'location' => '', 'status' => 'published', 'per_page' => 1, 'page' => 2]));
        parse_str(parse_url($response->viewData('jobs')->nextPageUrl(), PHP_URL_QUERY), $next);
        $this->assertSame('Sasaran', $next['q']); $this->assertSame('2', $next['page']);
        $this->get(self::URL.'?q=Analis')->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 2);
    }

    public function test_empty_queue_and_filtered_empty_are_distinct_despite_default_published_status(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertSee('Belum ada lowongan dalam daftar monitoring')->assertDontSee('Tidak ada lowongan yang sesuai');
        $this->job();
        $this->get(self::URL.'?q=TidakAda')->assertOk()->assertSee('Tidak ada lowongan yang sesuai')->assertSee('Reset filter');
        $this->get(self::URL.'?status=published')->assertOk()->assertDontSee('1 lowongan sesuai filter');
    }

    public function test_list_and_detail_escape_dynamic_html_and_hide_private_data(): void
    {
        $this->admin(); $markup = '<script>alert(1)</script>';
        $company = CompanyProfile::factory()->create(['company_name' => $markup, 'verification_notes' => 'Catatan rahasia sintetis', 'company_document_path' => 'private-monitoring-document.pdf']);
        $job = $this->job(array_fill_keys(['title', 'category', 'location', 'description', 'requirements', 'education_requirement', 'job_type'], $markup) + ['rejection_reason' => 'Alasan rahasia sintetis'], $company);
        foreach ([self::URL, self::URL.'/'.$job->id] as $url) {
            $this->get($url)->assertOk()->assertSee($markup)->assertDontSee($markup, false)
                ->assertDontSee('Catatan rahasia sintetis')->assertDontSee('private-monitoring-document.pdf')->assertDontSee('Alasan rahasia sintetis');
        }
    }

    public function test_optional_empty_fields_have_fallback_without_fabricated_salary_or_date(): void
    {
        $this->admin(); $company = CompanyProfile::factory()->create(['company_name' => '']);
        $job = $this->job(array_fill_keys(['title', 'category', 'location', 'description', 'requirements', 'education_requirement', 'job_type'], '') + ['salary_around' => null, 'deadline' => null, 'created_at' => '2020-01-01'], $company);
        $response = $this->get(self::URL.'/'.$job->id)->assertOk()->assertDontSee('Rp 0')->assertDontSee('01 Jan 2020');
        $this->assertGreaterThanOrEqual(9, substr_count($response->getContent(), 'Belum tersedia'));
    }

    public function test_detail_rechecks_status_after_list_was_loaded(): void
    {
        $this->admin(); $job = $this->job();
        $this->get(self::URL)->assertOk()->assertSee(route('government.job-monitoring.show', $job), false);
        $job->update(['status' => 'closed']); $this->get(self::URL.'/'.$job->id)->assertNotFound();
        $this->assertSame('closed', $job->fresh()->status);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_html_query_uses_existing_validation_feedback(string $field, mixed $value): void
    {
        $this->admin();
        $this->from(self::URL)->get(self::URL.'?'.http_build_query([$field => $value]))->assertRedirect(self::URL)->assertSessionHasErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [['q', ['bad']], ['q', str_repeat('a', 256)], ['page', 0], ['status', 'closed'], ['company', 'abc']];
    }
}
