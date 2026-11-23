<?php

namespace Tests\Feature;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\CompanyVerificationStatusHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyVerificationStatusHistoryDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Testing environment required.');
        }
        config(['database.default' => 'verification_history_testing',
            'database.connections.verification_history_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('verification_history_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php',
            '2026_11_17_000001_create_company_inspections_table.php',
            '2026_11_18_000001_create_company_verification_status_histories_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_schema_indexes_and_isolated_down_up_do_not_backfill(): void
    {
        $table = 'company_verification_status_histories';
        $this->assertSame(['id', 'company_profile_id', 'company_inspection_id', 'sequence', 'event_type',
            'old_status', 'new_status', 'actor_user_id', 'actor_name_snapshot', 'reason_code', 'reason',
            'changed_at', 'created_at'], Schema::getColumnListing($table));
        $this->assertTrue(Schema::hasIndex($table, 'cvsh_company_sequence_unique', 'unique'));
        $this->assertTrue(Schema::hasIndex($table, 'cvsh_company_time_index'));
        $inspection = CompanyInspection::factory()->verified()->create();
        $migration = require database_path('migrations/2026_11_18_000001_create_company_verification_status_histories_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable($table));
        $this->assertTrue($inspection->fresh()->exists);
        $migration->up();
        $this->assertSame(0, CompanyVerificationStatusHistory::count());
    }

    public function test_factory_relations_casts_and_retained_name(): void
    {
        $history = CompanyVerificationStatusHistory::factory()->create(['changed_at' => '2026-11-18 10:11:12'])->fresh();
        $this->assertSame(1, $history->sequence);
        $this->assertInstanceOf(CarbonImmutable::class, $history->changed_at);
        $this->assertInstanceOf(CarbonImmutable::class, $history->created_at);
        $this->assertSame('2026-11-18 10:11:12', $history->changed_at->format('Y-m-d H:i:s'));
        $this->assertSame('status_changed', $history->event_type);
        $this->assertSame('pending', $history->old_status);
        $this->assertSame('verified', $history->new_status);
        $this->assertSame('verified', $history->companyInspection->decision);
        $this->assertSame('verified', $history->companyProfile->verification_status);
        $this->assertSame($history->company_profile_id, $history->companyInspection->company_profile_id);
        $this->assertSame($history->actor_user_id, $history->companyInspection->reviewed_by);
        $this->assertSame($history->actor->name, $history->actor_name_snapshot);
        $this->assertSame('pemerintah', $history->actor->role);
        $this->assertTrue($history->companyProfile->verificationStatusHistories->sole()->is($history));
        $this->assertTrue($history->companyInspection->verificationStatusHistories->sole()->is($history));
        $this->assertTrue($history->companyProfile->inspection->is($history->companyInspection));
        $this->assertFalse(Schema::hasColumn($history->getTable(), 'updated_at'));
    }

    public function test_factory_rejected_and_nullable_actor(): void
    {
        $rejected = CompanyVerificationStatusHistory::factory()->rejected()->create();
        $this->assertSame('rejected', $rejected->new_status);
        $this->assertSame('rejected', $rejected->companyInspection->decision);
        $this->assertSame('rejected', $rejected->companyProfile->verification_status);
        $unassigned = CompanyVerificationStatusHistory::factory()->withoutActor()->create();
        $this->assertNull($unassigned->actor_user_id);
        $this->assertNull($unassigned->actor);
    }

    public function test_sequences_are_unique_per_company_but_independent_across_companies(): void
    {
        $first = CompanyVerificationStatusHistory::factory()->create();
        $second = CompanyVerificationStatusHistory::factory()->create();
        $this->assertNotSame($first->company_profile_id, $second->company_profile_id);
        $this->assertSame(1, $second->sequence);
        $this->expectException(QueryException::class);
        $duplicate = $first->replicate();
        $duplicate->save();
    }

    #[DataProvider('invalidValues')]
    public function test_database_rejects_invalid_enums_required_values_and_foreign_keys(string $field, mixed $value): void
    {
        $row = CompanyVerificationStatusHistory::factory()->raw();
        $row[$field] = $value;
        $this->expectException(QueryException::class);
        CompanyVerificationStatusHistory::create($row);
    }

    public static function invalidValues(): array
    {
        return [['event_type', 'created'], ['old_status', 'unknown'], ['new_status', 'approved'],
            ['company_profile_id', 999999], ['company_inspection_id', 999999], ['actor_user_id', 999999],
            ['company_profile_id', null], ['company_inspection_id', null], ['sequence', null],
            ['old_status', null], ['new_status', null], ['event_type', null],
            ['actor_name_snapshot', null], ['reason_code', null], ['reason', null], ['changed_at', null]];
    }

    #[DataProvider('restrictedParents')]
    public function test_parent_deletion_is_restricted(string $relation): void
    {
        $history = CompanyVerificationStatusHistory::factory()->create();
        $this->expectException(QueryException::class);
        $history->$relation->delete();
    }

    public static function restrictedParents(): array
    {
        return [['companyProfile'], ['companyInspection']];
    }

    public function test_actor_deletion_preserves_event_and_name_snapshot(): void
    {
        $history = CompanyVerificationStatusHistory::factory()->create();
        $name = $history->actor_name_snapshot;
        $history->actor->delete();
        $this->assertNull($history->fresh()->actor_user_id);
        $this->assertSame($name, $history->fresh()->actor_name_snapshot);
        $this->assertSame(1, CompanyVerificationStatusHistory::count());
    }

    public function test_loaded_history_is_not_exposed_by_default_serialization(): void
    {
        $history = CompanyVerificationStatusHistory::factory()->create(['reason' => 'SYNTHETIC_INTERNAL_REASON']);
        $history->load(['actor', 'companyProfile', 'companyInspection']);
        $this->assertSame(['id' => $history->id], $history->toArray());
        $this->assertStringNotContainsString('SYNTHETIC_INTERNAL_REASON', $history->toJson());
        $company = $history->companyProfile->load('verificationStatusHistories');
        $inspection = $history->companyInspection->load('verificationStatusHistories');
        foreach ([$company, $inspection] as $parent) {
            $this->assertArrayNotHasKey('verification_status_histories', $parent->toArray());
            $this->assertArrayNotHasKey('verificationStatusHistories', $parent->toArray());
            $this->assertStringNotContainsString('SYNTHETIC_INTERNAL_REASON', $parent->toJson());
        }
    }

    public function test_unique_inspection_constraint_is_unchanged(): void
    {
        $history = CompanyVerificationStatusHistory::factory()->create();
        $this->expectException(QueryException::class);
        CompanyInspection::factory()->create(['company_profile_id' => $history->company_profile_id]);
    }
}
