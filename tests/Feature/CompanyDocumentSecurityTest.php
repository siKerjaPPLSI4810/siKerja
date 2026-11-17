<?php

namespace Tests\Feature;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\CompanyInspectionDocument;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyDocumentSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Testing environment required.');
        }
        config(['app.key' => str_repeat('a', 32), 'session.driver' => 'array', 'cache.default' => 'array',
            'database.default' => 'company_document_testing',
            'database.connections.company_document_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('company_document_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php', '2026_11_17_000001_create_company_inspections_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Storage::fake('company_documents');
        Storage::fake('public');
        Storage::fake('local');
    }

    private function registration(): array
    {
        return ['company_name' => 'Synthetic company', 'name' => 'Synthetic PIC',
            'email' => 'synthetic@example.test', 'phone' => '081234567890',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'npwp' => '012345678901000',
            'company_document' => UploadedFile::fake()->createWithContent('synthetic.pdf', "%PDF-1.4\nSynthetic only\n%%EOF")];
    }

    private function updateInput(CompanyProfile $company): array
    {
        return ['company_name' => $company->company_name, 'name' => $company->user->name,
            'email' => $company->user->email, 'phone' => '081234567890',
            'company_document' => UploadedFile::fake()->createWithContent('replacement.pdf', "%PDF-1.4\nReplacement synthetic\n%%EOF")];
    }

    private function governmentUrl(CompanyProfile $company): string
    {
        return route('government.company-inspections.document', $company);
    }

    public function test_registration_stores_private_and_owner_and_active_government_can_download(): void
    {
        $this->post(route('company.register.submit'), $this->registration())->assertRedirect(route('company.profile.index'));
        $company = CompanyProfile::sole();
        $path = $company->company_document_path;
        $this->assertMatchesRegularExpression('/\Acompany_documents\/[a-zA-Z0-9]{64}\.pdf\z/', $path);
        Storage::disk('company_documents')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('local')->assertMissing($path);
        $this->get(route('company.profile.document.download'))->assertOk()->assertDownload('dokumen-legalitas.pdf')
            ->assertStreamedContent("%PDF-1.4\nSynthetic only\n%%EOF");
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]))
            ->get($this->governmentUrl($company))->assertOk()->assertDownload('dokumen-legalitas.pdf');
        $this->get(route('government.company-inspections.api.show', $company))->assertOk()
            ->assertJsonMissingPath('data.company_snapshot')->assertJsonMissingPath('data.company_document_path')
            ->assertDontSee($path);
    }

    public function test_replacement_keeps_old_document_and_snapshot_references(): void
    {
        $company = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/old.pdf']);
        Storage::disk('company_documents')->put($company->company_document_path, 'synthetic old');
        CompanyInspection::factory()->withoutReviewer()->for($company)->create();
        $this->actingAs($company->user)->put(route('company.profile.update'), $this->updateInput($company))
            ->assertRedirect(route('company.profile.index'));
        $newPath = $company->fresh()->company_document_path;
        $this->assertNotSame($company->company_document_path, $newPath);
        Storage::disk('company_documents')->assertExists([$newPath, $company->company_document_path]);
        $this->assertSame($company->company_document_path, $company->inspection->company_snapshot['company_document_path']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('roles')]
    public function test_other_roles_cannot_download_victim_document(?string $role, bool $active): void
    {
        $victim = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/victim.pdf']);
        Storage::disk('company_documents')->put($victim->company_document_path, 'synthetic victim secret');
        if ($role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
            $this->actingAs($user);
        }
        $response = $this->get($this->governmentUrl($victim));
        $role ? $response->assertForbidden() : $response->assertRedirect(route('login'));
        $ownerResponse = $this->get(route('company.profile.document.download', ['companyProfile' => $victim->id, 'path' => $victim->company_document_path]));
        if (! $role) {
            $ownerResponse->assertRedirect(route('company.login'));
        } elseif ($role === 'perusahaan') {
            $ownerResponse->assertRedirect()->assertSessionHas('error');
        } else {
            $ownerResponse->assertForbidden();
        }
        $ownerResponse->assertDontSee('synthetic victim secret');
    }

    public static function roles(): array
    {
        return [[null, true], ['perusahaan', true], ['pencari_kerja', true], ['pemerintah', false]];
    }

    #[DataProvider('invalidReferences')]
    public function test_invalid_missing_and_public_only_documents_are_not_downloaded(string $path): void
    {
        $company = CompanyProfile::factory()->create(['company_document_path' => $path]);
        Storage::disk('public')->put('company_documents/legacy.pdf', 'synthetic public only');
        $this->actingAs($company->user)->get(route('company.profile.document.download'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]))
            ->get($this->governmentUrl($company))->assertNotFound();
    }

    public static function invalidReferences(): array
    {
        return [['../secret.pdf'], ['company_documents/../secret.pdf'], ['/absolute.pdf'],
            ['company_documents/file.php'], ['company_documents/missing.pdf'], ['company_documents/legacy.pdf'],
            ['company_documents/nested/file.pdf']];
    }

    public function test_cross_company_duplicate_reference_is_rejected(): void
    {
        $company = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/shared.pdf']);
        CompanyProfile::factory()->create(['company_document_path' => $company->company_document_path]);
        Storage::disk('company_documents')->put($company->company_document_path, 'synthetic ambiguous');
        $this->assertNull(app(CompanyInspectionDocument::class)->path($company));
    }

    public function test_request_parameters_cannot_select_another_company_document(): void
    {
        $owner = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/owner.pdf']);
        $victim = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/victim.pdf']);
        Storage::disk('company_documents')->put($owner->company_document_path, 'synthetic owner');
        Storage::disk('company_documents')->put($victim->company_document_path, 'synthetic victim');
        $this->actingAs($owner->user)->get(route('company.profile.document.download', [
            'companyProfile' => $victim->id, 'path' => $victim->company_document_path,
        ]))->assertOk()->assertStreamedContent('synthetic owner');
        $input = $this->updateInput($owner);
        unset($input['company_document']);
        $input['company_document_path'] = $victim->company_document_path;
        $this->put(route('company.profile.update'), $input)->assertRedirect(route('company.profile.index'));
        $this->assertSame($owner->company_document_path, $owner->fresh()->company_document_path);
    }

    public function test_write_failure_returns_safe_error_without_database_changes(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::set('company_documents', $disk);
        $this->postJson(route('company.register.submit'), $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('company_document')->assertDontSee('storage/app');
        $this->assertSame(0, User::count());
        $this->assertSame(0, CompanyProfile::count());
    }

    public function test_database_failure_cleans_only_new_file_and_rolls_back(): void
    {
        DB::statement("CREATE TRIGGER fail_company_insert BEFORE INSERT ON company_profiles BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        Storage::disk('company_documents')->put('company_documents/old.pdf', 'synthetic preserved');
        $this->postJson(route('company.register.submit'), $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('company_document')->assertDontSee('synthetic failure');
        $this->assertSame(0, User::count());
        $this->assertSame(['company_documents/old.pdf'], Storage::disk('company_documents')->allFiles());
    }

    public function test_partial_write_is_cleaned_without_deleting_previous_files(): void
    {
        $actual = Storage::disk('company_documents');
        $actual->put('company_documents/old.pdf', 'synthetic retained');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andReturnUsing(fn ($path) => $actual->exists($path));
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $filename) use ($actual) {
            $actual->put($directory.'/'.$filename, 'synthetic partial');

            return false;
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(fn ($path) => $actual->delete($path));
        Storage::set('company_documents', $disk);
        $this->postJson(route('company.register.submit'), $this->registration())->assertUnprocessable();
        $this->assertSame(['company_documents/old.pdf'], $actual->allFiles());
        $this->assertSame(0, CompanyProfile::count());
    }

    public function test_storage_exception_is_hidden_from_response(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('putFileAs')->once()->andThrow(new \RuntimeException('synthetic internal filesystem path'));
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::set('company_documents', $disk);
        $this->postJson(route('company.register.submit'), $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('company_document')
            ->assertDontSee('synthetic internal filesystem path');
        $this->assertSame(0, User::count());
    }

    public function test_final_document_replacement_is_rejected_and_new_attempt_is_cleaned(): void
    {
        $record = CompanyInspection::factory()->verified()->create();
        $company = $record->companyProfile;
        Storage::disk('company_documents')->put('company_documents/retained.pdf', 'synthetic retained');
        $this->actingAs($company->user)->putJson(route('company.profile.update'), $this->updateInput($company))->assertConflict();
        $this->assertSame(['company_documents/retained.pdf'], Storage::disk('company_documents')->allFiles());
        $this->assertSame($company->company_document_path, $company->fresh()->company_document_path);
        $this->assertSame('verified', $record->fresh()->decision);
    }

    public function test_database_failure_on_replacement_preserves_old_reference_and_file(): void
    {
        $company = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/old.pdf']);
        Storage::disk('company_documents')->put($company->company_document_path, 'synthetic old');
        DB::statement("CREATE TRIGGER fail_company_update BEFORE UPDATE ON company_profiles BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->actingAs($company->user)->putJson(route('company.profile.update'), $this->updateInput($company))
            ->assertUnprocessable()->assertJsonValidationErrors('company_document');
        $this->assertSame($company->company_document_path, $company->fresh()->company_document_path);
        $this->assertSame([$company->company_document_path], Storage::disk('company_documents')->allFiles());
    }

    public function test_forbidden_type_and_size_are_rejected_before_storage(): void
    {
        foreach ([UploadedFile::fake()->create('file.php', 1, 'text/plain'), UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')] as $file) {
            $this->postJson(route('company.register.submit'), array_replace($this->registration(), ['company_document' => $file]))
                ->assertUnprocessable()->assertJsonValidationErrors('company_document');
        }
        $this->assertSame([], Storage::disk('company_documents')->allFiles());
    }

    public function test_disk_configuration_does_not_enable_public_or_signed_serving_or_change_cv_disk(): void
    {
        $config = require config_path('filesystems.php');
        $this->assertFalse($config['disks']['company_documents']['serve']);
        $this->assertSame('private', $config['disks']['company_documents']['visibility']);
        $this->assertNotSame($config['disks']['local']['root'], $config['disks']['company_documents']['root']);
        $this->assertNotSame($config['disks']['public']['root'], $config['disks']['company_documents']['root']);
        $this->assertFalse(Route::has('storage.company_documents'));
        $this->assertSame(storage_path('app/private'), $config['disks']['local']['root']);
        $this->assertTrue($config['disks']['local']['serve']);
        $this->assertSame(storage_path('app/public'), $config['disks']['public']['root']);
    }

    public function test_symlink_outside_root_is_rejected_when_platform_supports_links(): void
    {
        $disk = Storage::disk('company_documents');
        $disk->makeDirectory('company_documents');
        Storage::disk('local')->put('synthetic-outside.pdf', 'synthetic outside');
        $link = $disk->path('company_documents/link.pdf');
        if (! @symlink(Storage::disk('local')->path('synthetic-outside.pdf'), $link)) {
            $this->markTestSkipped('OS does not permit creating synthetic symbolic links.');
        }
        try {
            $company = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/link.pdf']);
            $this->assertNull(app(CompanyInspectionDocument::class)->path($company));
        } finally {
            unlink($link); // Only the synthetic link created by this test.
        }
    }
}
