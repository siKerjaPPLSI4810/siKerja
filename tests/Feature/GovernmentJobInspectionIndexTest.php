<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobInspectionIndexTest extends GovernmentJobInspectionTestCase
{
    private const URL = '/pemerintah/pemeriksaan-lowongan';

    public function test_active_government_can_read_named_route_and_existing_navigation(): void
    {
        $this->admin();
        $job = $this->listing();
        $before = $job->fresh()->getRawOriginal();
        $this->assertSame(url(self::URL), route('government.job-inspections.index'));
        $this->get(self::URL)->assertOk()->assertViewIs('government.job-inspections.index')
            ->assertSee('Lowongan antrean')->assertSee('Pemeriksaan lowongan')
            ->assertSee(route('government.company-inspections.index'), false)
            ->assertSee(route('government.company-verification-statuses.index'), false)
            ->assertDontSee('Detail belum tersedia')->assertSee('Belum tersedia')
            ->assertSee(route('government.job-inspections.show', ['jobPosting' => $job->id]), false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($before, $job->fresh()->getRawOriginal());
        $this->assertTrue(Route::has('government.job-inspections.show'));
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_government_can_read_queue(?string $role, bool $active): void
    {
        $this->listing(['title' => 'Rahasia antrean']);
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $response = $this->get(self::URL);
        $role === null ? $response->assertRedirect(route('login')) : $response->assertForbidden();
        $response->assertDontSee('Rahasia antrean');
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true], ['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    public function test_search_or_never_leaks_non_pending_jobs(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Cocok Perusahaan']);
        $pending = $this->listing(['title' => 'Cocok Judul'], $company);
        foreach (['draft', 'published', 'rejected', 'closed'] as $status) {
            $this->listing(['title' => 'Cocok '.$status, 'status' => $status], $company);
        }
        foreach (['', '?q=Cocok', '?q=Perusahaan', '?q=Judul'] as $query) {
            $this->get(self::URL.$query)->assertOk()
                ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1 && $jobs->first()->id === $pending->id
                    && $jobs->first()->relationLoaded('company'));
        }
    }

    public function test_filters_are_combined_and_category_options_cover_the_whole_pending_queue(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Sasaran']);
        $match = $this->listing(['category' => 'Teknologi', 'location' => 'Jakarta Selatan'], $company);
        $this->listing(['category' => 'Teknologi Lanjutan', 'location' => 'Jakarta Selatan'], $company);
        $this->listing(['category' => 'Teknologi', 'location' => 'Bandung'], $company);
        $this->listing(['category' => 'Keuangan', 'location' => 'Bandung']);
        $this->listing(['category' => 'Kategori Publik Saja', 'status' => 'published']);
        $query = http_build_query(['q' => '  Sasaran  ', 'category' => 'Teknologi', 'location' => 'Selatan', 'per_page' => 1]);
        $this->get(self::URL.'?'.$query)->assertOk()
            ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1 && $jobs->first()->id === $match->id)
            ->assertViewHas('filters', fn ($filters) => $filters['q'] === 'Sasaran')
            ->assertViewHas('categories', fn ($categories) => $categories->all() === ['Keuangan', 'Teknologi', 'Teknologi Lanjutan']);
        $this->get(self::URL.'?category=Keuangan')->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1);
        $this->get(self::URL.'?location=Bandung')->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 2);
    }

    public function test_like_wildcards_are_literal_in_company_title_and_location(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Tim %_!']);
        $match = $this->listing(['title' => 'Posisi %_!', 'location' => 'Kota %_!'], $company);
        $this->listing(['title' => 'Posisi lain', 'location' => 'Kota lain']);
        foreach (['q' => '%_!', 'location' => '%_!'] as $field => $value) {
            $this->get(self::URL.'?'.http_build_query([$field => $value]))
                ->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1 && $jobs->first()->id === $match->id);
        }
    }

    public function test_pagination_has_stable_id_order_and_retains_filters(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Tim Paging']);
        $first = $this->listing(['category' => 'Teknologi', 'created_at' => '2026-11-20 12:00:00'], $company);
        $second = $this->listing(['category' => 'Teknologi', 'created_at' => '2026-11-01 12:00:00'], $company);
        $this->listing(['category' => 'Kategori Halaman Lain'], $company);
        $filters = ['q' => 'Paging', 'category' => 'Teknologi', 'location' => 'Jakarta', 'per_page' => 1];
        $this->get(self::URL.'?'.http_build_query($filters))->assertOk()
            ->assertViewHas('jobs', function ($jobs) use ($first, $filters) {
                parse_str(parse_url($jobs->nextPageUrl(), PHP_URL_QUERY), $next);
                $this->assertSame('2', $next['page']);
                foreach ($filters as $key => $value) {
                    $this->assertSame((string) $value, $next[$key]);
                }

                return $jobs->first()->id === $first->id;
            })->assertSee('pagination')->assertDontSee('20 Nov 2026')->assertDontSee('01 Nov 2026')
            ->assertViewHas('categories', fn ($categories) => $categories->contains('Kategori Halaman Lain'));
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 2]))
            ->assertViewHas('jobs', fn ($jobs) => $jobs->first()->id === $second->id);
        $this->get(self::URL.'?'.http_build_query($filters + ['page' => 999]))
            ->assertRedirect(route('government.job-inspections.index', $filters + ['page' => 2]));
    }

    public function test_empty_queue_differs_from_no_search_results(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertSee('Belum ada pengajuan lowongan');
        $this->listing();
        $this->get(self::URL.'?q=TidakDitemukan')->assertOk()->assertSee('Tidak ada pengajuan yang sesuai')->assertSee('Reset filter');
        $this->get(self::URL.'?q=%20%20')->assertViewHas('filters', fn ($filters) => $filters['q'] === '');
    }

    public function test_untrusted_content_is_escaped_in_cells_options_and_filters(): void
    {
        $this->admin();
        $markup = '<script>alert(1)</script>';
        $company = CompanyProfile::factory()->create(['company_name' => $markup]);
        $this->listing(['title' => $markup, 'category' => $markup, 'location' => $markup, 'salary_around' => null], $company);
        $filters = ['q' => $markup, 'category' => $markup, 'location' => $markup];
        $this->get(self::URL.'?'.http_build_query($filters))->assertOk()
            ->assertSee($markup)->assertDontSee($markup, false)->assertSee('Belum tersedia')->assertDontSee('Rp 0');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_is_rejected(string $field, mixed $value): void
    {
        $this->admin();
        $this->from(self::URL)->get(self::URL.'?'.http_build_query([$field => $value]))
            ->assertRedirect(self::URL)->assertSessionHasErrors($field);
    }

    public static function invalidQueries(): array
    {
        return [
            ['q', ['unexpected']], ['q', str_repeat('a', 256)],
            ['category', ['unexpected']], ['category', str_repeat('a', 256)],
            ['location', ['unexpected']], ['location', str_repeat('a', 256)],
            ['page', 0], ['page', 100001], ['page', 'invalid'], ['page', [1]],
            ['per_page', 0], ['per_page', 51], ['per_page', [1]],
        ];
    }

    public function test_filters_accept_database_length_boundary(): void
    {
        $this->admin();
        $value = str_repeat('a', 255);
        $this->listing(['title' => $value, 'category' => $value, 'location' => $value]);
        $this->get(self::URL.'?'.http_build_query(['q' => $value, 'category' => $value, 'location' => $value]))
            ->assertOk()->assertViewHas('jobs', fn ($jobs) => $jobs->total() === 1);
    }
}
