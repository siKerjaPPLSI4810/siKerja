<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\CompanyInspection;
use App\Models\CompanyVerificationStatusHistory;
use App\Models\User;
use App\Services\CompanyInspectionService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyVerificationMonitoringWebTest extends TestCase
{
    private const URL = '/pemerintah/status-verifikasi-perusahaan';

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) { throw new \RuntimeException('Testing required.'); }
        config(['app.key' => str_repeat('a', 32), 'session.driver' => 'array', 'cache.default' => 'array',
            'database.default' => 'monitoring_web_testing',
            'database.connections.monitoring_web_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('monitoring_web_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php',
            '2026_11_17_000001_create_company_inspections_table.php',
            '2026_11_18_000001_create_company_verification_status_histories_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'pemerintah', 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    #[DataProvider('roles')]
    public function test_html_is_restricted(?string $role, bool $active): void
    {
        $record = CompanyInspection::factory()->create(['notes' => 'SYNTHETIC_PRIVATE_NOTE']);
        if ($role) { $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active])); }
        foreach ([self::URL, self::URL.'/'.$record->company_profile_id] as $url) {
            $response = $this->get($url);
            $role ? $response->assertForbidden() : $response->assertRedirect(route('login'));
            $response->assertDontSee('SYNTHETIC_PRIVATE_NOTE');
        }
    }

    public static function roles(): array
    {
        return [[null, true], ['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    public function test_all_statuses_search_filter_and_pagination_use_safe_arrays(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertSee('Belum ada data perusahaan.');
        foreach (['pending', 'verified', 'rejected'] as $status) {
            CompanyProfile::factory()->create(['company_name' => 'Synthetic '.$status, 'npwp' => 'NPWP-'.$status, 'verification_status' => $status]);
        }
        $this->get(self::URL.'?per_page=2')->assertOk()->assertSee('Synthetic pending')->assertSee('Synthetic verified')
            ->assertDontSee('Synthetic rejected')->assertSee('table-responsive')->assertSee('scope="col"', false)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('companies', fn ($rows) => $rows->total() === 3 && is_array($rows->first()) && ! array_key_exists('verification_notes', $rows->first()));
        $this->get(self::URL.'?per_page=2&page=2')->assertOk()->assertSee('Synthetic rejected');
        $this->get(self::URL.'?status=rejected&q=NPWP-rejected&per_page=1')->assertOk()
            ->assertSee('Synthetic rejected')->assertDontSee('Synthetic verified')
            ->assertViewHas('filters', fn ($filters) => $filters['q'] === 'NPWP-rejected' && $filters['status'] === 'rejected');
        $this->get(self::URL.'?status=verified&q=missing')->assertOk()->assertSee('Tidak ada perusahaan yang sesuai filter atau pencarian.');
        $this->get(self::URL.'?status=pending&per_page=1&page=99')->assertRedirect(self::URL.'?status=pending&q=&page=1&per_page=1');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_queries_are_rejected(string $query, string $key): void
    {
        $this->admin();
        $this->from(self::URL)->get(self::URL.'?'.$query)->assertRedirect(self::URL)->assertSessionHasErrors($key);
    }

    public static function invalidQueries(): array
    {
        return [['status=other', 'status'], ['page=0', 'page'], ['per_page=51', 'per_page'], ['q[]=bad', 'q']];
    }

    public function test_detail_provenance_matches_api_and_only_pending_is_actionable(): void
    {
        $this->admin();
        $officialPending = CompanyInspection::factory()->create()->companyProfile;
        $officialFinal = CompanyInspection::factory()->verified()->create()->companyProfile;
        $officialFinal->update(['verification_status' => 'verified']);
        $legacy = CompanyProfile::factory()->create(['verification_status' => 'rejected']);
        $pending = CompanyProfile::factory()->create();
        $unknown = CompanyProfile::factory()->create(['verification_status' => 'invalid']);
        $inconsistent = CompanyInspection::factory()->create()->companyProfile;
        $inconsistent->update(['verification_status' => 'verified']);
        foreach ([[$officialPending, true], [$officialFinal, false], [$legacy, false], [$pending, true], [$unknown, false], [$inconsistent, false]] as [$company, $eligible]) {
            $provenance = $this->getJson(route('government.company-verification-statuses.api.show', $company))->json('data.provenance');
            $response = $this->get(self::URL.'/'.$company->id)->assertOk()
                ->assertViewHas('companyData', fn ($data) => is_array($data) && $data['provenance'] === $provenance)
                ->assertViewHas('canInspect', $eligible)->assertHeader('Cache-Control', 'no-store, private');
            $eligible ? $response->assertSee('Buka pemeriksaan perusahaan') : $response->assertDontSee('Buka pemeriksaan perusahaan');
        }
        $this->get(self::URL.'/'.$legacy->id)->assertSee('Status final legacy belum didukung');
        $this->get(self::URL.'/'.$pending->id)->assertSee('Perusahaan belum memiliki hasil pemeriksaan resmi.');
        $this->get(self::URL.'/'.$unknown->id)->assertSee('Sumber atau konsistensi status belum dapat dipastikan.');
        $this->assertSame(0, CompanyVerificationStatusHistory::count());
    }

    public function test_admin_notes_are_explicit_and_history_is_scoped_and_paginated(): void
    {
        $admin = $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => 'verified', 'company_document_path' => 'company_documents/PRIVATE.pdf']);
        $service = app(CompanyInspectionService::class);
        $input = ['oss_check_result' => 'unconfirmed', 'administrative_result' => 'unconfirmed',
            'decision' => 'pending', 'notes' => 'SYNTHETIC_INTERNAL_NOTE', 'checked_on' => now()->toDateString(),
            'data_version' => $service->dataVersion($company)];
        $service->record($company, $admin, $input);
        $input = array_replace($input, ['oss_check_result' => 'matched', 'administrative_result' => 'complete',
            'decision' => 'verified', 'data_version' => $service->dataVersion($company->fresh())]);
        $service->record($company, $admin, $input);
        CompanyVerificationStatusHistory::factory()->create(['reason' => 'SYNTHETIC_OTHER_COMPANY_EVENT']);
        $this->get(self::URL.'/'.$company->id.'?status=verified&q=Synthetic&page=2&per_page=1&history_per_page=1')
            ->assertOk()->assertSee('SYNTHETIC_INTERNAL_NOTE')->assertDontSee('company_documents/PRIVATE.pdf')
            ->assertDontSee('SYNTHETIC_OTHER_COMPANY_EVENT')->assertSee('history_page=2', false)
            ->assertViewHas('historyEntries', fn ($rows) => is_array($rows->first()) && $rows->first()['old_status_label'] === 'Pending' && $rows->total() === 2)
            ->assertViewHas('inspectionData', fn ($data) => is_array($data) && ! array_key_exists('company_snapshot', $data))
            ->assertViewHas('returnFilters', fn ($filters) => $filters['page'] === 2 && $filters['status'] === 'verified');
        $this->get(self::URL.'/'.$company->id.'?history_per_page=1&history_page=2')->assertOk()
            ->assertViewHas('historyEntries', fn ($rows) => $rows->first()['old_status_label'] === 'Verified');
        $this->get(self::URL.'/'.$company->id.'?history_page=999')->assertOk()->assertSee('Halaman riwayat di luar rentang.');
        $this->get(self::URL.'/'.$company->id.'?history_page=0')->assertRedirect()->assertSessionHasErrors('history_page');
        $empty = CompanyProfile::factory()->create();
        $this->get(self::URL.'/'.$empty->id)->assertOk()->assertSee('Riwayat sebelum pencatatan diaktifkan tidak tersedia.')
            ->assertSee('Belum ada perubahan status tercatat');
        $this->get(self::URL.'/99999')->assertNotFound();
    }
}
