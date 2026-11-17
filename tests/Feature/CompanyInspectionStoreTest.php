<?php

namespace Tests\Feature;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\CompanyInspectionService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyInspectionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Testing environment required.');
        }
        config(['app.key' => str_repeat('a', 32), 'session.driver' => 'array', 'cache.default' => 'array',
            'database.default' => 'inspection_store_testing',
            'database.connections.inspection_store_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('inspection_store_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php', '2026_11_17_000001_create_company_inspections_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    private function url(CompanyProfile $company): string
    {
        return '/pemerintah/pemeriksaan-perusahaan/'.$company->id.'/hasil';
    }

    private function payload(CompanyProfile $company): array
    {
        return ['oss_check_result' => 'matched', 'administrative_result' => 'complete',
            'checked_on' => now()->toDateString(), 'decision' => 'verified', 'notes' => '  Synthetic inspection  ',
            'data_version' => app(CompanyInspectionService::class)->dataVersion($company->fresh())];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'pemerintah', 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    #[DataProvider('roles')]
    public function test_access(?string $role, bool $active, int $status): void
    {
        $company = CompanyProfile::factory()->create();
        if ($role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $this->postJson($this->url($company), $this->payload($company))->assertStatus($status);
        $this->assertSame(0, CompanyInspection::count());
    }

    public static function roles(): array
    {
        return [[null, true, 401], ['pemerintah', false, 403], ['perusahaan', true, 403], ['pencari_kerja', true, 403]];
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation(array $changes, string $field): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $this->postJson($this->url($company), array_replace($this->payload($company), $changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, CompanyInspection::count());
        $this->assertSame('pending', $company->fresh()->verification_status);
    }

    public static function invalidPayloads(): array
    {
        return [
            [['oss_check_result' => 'invalid'], 'oss_check_result'],
            [['administrative_result' => 'invalid'], 'administrative_result'],
            [['decision' => 'invalid'], 'decision'],
            [['checked_on' => '2999-01-01'], 'checked_on'],
            [['checked_on' => '2026-02-30'], 'checked_on'],
            [['notes' => '   '], 'notes'], [['notes' => str_repeat('a', 5001)], 'notes'],
            [['administrative_result' => 'incomplete'], 'decision'],
            [['oss_check_result' => 'unconfirmed', 'decision' => 'rejected'], 'decision'],
            [['administrative_result' => 'unconfirmed', 'decision' => 'verified'], 'decision'],
            [['data_version' => 'bad'], 'data_version'],
            [['reviewed_by' => 999], 'reviewed_by'], [['method' => 'automatic'], 'method'],
            [['company_snapshot' => ['company_name' => 'Forged']], 'company_snapshot'],
        ];
    }

    public function test_save_server_identity_and_final_conflict(): void
    {
        $admin = $this->admin();
        $company = CompanyProfile::factory()->create();
        $input = $this->payload($company);
        $this->postJson($this->url($company), $input)->assertOk();
        $record = $company->fresh()->inspection;
        $this->assertSame($admin->id, $record->reviewed_by);
        $this->assertSame('manual_oss_rba', $record->method);
        $this->assertSame('Synthetic inspection', $record->notes);
        $this->assertSame($company->company_name, $record->company_snapshot['company_name']);
        $this->assertSame($admin->id, $company->fresh()->verified_by);
        $this->assertSame('verified', $company->fresh()->verification_status);
        $this->admin();
        $this->postJson($this->url($company), $this->payload($company))->assertConflict();
        $this->assertSame($admin->id, $record->fresh()->reviewed_by);
    }

    public function test_stale_identity_and_pending_revision_conflict(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $input = $this->payload($company);
        $company->update(['company_name' => 'Changed synthetic name']);
        $this->postJson($this->url($company), $input)->assertConflict();
        $input = array_replace($this->payload($company), ['decision' => 'pending', 'oss_check_result' => 'unconfirmed']);
        $this->postJson($this->url($company), $input)->assertOk();
        $this->postJson($this->url($company), $input)->assertConflict();
        $this->postJson($this->url($company), array_replace($this->payload($company), ['decision' => 'rejected', 'oss_check_result' => 'mismatched']))->assertOk();
        $this->assertSame(1, CompanyInspection::count());
        $this->assertSame('rejected', $company->fresh()->verification_status);
    }

    public function test_transaction_rolls_back_inspection_when_profile_write_fails(): void
    {
        $admin = $this->admin();
        $company = CompanyProfile::factory()->create();
        $before = $company->fresh()->getAttributes();
        DB::statement("CREATE TRIGGER fail_profile_update BEFORE UPDATE ON company_profiles BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            app(CompanyInspectionService::class)->record($company, $admin, $this->payload($company));
            $this->fail('Expected write failure');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        }
        $this->assertSame(0, CompanyInspection::count());
        $this->assertSame('pending', $company->fresh()->verification_status);
        $this->assertSame($before, $company->fresh()->getAttributes());
    }

    public function test_company_scenario_cannot_verify_or_overwrite_official_result(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['npwp' => '012345678901000']);
        $this->postJson($this->url($company), $this->payload($company))->assertOk();
        $this->actingAs($company->user);
        $before = $company->fresh()->getAttributes();
        $this->post(route('company.profile.identity.verify'), ['scenario_override' => 'invalid', 'npwp' => '999'])
            ->assertRedirect();
        $this->assertSame($before, $company->fresh()->getAttributes());
        $result = app(\App\Services\CompanyVerificationService::class)->inspect('Synthetic', '123456789012345', null, null, 'valid');
        $this->assertSame('pending', $result['status']);
    }

    public function test_registration_override_cannot_create_verified_company(): void
    {
        $this->post(route('company.register.submit'), [
            'company_name' => 'Synthetic registration', 'name' => 'Synthetic PIC',
            'email' => 'synthetic@example.test', 'phone' => '081234567890',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'npwp' => '012345678901000', 'scenario_override' => 'valid',
        ])->assertRedirect(route('company.profile.index'));
        $this->assertSame('pending', CompanyProfile::sole()->verification_status);
        $this->assertSame(0, CompanyInspection::count());
    }

    public function test_final_identity_edit_is_rejected_and_user_changes_are_rolled_back(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $this->postJson($this->url($company), $this->payload($company))->assertOk();
        $owner = $company->user;
        $before = $owner->getAttributes();
        $this->actingAs($owner)->putJson(route('company.profile.update'), [
            'company_name' => 'Changed identity', 'name' => 'Changed PIC',
            'email' => $owner->email, 'phone' => '081234567890',
        ])->assertConflict();
        $this->assertSame($company->company_name, $company->fresh()->company_name);
        $this->assertSame($before, $owner->fresh()->getAttributes());
        $this->assertSame('verified', $company->fresh()->inspection->decision);
    }

    public function test_legacy_simulated_final_status_is_not_an_official_record(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => 'verified']);
        $this->assertNull($company->inspection);
        $this->postJson($this->url($company), array_replace($this->payload($company), [
            'decision' => 'pending', 'oss_check_result' => 'unconfirmed',
        ]))->assertOk();
        $this->assertSame('pending', $company->fresh()->verification_status);
    }

    public function test_untrusted_oss_url_is_not_exposed_by_service(): void
    {
        $service = app(CompanyInspectionService::class);
        $this->assertSame('https://oss.go.id/id', $service->ossPortalUrl());
        config(['company_inspections.oss_portal_url' => 'https://oss.go.id.evil.test']);
        $this->assertNull($service->ossPortalUrl());
    }

    #[DataProvider('decisionMatrix')]
    public function test_complete_decision_matrix_and_persisted_values(string $oss, string $administration, string $decision, bool $allowed): void
    {
        $admin = $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Synthetic decision matrix', 'npwp' => '012345678901000']);
        $input = array_replace($this->payload($company), [
            'oss_check_result' => $oss, 'administrative_result' => $administration,
            'decision' => $decision, 'checked_on' => now()->subDay()->toDateString(),
        ]);
        $response = $this->postJson($this->url($company), $input);
        if (! $allowed) {
            $response->assertUnprocessable()->assertJsonValidationErrors('decision');
            $this->assertSame(0, CompanyInspection::count());
            $this->assertSame('pending', $company->fresh()->verification_status);

            return;
        }
        $response->assertOk();
        $inspection = CompanyInspection::sole();
        $this->assertSame($oss, $inspection->oss_check_result);
        $this->assertSame($administration, $inspection->administrative_result);
        $this->assertSame($decision, $inspection->decision);
        $this->assertSame($admin->id, $inspection->reviewed_by);
        $this->assertSame($input['checked_on'], $inspection->checked_on->format('Y-m-d'));
        $this->assertSame(trim($input['notes']), $inspection->notes);
        $this->assertSame(app(CompanyInspectionService::class)->snapshot($company), $inspection->company_snapshot);
        $this->assertSame($decision, $company->fresh()->verification_status);
        $publicMessages = [
            'pending' => 'Status perusahaan menunggu pemeriksaan atau tindak lanjut.',
            'verified' => 'Status perusahaan terverifikasi.',
            'rejected' => 'Status verifikasi perusahaan ditolak.',
        ];
        $this->assertSame($publicMessages[$decision], $company->fresh()->verification_notes);
        $this->assertNotSame($inspection->notes, $company->fresh()->verification_notes);
        $this->assertSame($admin->id, $company->fresh()->verified_by);
    }

    public static function decisionMatrix(): array
    {
        $cases = [];
        foreach (['matched', 'mismatched', 'unconfirmed'] as $oss) {
            foreach (['complete', 'incomplete', 'unconfirmed'] as $administration) {
                foreach (['pending', 'verified', 'rejected'] as $decision) {
                    $allowed = $decision === 'pending'
                        || ($oss !== 'unconfirmed' && $administration !== 'unconfirmed'
                            && ($decision !== 'verified' || ($oss === 'matched' && $administration === 'complete')));
                    $cases[$oss.'-'.$administration.'-'.$decision] = [$oss, $administration, $decision, $allowed];
                }
            }
        }

        return $cases;
    }

    public function test_company_cannot_read_government_notes_under_admin_only_acceptance_requirement(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $marker = 'SYNTHETIC_GOVERNMENT_INTERNAL_FINDINGS_3116';
        $this->postJson($this->url($company), array_replace($this->payload($company), ['notes' => $marker]))->assertOk();
        $this->assertSame($marker, $company->fresh()->inspection->notes);
        $this->get(route('government.company-inspections.show', $company))->assertOk()->assertSee($marker);
        $this->actingAs($company->user)->get(route('company.profile.index'))->assertOk()->assertDontSee($marker)
            ->assertSee('Status perusahaan terverifikasi.')->assertSee('TERVERIFIKASI (VALID)');
    }

    public function test_version_for_another_company_is_rejected(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $other = CompanyProfile::factory()->create();
        $this->postJson($this->url($company), array_replace($this->payload($company), [
            'data_version' => $this->payload($other)['data_version'],
        ]))->assertConflict();
        $this->assertSame(0, CompanyInspection::count());
    }

    #[DataProvider('publicStatuses')]
    public function test_legacy_contaminated_notes_are_not_rendered_or_cleaned_automatically(string $status, string $message): void
    {
        $marker = 'SYNTHETIC_LEGACY_PRIVATE_FINDINGS';
        $company = CompanyProfile::factory()->create(['verification_status' => $status, 'verification_notes' => $marker]);
        $this->actingAs($company->user)->get(route('company.profile.index'))->assertOk()
            ->assertDontSee($marker)->assertSee($message);
        $this->assertSame($marker, $company->fresh()->verification_notes);
    }

    public static function publicStatuses(): array
    {
        return [
            ['pending', 'Status perusahaan menunggu pemeriksaan atau tindak lanjut.'],
            ['verified', 'Status perusahaan terverifikasi.'],
            ['rejected', 'Status verifikasi perusahaan ditolak.'],
            ['unknown', 'Status verifikasi perusahaan belum tersedia.'],
        ];
    }

    #[DataProvider('savedDecisions')]
    public function test_internal_notes_remain_readable_only_in_authorized_government_detail(string $decision, string $oss, string $administration, string $publicMessage): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $marker = 'SYNTHETIC_INTERNAL_NOTE_'.$decision;
        $this->postJson($this->url($company), array_replace($this->payload($company), [
            'decision' => $decision, 'oss_check_result' => $oss,
            'administrative_result' => $administration, 'notes' => '  '.$marker.'  ',
        ]))->assertOk()->assertDontSee($marker);
        $this->assertSame($marker, $company->fresh()->inspection->notes);
        $this->assertSame($publicMessage, $company->fresh()->verification_notes);
        $this->get(route('government.company-inspections.show', $company))->assertOk()->assertSee($marker);
        $this->getJson(route('government.company-inspections.api.show', $company))->assertOk()
            ->assertDontSee($marker)->assertJsonMissingPath('data.notes')->assertJsonMissingPath('data.verification_notes')
            ->assertJsonMissingPath('data.inspection')->assertJsonMissingPath('data.company_snapshot');
        $this->getJson(route('government.company-inspections.api.index'))->assertOk()->assertDontSee($marker);
        $this->get(route('government.company-inspections.index'))->assertOk()->assertDontSee($marker);
        $this->actingAs($company->user)->get(route('company.profile.index'))->assertOk()
            ->assertDontSee($marker)->assertSee($publicMessage);
    }

    public static function savedDecisions(): array
    {
        return [
            ['pending', 'unconfirmed', 'unconfirmed', 'Status perusahaan menunggu pemeriksaan atau tindak lanjut.'],
            ['verified', 'matched', 'complete', 'Status perusahaan terverifikasi.'],
            ['rejected', 'mismatched', 'incomplete', 'Status verifikasi perusahaan ditolak.'],
        ];
    }

    #[DataProvider('unauthorizedNoteReaders')]
    public function test_unauthorized_readers_cannot_obtain_internal_notes(string $actor): void
    {
        $marker = 'SYNTHETIC_RESTRICTED_GOVERNMENT_NOTES';
        $record = CompanyInspection::factory()->create(['notes' => $marker]);
        $company = $record->companyProfile;
        if ($actor === 'owner') {
            $this->actingAs($company->user);
        } elseif ($actor !== 'guest') {
            $this->actingAs(User::factory()->create([
                'role' => $actor === 'inactive' ? 'pemerintah' : $actor,
                'is_active' => $actor !== 'inactive',
            ]));
        }
        foreach (['government.company-inspections.index', 'government.company-inspections.show',
            'government.company-inspections.api.index', 'government.company-inspections.api.show'] as $routeName) {
            $url = route($routeName, str_ends_with($routeName, '.show') ? $company : []);
            $this->getJson($url)->assertStatus($actor === 'guest' ? 401 : 403)->assertDontSee($marker);
        }
    }

    public static function unauthorizedNoteReaders(): array
    {
        return [['guest'], ['inactive'], ['owner'], ['perusahaan'], ['pencari_kerja']];
    }

    public function test_default_model_serialization_hides_notes_snapshot_and_internal_relations(): void
    {
        $marker = 'SYNTHETIC_MODEL_PRIVATE_FINDINGS';
        $record = CompanyInspection::factory()->create([
            'notes' => $marker, 'company_snapshot' => ['internal' => $marker],
        ]);
        $company = $record->companyProfile;
        $company->update(['verification_notes' => $marker, 'verified_by' => $record->reviewed_by]);
        $company->load(['inspection.reviewer', 'verifier']);
        $record->load('reviewer');
        foreach (['notes', 'company_snapshot', 'reviewed_by', 'reviewer'] as $key) {
            $this->assertArrayNotHasKey($key, $record->toArray());
        }
        foreach (['verification_notes', 'inspection', 'verified_by', 'verifier'] as $key) {
            $this->assertArrayNotHasKey($key, $company->toArray());
        }
        $this->assertStringNotContainsString($marker, $record->toJson());
        $this->assertStringNotContainsString($marker, $company->toJson());
        $this->assertSame($marker, $record->notes);
        $this->assertSame($marker, $record->fresh()->notes);
    }
}
