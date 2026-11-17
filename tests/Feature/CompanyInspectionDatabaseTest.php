<?php

namespace Tests\Feature;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyInspectionDatabaseTest extends TestCase
{
    private const CONNECTION = 'company_inspection_testing';

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Company inspection tests require APP_ENV=testing.');
        }

        config([
            'database.default' => self::CONNECTION,
            'database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge(self::CONNECTION));

        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

        // Run actual prerequisite migrations, not a parallel test-only schema.
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_11_06_073058_create_sikerja_table.php',
            '2026_11_17_000001_create_company_inspections_table.php',
        ] as $filename) {
            (require database_path('migrations/'.$filename))->up();
        }
    }

    public function test_schema_defaults_and_isolated_rollback(): void
    {
        $this->assertTrue(Schema::hasColumns('company_inspections', [
            'id', 'company_profile_id', 'reviewed_by', 'method', 'oss_check_result',
            'administrative_result', 'decision', 'notes', 'checked_on',
            'company_snapshot', 'created_at', 'updated_at',
        ]));
        $company = CompanyProfile::factory()->create();
        DB::table('company_inspections')->insert([
            'company_profile_id' => $company->id,
            'notes' => 'Synthetic test record',
            'checked_on' => '2026-11-01',
            'company_snapshot' => '{}',
        ]);
        $inspection = CompanyInspection::firstOrFail();
        $this->assertNull($inspection->reviewed_by);
        $this->assertSame('manual_oss_rba', $inspection->method);
        $this->assertSame('unconfirmed', $inspection->oss_check_result);
        $this->assertSame('unconfirmed', $inspection->administrative_result);
        $this->assertSame('pending', $inspection->decision);

        $migration = require database_path('migrations/2026_11_17_000001_create_company_inspections_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('company_inspections'));
        $this->assertTrue(Schema::hasTable('company_profiles'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('company_inspections'));
    }

    public function test_one_current_inspection_per_company(): void
    {
        $inspection = CompanyInspection::factory()->create();
        $this->expectException(QueryException::class);
        CompanyInspection::factory()->create(['company_profile_id' => $inspection->company_profile_id]);
    }

    #[DataProvider('invalidForeignKeys')]
    public function test_foreign_keys_reject_missing_parents(string $column): void
    {
        $attributes = CompanyInspection::factory()->raw();
        $attributes[$column] = 999999;
        $this->expectException(QueryException::class);
        CompanyInspection::create($attributes);
    }

    public static function invalidForeignKeys(): array
    {
        return [['company_profile_id'], ['reviewed_by']];
    }

    public function test_company_deletion_is_restricted(): void
    {
        $inspection = CompanyInspection::factory()->create();
        $this->expectException(QueryException::class);
        $inspection->companyProfile->delete();
    }

    public function test_company_owner_deletion_is_restricted_through_cascade(): void
    {
        $inspection = CompanyInspection::factory()->create();
        $this->expectException(QueryException::class);
        $inspection->companyProfile->user->delete();
    }

    public function test_reviewer_deletion_preserves_inspection_and_sets_null(): void
    {
        $inspection = CompanyInspection::factory()->create();
        $inspection->reviewer->delete();
        $this->assertNull($inspection->fresh()->reviewed_by);
        $this->assertNull($inspection->fresh()->reviewer);
        $this->assertSame(1, CompanyInspection::count());
    }

    public function test_relations_and_json_date_casts(): void
    {
        $snapshot = ['company_name' => 'Synthetic company', 'metadata' => ['checked' => true]];
        $inspection = CompanyInspection::factory()->create([
            'checked_on' => '2026-11-01', 'company_snapshot' => $snapshot,
        ])->fresh();
        $this->assertSame($snapshot, $inspection->company_snapshot);
        $this->assertInstanceOf(Carbon::class, $inspection->checked_on);
        $this->assertSame('2026-11-01', $inspection->checked_on->format('Y-m-d'));
        $this->assertTrue($inspection->companyProfile->inspection->is($inspection));
        $this->assertTrue($inspection->reviewer->companyInspections->sole()->is($inspection));
        $this->assertSame('pemerintah', $inspection->reviewer->role);
    }

    public function test_factory_states_and_snapshot_match_company(): void
    {
        $pending = CompanyInspection::factory()->create();
        $this->assertSame('pending', $pending->decision);
        $this->assertSame($pending->companyProfile->company_name, $pending->company_snapshot['company_name']);
        $verified = CompanyInspection::factory()->verified()->create();
        $this->assertSame(['matched', 'complete', 'verified'], [
            $verified->oss_check_result, $verified->administrative_result, $verified->decision,
        ]);
        $rejected = CompanyInspection::factory()->rejected()->create();
        $this->assertSame(['mismatched', 'incomplete', 'rejected'], [
            $rejected->oss_check_result, $rejected->administrative_result, $rejected->decision,
        ]);
        $usersBefore = User::count();
        $company = CompanyProfile::factory()->create();
        $usersAfterCompany = User::count();
        $this->assertSame($usersBefore + 1, $usersAfterCompany);
        $unassigned = CompanyInspection::factory()->withoutReviewer()->for($company)->create();
        $this->assertNull($unassigned->reviewed_by);
        $this->assertSame($usersAfterCompany, User::count());
    }

    #[DataProvider('invalidValues')]
    public function test_enums_and_required_columns_reject_invalid_values(string $column, mixed $value): void
    {
        $attributes = CompanyInspection::factory()->raw();
        $attributes[$column] = $value;
        $this->expectException(QueryException::class);
        CompanyInspection::create($attributes);
    }

    public static function invalidValues(): array
    {
        return [
            ['method', 'automatic'], ['oss_check_result', 'invalid'],
            ['administrative_result', 'invalid'], ['decision', 'approved'],
            ['company_profile_id', null], ['method', null], ['oss_check_result', null],
            ['administrative_result', null], ['decision', null], ['notes', null],
            ['checked_on', null], ['company_snapshot', null],
        ];
    }
}
