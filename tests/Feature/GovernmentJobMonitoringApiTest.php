<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\JobMonitoringQuery;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobMonitoringApiTest extends GovernmentJobInspectionTestCase
{
    private const URL = '/pemerintah/api/monitoring-lowongan';

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_government_can_read_without_accept_header(?string $role, bool $active, int $status): void
    {
        $job = $this->listing(['status' => 'published']);
        $before = $job->fresh()->getRawOriginal();
        if ($role !== null) $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        $this->get(self::URL)->assertStatus($status)->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonStructure(['message', 'errors']);
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true, 401], ['pemerintah', false, 403], ['perusahaan', true, 403], ['pencari_kerja', true, 403]];
    }

    public function test_named_route_is_protected_and_only_published_survives_grouped_search(): void
    {
        $this->admin();
        $route = Route::getRoutes()->getByName('government.job-monitoring.api.index');
        $this->assertSame(url(self::URL), route('government.job-monitoring.api.index'));
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('can:access-government', $route->gatherMiddleware());
        $this->assertTrue(Route::has('government.job-monitoring.index'));
        $this->assertTrue(Route::has('government.job-monitoring.show'));
        $company = CompanyProfile::factory()->create(['company_name' => 'Sasaran Perusahaan']);
        $job = $this->listing(['status' => 'published', 'title' => 'Sasaran Judul', 'approved_by' => null], $company);
        foreach (['draft', 'pending_approval', 'rejected', 'closed'] as $status) {
            $this->listing(['status' => $status, 'title' => 'Sasaran '.$status, 'approved_by' => auth()->id()], $company);
        }
        foreach (['', '?q=Sasaran', '?q=Perusahaan', '?q=Judul', '?status=published'] as $query) {
            $this->get(self::URL.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $job->id)
                ->assertJsonPath('data.0.status', 'published')->assertHeader('Cache-Control', 'no-store, private');
        }
        $results = app(JobMonitoringQuery::class)->paginate([]);
        $this->assertTrue($results->first()->relationLoaded('company'));
    }

    public function test_deadline_and_company_verification_do_not_silently_change_read_scope(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => 'pending']);
        $ids = [];
        foreach ([today()->subDay()->toDateString(), today()->toDateString(), today()->addDay()->toDateString(), null] as $deadline) {
            $ids[] = $this->listing(['status' => 'published', 'deadline' => $deadline, 'approved_by' => null], $company)->id;
        }
        $response = $this->get(self::URL)->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('data.3.deadline', null);
        $this->assertSame($ids, array_column($response->json('data'), 'id'));
        $this->assertSame('pending', $company->fresh()->verification_status);
    }

    public function test_combined_filters_have_exact_company_category_and_persistent_pagination(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Tim Sasaran']);
        $first = $this->listing(['status' => 'published', 'title' => 'Analis Satu', 'category' => 'Teknologi', 'location' => 'Jakarta Selatan', 'created_at' => '2026-11-20'], $company);
        $second = $this->listing(['status' => 'published', 'title' => 'Analis Dua', 'category' => 'Teknologi', 'location' => 'Jakarta Selatan', 'created_at' => '2026-11-01'], $company);
        $this->listing(['status' => 'published', 'category' => 'Teknologi Lanjutan', 'location' => 'Jakarta Selatan'], $company);
        $this->listing(['status' => 'published', 'category' => 'Teknologi', 'location' => 'Bandung'], $company);
        $other = $this->listing(['status' => 'published', 'title' => 'Analis Lain', 'category' => 'Teknologi', 'location' => 'Jakarta Selatan']);
        $filters = ['q' => '  Sasaran  ', 'company' => (string) $company->id, 'category' => ' Teknologi ', 'location' => ' Selatan ', 'status' => ' published ', 'per_page' => 1];
        $response = $this->get(self::URL.'?'.http_build_query($filters))->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('filters.company', $company->id)
            ->assertJsonPath('filters.q', 'Sasaran')->assertJsonPath('filters.status', 'published');
        parse_str(parse_url($response->json('links.next'), PHP_URL_QUERY), $next);
        foreach (['q' => 'Sasaran', 'company' => (string) $company->id, 'category' => 'Teknologi', 'location' => 'Selatan', 'status' => 'published', 'per_page' => '1', 'page' => '2'] as $key => $value) {
            $this->assertSame($value, $next[$key]);
        }
        $this->get(self::URL.'?'.http_build_query($next))->assertOk()->assertJsonPath('data.0.id', $second->id);
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 999]))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 2);
        $this->get(self::URL.'?company='.$other->company_profile_id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->get(self::URL.'?company=999999')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_partial_search_treats_wildcards_literally(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Perusahaan %_!']);
        $match = $this->listing(['status' => 'published', 'title' => 'Judul %_!', 'location' => 'Kota %_!'], $company);
        $this->listing(['status' => 'published', 'title' => 'Judul lain', 'location' => 'Kota lain']);
        foreach (['q', 'location'] as $field) {
            $this->get(self::URL.'?'.http_build_query([$field => '%_!']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        }
        $this->get(self::URL.'?q=Perusahaan')->assertOk()->assertJsonPath('data.0.id', $match->id);
    }

    public function test_allowlist_empty_results_and_get_never_mutates_job_or_company(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0)->assertJsonPath('meta.per_page', 15);
        $company = CompanyProfile::factory()->create(['company_name' => 'Monitoring Sintetis', 'npwp' => 'NPWP privat sintetis', 'address' => 'Alamat privat sintetis', 'verification_notes' => 'Catatan internal sintetis', 'company_document_path' => 'private-document.pdf']);
        $job = $this->listing(['status' => 'published', 'title' => '<script>alert(1)</script>', 'approved_by' => auth()->id(), 'rejection_reason' => 'Alasan internal sintetis', 'description' => 'Deskripsi internal tidak diperlukan daftar', 'deadline' => '2027-01-31'], $company);
        $beforeJob = $job->fresh()->getRawOriginal(); $beforeCompany = $company->fresh()->getRawOriginal();
        $response = $this->get(self::URL)->assertOk()->assertHeader('Content-Type', 'application/json')->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.0.deadline', '2027-01-31')->assertJsonPath('data.0.title', '<script>alert(1)</script>');
        $this->assertSame(['id', 'title', 'company', 'category', 'location', 'status', 'deadline'], array_keys($response->json('data.0')));
        $this->assertSame(['id', 'company_name'], array_keys($response->json('data.0.company')));
        foreach (['NPWP privat sintetis', 'Alamat privat sintetis', 'Catatan internal sintetis', 'private-document.pdf', 'Alasan internal sintetis', 'Deskripsi internal tidak diperlukan daftar'] as $private) $response->assertDontSee($private);
        $this->assertSame($beforeJob, $job->fresh()->getRawOriginal());
        $this->assertSame($beforeCompany, $company->fresh()->getRawOriginal());
        $this->get(self::URL.'?q=TidakAda')->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_is_explicit_json_422(string $field, mixed $value): void
    {
        $this->admin();
        $this->get(self::URL.'?'.http_build_query([$field => $value]))->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')->assertHeader('Cache-Control', 'no-store, private')->assertJsonValidationErrors($field);
    }

    public static function invalidQueries(): array
    {
        return [
            ['q', ['bad']], ['q', str_repeat('a', 256)], ['category', ['bad']], ['category', str_repeat('a', 256)],
            ['location', ['bad']], ['location', str_repeat('a', 256)],
            ['company', 0], ['company', -1], ['company', 'abc'], ['company', ['1']], ['company', str_repeat('9', 40)],
            ['status', 'closed'], ['status', 'rejected'], ['status', 'pending_approval'], ['status', 'draft'], ['status', ['published']],
            ['page', 0], ['page', 100001], ['page', [1]], ['per_page', 0], ['per_page', 51], ['per_page', [1]],
        ];
    }

    public function test_length_page_size_boundaries_and_empty_optional_parameters(): void
    {
        $this->admin();
        $value = str_repeat('a', 255);
        $job = $this->listing(['status' => 'published', 'title' => $value, 'category' => $value, 'location' => $value]);
        $this->get(self::URL.'?'.http_build_query(['q' => $value, 'category' => $value, 'location' => $value, 'per_page' => 50]))
            ->assertOk()->assertJsonPath('data.0.id', $job->id)->assertJsonPath('meta.per_page', 50);
        $this->get(self::URL.'?q=%20%20&company=&category=&location=&status=')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('filters.status', 'published');
    }

    public function test_monitoring_has_no_detail_or_write_endpoint(): void
    {
        $this->admin();
        $job = $this->listing(['status' => 'published']);
        $before = $job->fresh()->getRawOriginal();
        foreach (['post', 'put', 'patch', 'delete'] as $method) $this->{$method}(self::URL, ['status' => 'closed'])->assertStatus(405);
        $this->get(self::URL.'/'.$job->id)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->assertSame($before, $job->fresh()->getRawOriginal());
    }
}
