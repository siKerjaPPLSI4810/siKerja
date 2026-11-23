<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobInspectionApiTest extends GovernmentJobInspectionTestCase
{
    private const URL = '/pemerintah/api/pemeriksaan-lowongan';
    private const HTML = '/pemerintah/pemeriksaan-lowongan';

    #[DataProvider('unauthorizedUsers')]
    public function test_api_denies_unauthorized_users_without_accept_header(?string $role, bool $active, int $status): void
    {
        $job = $this->listing(['title' => 'Pengajuan terbatas']);
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        foreach ([self::URL, self::URL.'/'.$job->id] as $url) {
            $this->get($url)->assertStatus($status)->assertHeader('Content-Type', 'application/json')
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonStructure(['message', 'errors'])->assertDontSee('Pengajuan terbatas');
        }
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true, 401], ['pemerintah', false, 403], ['perusahaan', true, 403], ['pencari_kerja', true, 403]];
    }

    public function test_pending_search_keeps_grouped_or_and_matches_html_results(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Cocok Perusahaan']);
        $job = $this->listing(['title' => 'Cocok Judul'], $company);
        foreach (['draft', 'published', 'rejected', 'closed'] as $status) {
            $this->listing(['status' => $status, 'title' => 'Cocok '.$status], $company);
        }
        foreach (['', '?q=Cocok', '?q=Perusahaan', '?q=Judul', '?status=published'] as $query) {
            $html = $this->get(self::HTML.$query)->assertOk();
            $this->get(self::URL.$query)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $job->id)->assertJsonPath('data.0.status', 'pending_approval')
                ->assertJsonPath('meta.total', $html->viewData('jobs')->total());
        }
    }

    public function test_combined_filters_categories_and_pagination_match_html(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Tim Sasaran']);
        $first = $this->listing(['title' => 'Lowongan Satu', 'category' => 'Teknologi', 'location' => 'Jakarta Selatan'], $company);
        $second = $this->listing(['title' => 'Lowongan Dua', 'category' => 'Teknologi', 'location' => 'Jakarta Selatan'], $company);
        $this->listing(['category' => 'Teknologi Lanjutan', 'location' => 'Jakarta Selatan'], $company);
        $this->listing(['category' => 'Teknologi', 'location' => 'Bandung'], $company);
        $this->listing(['category' => 'Keuangan']);
        $this->listing(['category' => 'Kategori Publik Saja', 'status' => 'published']);
        $filters = ['q' => '  Sasaran  ', 'category' => 'Teknologi', 'location' => 'Selatan', 'per_page' => 1];
        $query = '?'.http_build_query($filters);
        $html = $this->get(self::HTML.$query)->assertOk();
        $response = $this->get(self::URL.$query)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('meta.total', 2)
            ->assertJsonPath('filters.q', 'Sasaran')->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('categories', $html->viewData('categories')->all())
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($html->viewData('jobs')->getCollection()->modelKeys(), array_column($response->json('data'), 'id'));
        parse_str(parse_url($response->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertSame('Sasaran', $next['q']);
        $this->assertSame('Teknologi', $next['category']);
        $this->assertSame('Selatan', $next['location']);
        $this->assertSame('1', $next['per_page']);
        $this->assertSame('2', $next['page']);
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 2]))
            ->assertOk()->assertJsonPath('data.0.id', $second->id);
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 999]))
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_api_has_stable_id_order_and_literal_partial_search(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Tim %_!']);
        $first = $this->listing(['title' => 'Posisi %_!', 'location' => 'Kota %_!', 'created_at' => '2026-11-20 12:00:00'], $company);
        $second = $this->listing(['title' => 'Posisi dua', 'created_at' => '2026-11-01 12:00:00']);
        $this->get(self::URL)->assertOk()->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)->assertJsonPath('meta.per_page', 15);
        foreach (['q' => '%_!', 'location' => '%_!'] as $field => $value) {
            $this->get(self::URL.'?'.http_build_query([$field => $value]))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        }
    }

    public function test_response_uses_strict_allowlist_for_list_and_detail_and_does_not_write(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create([
            'company_name' => 'Perusahaan Detail API', 'npwp' => 'NPWP internal sintetis',
            'address' => 'Alamat internal sintetis', 'verification_notes' => 'Catatan internal sintetis',
            'company_document_path' => 'company_documents/private-file.pdf',
        ]);
        $job = $this->listing([
            'description' => "Deskripsi\nBaris kedua", 'requirements' => 'Persyaratan kerja',
            'deadline' => '2027-01-31', 'quota' => 3, 'rejection_reason' => 'Catatan keputusan lama sintetis',
        ], $company);
        $beforeJob = $job->fresh()->getRawOriginal();
        $beforeCompany = $company->fresh()->getRawOriginal();
        foreach ([self::URL, self::URL.'/'.$job->id] as $url) {
            $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/json')
                ->assertHeader('Cache-Control', 'no-store, private');
            $data = $url === self::URL ? $response->json('data.0') : $response->json('data');
            $this->assertSame(['id', 'title', 'status', 'company', 'category', 'job_type', 'location', 'description',
                'education_requirement', 'requirements', 'salary_around', 'quota', 'deadline'], array_keys($data));
            $this->assertSame(['id', 'company_name'], array_keys($data['company']));
            $this->assertSame($company->id, $data['company']['id']);
            $this->assertSame('Perusahaan Detail API', $data['company']['company_name']);
            $this->assertSame("Deskripsi\nBaris kedua", $data['description']);
            $this->assertSame('8000000.00', $data['salary_around']);
            $this->assertSame(3, $data['quota']);
            $this->assertSame('2027-01-31', $data['deadline']);
            foreach (['NPWP internal sintetis', 'Alamat internal sintetis', 'Catatan internal sintetis',
                'private-file.pdf', 'Catatan keputusan lama sintetis'] as $secret) {
                $response->assertDontSee($secret);
            }
        }
        $this->assertSame($beforeJob, $job->fresh()->getRawOriginal());
        $this->assertSame($beforeCompany, $company->fresh()->getRawOriginal());
    }

    #[DataProvider('otherStatuses')]
    public function test_detail_only_allows_pending(string $status): void
    {
        $this->admin();
        $job = $this->listing(['status' => $status]);
        $this->get(self::URL.'/'.$job->id)->assertNotFound()->assertExactJson([
            'message' => 'Data tidak ditemukan.', 'errors' => [],
        ])->assertHeader('Cache-Control', 'no-store, private');
    }

    public static function otherStatuses(): array
    {
        return [['draft'], ['published'], ['rejected'], ['closed'], ['unknown']];
    }

    #[DataProvider('missingIdentifiers')]
    public function test_invalid_or_missing_id_returns_json_404(string $identifier): void
    {
        $this->admin();
        $this->get(self::URL.'/'.$identifier)->assertNotFound()->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('message', 'Data tidak ditemukan.');
    }

    public static function missingIdentifiers(): array
    {
        return [['99999'], ['0'], ['abc'], ['-1'], ['1.5'], [str_repeat('9', 40)]];
    }

    public function test_empty_results_nullable_values_and_json_text_are_preserved(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0)->assertJsonPath('categories', []);
        $html = '<script>alert(1)</script>';
        $job = $this->listing(['title' => $html, 'salary_around' => null, 'deadline' => null]);
        $this->get(self::URL.'?q=notfound')->assertOk()->assertJsonCount(0, 'data');
        $this->get(self::URL.'/'.$job->id)->assertOk()->assertJsonPath('data.title', $html)
            ->assertJsonPath('data.salary_around', null)->assertJsonPath('data.deadline', null)
            ->assertHeader('Content-Type', 'application/json');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_gets_json_validation_without_accept_header(string $field, mixed $value): void
    {
        $this->admin();
        $this->get(self::URL.'?'.http_build_query([$field => $value]))->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonValidationErrors($field);
    }

    public static function invalidQueries(): array
    {
        return [
            ['q', ['bad']], ['q', str_repeat('a', 256)], ['category', ['bad']], ['category', str_repeat('a', 256)],
            ['location', ['bad']], ['location', str_repeat('a', 256)], ['page', 0], ['page', 100001],
            ['page', 'invalid'], ['per_page', 0], ['per_page', 51], ['per_page', [1]],
        ];
    }

    public function test_api_filter_length_boundary_and_maximum_page_size(): void
    {
        $this->admin();
        $value = str_repeat('a', 255);
        $job = $this->listing(['title' => $value, 'category' => $value, 'location' => $value]);
        $this->get(self::URL.'?'.http_build_query(['q' => $value, 'category' => $value, 'location' => $value, 'per_page' => 50]))
            ->assertOk()->assertJsonPath('meta.per_page', 50)->assertJsonPath('data.0.id', $job->id);
    }

    public function test_read_endpoints_reject_write_methods_and_cannot_mutate_job(): void
    {
        $this->admin();
        $job = $this->listing();
        $before = $job->fresh()->getRawOriginal();
        foreach ([['post', self::URL], ['put', self::URL.'/'.$job->id], ['patch', self::URL.'/'.$job->id],
            ['delete', self::URL.'/'.$job->id]] as [$method, $url]) {
            $this->{$method}($url, ['status' => 'published'])->assertStatus(405);
        }
        $this->assertSame($before, $job->fresh()->getRawOriginal());
        $this->assertSame(1, JobPosting::count());
    }
}
