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

class CompanyVerificationMonitoringApiTest extends TestCase
{
    private const URL = '/pemerintah/api/status-verifikasi-perusahaan';

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) { throw new \RuntimeException('Testing required.'); }
        config(['app.key' => str_repeat('a', 32), 'session.driver' => 'array', 'cache.default' => 'array',
            'database.default' => 'monitoring_api_testing',
            'database.connections.monitoring_api_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('monitoring_api_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php',
            '2026_11_17_000001_create_company_inspections_table.php',
            '2026_11_18_000001_create_company_verification_status_histories_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]));
    }

    private function payload(CompanyProfile $company): array
    {
        return ['oss_check_result' => 'matched', 'administrative_result' => 'complete',
            'decision' => 'verified', 'notes' => 'SYNTHETIC_PRIVATE_NOTES', 'checked_on' => now()->toDateString(),
            'data_version' => $this->get(self::URL.'/'.$company->id)->assertOk()->json('data.data_version')];
    }

    #[DataProvider('roles')]
    public function test_all_endpoints_deny_unauthorized_users(?string $role, bool $active): void
    {
        $company = CompanyProfile::factory()->create();
        if ($role) { $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active])); }
        foreach ([self::URL, self::URL.'/'.$company->id, self::URL.'/'.$company->id.'/riwayat'] as $url) {
            $this->get($url)->assertStatus($role ? 403 : 401)->assertJsonStructure(['message', 'errors'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->patch(self::URL.'/'.$company->id, [])->assertStatus($role ? 403 : 401);
    }

    public static function roles(): array
    {
        return [[null, true], ['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    public function test_all_statuses_filter_search_literal_wildcards_and_pagination(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertJsonCount(0, 'data');
        $companies = [];
        foreach (['pending', 'verified', 'rejected'] as $status) {
            $companies[] = CompanyProfile::factory()->create(['verification_status' => $status, 'company_name' => 'Synthetic %_'.$status, 'npwp' => 'NPWP-'.$status]);
        }
        $this->get(self::URL.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', $companies[0]->id)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get(self::URL.'?per_page=2&page=2')->assertJsonPath('data.0.id', $companies[2]->id);
        foreach (['pending', 'verified', 'rejected'] as $status) {
            foreach (['%_'.$status, 'NPWP-'.$status] as $q) {
                $this->get(self::URL.'?'.http_build_query(['status' => $status, 'q' => $q]))
                    ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.verification_status', $status);
            }
        }
        $this->get(self::URL.'?status=pending&q=missing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_provenance_and_strict_response_allowlist(): void
    {
        $this->admin();
        $official = CompanyInspection::factory()->verified()->create()->companyProfile;
        $official->update(['verification_status' => 'verified']);
        $pending = CompanyProfile::factory()->create();
        $legacy = CompanyProfile::factory()->create(['verification_status' => 'rejected']);
        $unknown = CompanyProfile::factory()->create(['verification_status' => 'other']);
        $inconsistent = CompanyInspection::factory()->create()->companyProfile;
        $inconsistent->update(['verification_status' => 'verified']);
        foreach ([[$official, 'official'], [$pending, 'uninspected'], [$legacy, 'legacy'], [$unknown, 'unknown'], [$inconsistent, 'unknown']] as [$company, $provenance]) {
            $response = $this->get(self::URL.'/'.$company->id)->assertOk()->assertJsonPath('data.provenance', $provenance);
            $this->assertEqualsCanonicalizing(['id', 'company_name', 'npwp', 'verification_status', 'provenance', 'data_version'], array_keys($response->json('data')));
        }
        $this->get(self::URL.'/'.$unknown->id)->assertJsonPath('data.verification_status', 'other');
        $this->get(self::URL.'/'.$legacy->id.'/riwayat')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, CompanyVerificationStatusHistory::count());
        foreach ([$legacy, $unknown, $inconsistent] as $company) {
            $this->patchJson(self::URL.'/'.$company->id, $this->payload($company))->assertConflict();
        }
    }

    #[DataProvider('invalidQuery')]
    public function test_invalid_query_returns_json_validation(string $query, string $key): void
    {
        $this->admin();
        $this->get(self::URL.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($key);
    }

    public static function invalidQuery(): array
    {
        return [['status=other', 'status'], ['status[]=pending', 'status'], ['page=0', 'page'],
            ['page=100001', 'page'], ['per_page=51', 'per_page'], ['per_page=abc', 'per_page'],
            ['q='.str_repeat('x', 101), 'q'], ['q[]=bad', 'q']];
    }

    #[DataProvider('decisions')]
    public function test_patch_reuses_official_writer_and_final_conflict(string $decision, string $oss, string $administration): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/PRIVATE.pdf']);
        $input = array_replace($this->payload($company), ['decision' => $decision, 'oss_check_result' => $oss, 'administrative_result' => $administration]);
        $this->patchJson(self::URL.'/'.$company->id, $input)->assertOk()->assertJsonPath('data.verification_status', $decision)->assertDontSee('SYNTHETIC_PRIVATE_NOTES');
        $this->assertSame($decision, $company->fresh()->verification_status);
        $this->assertSame($decision === 'pending' ? 0 : 1, CompanyVerificationStatusHistory::count());
        $this->assertSame('SYNTHETIC_PRIVATE_NOTES', $company->fresh()->inspection->notes);
        $this->patchJson(self::URL.'/'.$company->id, $input)->assertConflict();
        if ($decision !== 'pending') {
            $this->patchJson(self::URL.'/'.$company->id, $this->payload($company))->assertConflict();
            $history = $this->get(self::URL.'/'.$company->id.'/riwayat')->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.old_status', 'pending')->assertJsonPath('data.0.new_status', $decision)
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertEqualsCanonicalizing(['id', 'sequence', 'event_type', 'old_status', 'new_status', 'actor_label', 'reason_code', 'reason', 'changed_at'], array_keys($history->json('data.0')));
            $history->assertDontSee('SYNTHETIC_PRIVATE_NOTES')->assertDontSee('company_documents/PRIVATE.pdf');
        } else {
            $this->patchJson(self::URL.'/'.$company->id, array_replace($this->payload($company), [
                'decision' => 'pending', 'oss_check_result' => 'unconfirmed', 'administrative_result' => 'unconfirmed',
            ]))->assertOk();
            $this->assertSame(0, CompanyVerificationStatusHistory::count());
        }
        $this->get(self::URL)->assertOk()->assertDontSee('SYNTHETIC_PRIVATE_NOTES')->assertDontSee('company_documents/PRIVATE.pdf');
    }

    public static function decisions(): array
    {
        return [['verified', 'matched', 'complete'], ['rejected', 'mismatched', 'incomplete'], ['pending', 'unconfirmed', 'unconfirmed']];
    }

    public function test_patch_requires_full_evidence_and_rejects_spoofing(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $input = $this->payload($company);
        $this->patchJson(self::URL.'/'.$company->id, ['decision' => 'verified'])->assertUnprocessable();
        foreach (['reviewed_by', 'actor_user_id', 'actor_name_snapshot', 'changed_at', 'sequence', 'reason_code', 'company_snapshot'] as $key) {
            $this->patchJson(self::URL.'/'.$company->id, array_replace($input, [$key => 'forged']))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->patchJson(self::URL.'/'.$company->id, array_replace($input, ['administrative_result' => 'incomplete']))->assertUnprocessable();
        $this->patchJson(self::URL.'/'.$company->id, array_replace($input, ['decision' => 'rejected', 'oss_check_result' => 'unconfirmed']))->assertUnprocessable();
        $company->update(['company_name' => 'Changed synthetic']);
        $this->patchJson(self::URL.'/'.$company->id, $input)->assertConflict();
        $this->assertSame(0, CompanyInspection::count());
        $this->assertSame(0, CompanyVerificationStatusHistory::count());
    }

    public function test_history_is_scoped_ordered_and_paginated_without_backfill(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => 'verified']);
        // Preserve PBI 3.1.1's existing first-inspection legacy handling to create real events.
        $service = app(CompanyInspectionService::class);
        $service->record($company, auth()->user(), array_replace($this->payload($company), ['decision' => 'pending', 'oss_check_result' => 'unconfirmed']));
        $this->patchJson(self::URL.'/'.$company->id, $this->payload($company))->assertOk();
        $other = CompanyProfile::factory()->create();
        $this->patchJson(self::URL.'/'.$other->id, $this->payload($other))->assertOk();
        $this->get(self::URL.'/'.$company->id.'/riwayat?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.sequence', 2);
        $this->get(self::URL.'/'.$company->id.'/riwayat?per_page=1&page=2')->assertJsonPath('data.0.sequence', 1);
        $this->get(self::URL.'/99999')->assertNotFound()->assertJsonStructure(['message', 'errors']);
        $this->get(self::URL.'/99999/riwayat')->assertNotFound();
        $this->patchJson(self::URL.'/99999', [])->assertNotFound();
    }

    public function test_csrf_is_enforced_when_test_bypass_is_removed(): void
    {
        $this->admin();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            fn ($app) => new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
                protected function runningUnitTests() { return false; }
            });
        $company = CompanyProfile::factory()->create();
        $input = $this->payload($company);
        $this->patch(self::URL.'/'.$company->id, $input)->assertStatus(419);
        $this->assertSame(0, CompanyInspection::count());
        $this->withSession(['_token' => 'synthetic-csrf'])->patch(self::URL.'/'.$company->id, array_merge($input, ['_token' => 'synthetic-csrf']))->assertOk();
    }
}
