<?php

namespace Tests\Feature;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyInspectionApiTest extends TestCase
{
    private const URL = '/pemerintah/api/pemeriksaan-perusahaan';

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('API tests require testing environment.');
        }
        config([
            'database.default' => 'inspection_api_testing',
            'database.connections.inspection_api_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('inspection_api_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_11_06_073058_create_sikerja_table.php',
            '2026_11_17_000001_create_company_inspections_table.php',
        ] as $filename) {
            (require database_path('migrations/'.$filename))->up();
        }
    }

    private function signInAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]));
    }

    public function test_guest_receives_json_without_accept_header(): void
    {
        $company = CompanyProfile::factory()->create();
        foreach ([self::URL, self::URL.'/'.$company->id] as $url) {
            $this->get($url)->assertUnauthorized()->assertExactJson([
                'message' => 'Autentikasi diperlukan.', 'errors' => [],
            ])->assertHeader('Content-Type', 'application/json');
        }
    }

    #[DataProvider('forbiddenUsers')]
    public function test_unauthorized_roles_and_inactive_admin_are_forbidden(string $role, bool $active): void
    {
        $company = CompanyProfile::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        foreach ([self::URL, self::URL.'/'.$company->id] as $url) {
            $this->get($url)->assertForbidden()->assertExactJson([
                'message' => 'Akses hanya untuk Admin Pemerintah aktif.', 'errors' => [],
            ]);
        }
    }

    public static function forbiddenUsers(): array
    {
        return [['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    public function test_pending_queue_has_stable_pagination_and_bounded_size(): void
    {
        $this->signInAdmin();
        $pending = CompanyProfile::factory()->count(3)->create();
        CompanyProfile::factory()->create(['verification_status' => 'verified']);
        CompanyProfile::factory()->create(['verification_status' => 'rejected']);
        $this->get(self::URL.'?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $pending[0]->id)
            ->assertJsonPath('data.1.id', $pending[1]->id)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(self::URL.'?per_page=2&page=2')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending[2]->id);
        $this->get(self::URL)->assertJsonPath('meta.per_page', 15);
        $this->get(self::URL.'?per_page=50')->assertOk()->assertJsonPath('meta.per_page', 50);
    }

    public function test_search_name_npwp_and_literal_wildcards_keeps_pending_filter(): void
    {
        $this->signInAdmin();
        $target = CompanyProfile::factory()->create(['company_name' => 'Synthetic 100%_!', 'npwp' => '123456789012345']);
        CompanyProfile::factory()->create(['company_name' => 'Synthetic 100ABC', 'npwp' => '999']);
        CompanyProfile::factory()->create(['company_name' => 'Synthetic 100%_!', 'verification_status' => 'verified']);
        foreach (['100%_!', '123456789012345'] as $q) {
            $this->get(self::URL.'?'.http_build_query(['q' => $q]))->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
        }
        $this->get(self::URL.'?q=missing')->assertOk()->assertJsonCount(0, 'data');
        $this->get(self::URL.'?q=%27%20OR%201%3D1')->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_returns_consistent_json(string $query, string $field): void
    {
        $this->signInAdmin();
        $this->get(self::URL.'?'.$query)->assertUnprocessable()
            ->assertJsonPath('message', 'Parameter permintaan tidak valid.')
            ->assertJsonValidationErrors($field)->assertJsonStructure(['message', 'errors']);
    }

    public static function invalidQueries(): array
    {
        return [
            ['per_page=0', 'per_page'], ['per_page=51', 'per_page'],
            ['per_page[]=2', 'per_page'], ['page=0', 'page'],
            ['page=100001', 'page'], ['page=abc', 'page'],
            ['q[]=name', 'q'], ['q='.str_repeat('a', 101), 'q'],
        ];
    }

    public function test_final_detail_is_readable_and_only_safe_attributes_are_returned_without_writes(): void
    {
        $this->signInAdmin();
        $inspection = CompanyInspection::factory()->verified()->create();
        $company = $inspection->companyProfile;
        $company->update(['verification_status' => 'verified', 'company_document_path' => 'private/sensitive.pdf']);
        $before = $company->fresh()->getAttributes();
        $inspectionBefore = $inspection->fresh()->getAttributes();
        $response = $this->get(self::URL.'/'.$company->id)->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.document_reference_present', true);
        $this->assertEqualsCanonicalizing([
            'id', 'company_name', 'npwp', 'address', 'province', 'city', 'website',
            'verification_status', 'document_reference_present', 'data_version',
        ], array_keys($response->json('data')));
        $this->assertStringNotContainsString('private/sensitive.pdf', $response->getContent());
        $this->assertSame($before, $company->fresh()->getAttributes());
        $this->assertSame($inspectionBefore, $inspection->fresh()->getAttributes());
        $this->assertSame(1, CompanyInspection::count());
        $pending = CompanyProfile::factory()->create();
        $this->get(self::URL.'/'.$pending->id)->assertOk()->assertJsonPath('data.document_reference_present', false);
        $this->get(self::URL)->assertJsonMissingPath('data.0.user')->assertJsonMissingPath('data.0.company_document_path');
    }

    public function test_missing_and_invalid_ids_return_safe_json(): void
    {
        $this->signInAdmin();
        foreach (['999999', 'invalid'] as $id) {
            $this->get(self::URL.'/'.$id)->assertNotFound()->assertExactJson([
                'message' => 'Data tidak ditemukan.', 'errors' => [],
            ]);
        }
    }
}
