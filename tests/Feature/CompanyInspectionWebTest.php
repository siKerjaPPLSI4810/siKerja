<?php

namespace Tests\Feature;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\CompanyInspectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyInspectionWebTest extends TestCase
{
    private const URL = '/pemerintah/pemeriksaan-perusahaan';

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->app->environment('testing')) {
            throw new \RuntimeException('Testing environment required.');
        }
        config(['app.key' => str_repeat('a', 32), 'session.driver' => 'array', 'cache.default' => 'array',
            'database.default' => 'inspection_web_testing',
            'database.connections.inspection_web_testing' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->beforeApplicationDestroyed(fn () => DB::purge('inspection_web_testing'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_06_073058_create_sikerja_table.php', '2026_11_17_000001_create_company_inspections_table.php', '2026_11_18_000001_create_company_verification_status_histories_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Storage::fake('local');
        Storage::fake('company_documents');
        Storage::fake('public');
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pemerintah', 'is_active' => true]));
    }

    private function payload(CompanyProfile $company): array
    {
        return ['oss_check_result' => 'matched', 'administrative_result' => 'complete',
            'decision' => 'verified', 'checked_on' => now()->toDateString(), 'notes' => 'Synthetic manual findings',
            'data_version' => app(CompanyInspectionService::class)->dataVersion($company->fresh())];
    }

    #[DataProvider('roles')]
    public function test_all_html_and_download_routes_require_active_government(?string $role, bool $active): void
    {
        $company = CompanyProfile::factory()->create();
        if ($role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        foreach ([self::URL, self::URL.'/'.$company->id, self::URL.'/'.$company->id.'/dokumen'] as $url) {
            $response = $this->get($url);
            $role ? $response->assertForbidden() : $response->assertRedirect(route('login'));
            $response->assertDontSee($company->company_name);
        }
    }

    public static function roles(): array
    {
        return [[null, true], ['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    public function test_empty_search_pending_queue_and_pagination(): void
    {
        $this->admin();
        $this->get(self::URL)->assertOk()->assertSee('Tidak ada perusahaan yang menunggu pemeriksaan.');
        $companies = CompanyProfile::factory()->count(3)->create();
        CompanyProfile::factory()->create(['company_name' => 'Final synthetic company', 'verification_status' => 'verified']);
        $this->get(self::URL.'?per_page=2')->assertOk()->assertSee($companies[0]->company_name)
            ->assertDontSee('Final synthetic company')->assertSee('table-responsive')->assertSee('pagination');
        $this->get(self::URL.'?per_page=2&page=999')->assertRedirect(self::URL.'?q=&per_page=2&page=2');
        $this->get(self::URL.'?q=notfound')->assertOk()->assertSee('Tidak ada perusahaan yang cocok');
        $this->get(self::URL.'?'.http_build_query(['q' => $companies[0]->company_name]))->assertOk()
            ->assertViewHas('companies', fn ($items) => $items->total() === 1);
        $this->from(self::URL)->get(self::URL.'?q[]=bad')->assertRedirect(self::URL)->assertSessionHasErrors('q');
    }

    public function test_detail_contract_readonly_escaping_and_responsive_markup(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => '<script>synthetic</script>']);
        $this->get(self::URL.'/'.$company->id)->assertOk()
            ->assertViewHasAll(['company', 'inspectionRecord', 'document', 'dataVersion', 'canSubmit', 'canRecordInspection', 'ossPortalUrl'])
            ->assertSee('&lt;script&gt;synthetic&lt;/script&gt;', false)
            ->assertDontSee('<script>synthetic</script>', false)
            ->assertSee('col-lg-6')->assertSee('name="_token"', false)
            ->assertSee('name="data_version"', false)->assertDontSee('name="company_name"', false)
            ->assertSee('width=device-width')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(self::URL.'/999')->assertNotFound();
    }

    public function test_validation_old_input_and_post_redirect_get(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $detail = self::URL.'/'.$company->id;
        $this->from($detail)->post($detail.'/hasil', array_replace($this->payload($company), ['notes' => '   ']))
            ->assertRedirect($detail);
        // Follow the redirect before inspecting session: JSON-session assertions restart
        // the same in-process session and can deserialize its error bag twice.
        $this->get($detail)->assertOk()->assertViewHas('errors', fn ($errors) => $errors->has('notes'))
            ->assertSee('Periksa isian berikut:')->assertSee('value="verified" selected', false);
        $this->post($detail.'/hasil', $this->payload($company))->assertRedirect($detail)->assertSessionHas('status');
        $this->get($detail)->assertOk()->assertSee('Hasil pemeriksaan berhasil disimpan.')
            ->assertSee('Synthetic manual findings')->assertSee('Keputusan sudah final.')
            ->assertSee('<fieldset disabled>', false);
        $this->assertSame('verified', $company->fresh()->verification_status);
    }

    public function test_html_conflict_disables_submission_and_requests_reload(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $input = $this->payload($company);
        $company->update(['company_name' => 'Updated synthetic']);
        $this->post(self::URL.'/'.$company->id.'/hasil', $input)->assertConflict()
            ->assertSee('Muat ulang data')->assertSee('Pencatatan dinonaktifkan')->assertDontSee('Hasil pemeriksaan berhasil disimpan.');
        $this->assertSame(0, CompanyInspection::count());
    }

    public function test_pending_inspection_unknown_status_and_legacy_final(): void
    {
        $this->admin();
        $record = CompanyInspection::factory()->create();
        $this->get(self::URL)->assertOk()->assertSee('Perlu pemeriksaan lanjutan');
        $this->get(self::URL.'/'.$record->company_profile_id)->assertOk()->assertSee('Perlu pemeriksaan lanjutan');
        $unknown = CompanyProfile::factory()->create(['verification_status' => 'unknown']);
        $this->get(self::URL.'/'.$unknown->id)->assertOk()->assertSee('Status belum tersedia')->assertDontSee('Keputusan sudah final.');
        $legacy = CompanyProfile::factory()->create(['verification_status' => 'verified']);
        $this->get(self::URL.'/'.$legacy->id)->assertOk()->assertSee('Belum ada catatan pemeriksaan manual')
            ->assertViewHas('canSubmit', true);
    }

    public function test_private_download_and_rejection_of_public_only_or_invalid_references(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_document_path' => 'company_documents/synthetic.pdf']);
        Storage::disk('public')->put('company_documents/synthetic.pdf', 'legacy synthetic');
        $url = self::URL.'/'.$company->id.'/dokumen';
        $this->get($url)->assertNotFound();
        $this->get(self::URL.'/'.$company->id)->assertSee('Berkas lama')->assertDontSee('Unduh dokumen');
        Storage::disk('company_documents')->put('company_documents/synthetic.pdf', 'private synthetic');
        $this->get($url.'?path=other.pdf')->assertOk()->assertDownload('dokumen-legalitas.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent('private synthetic');
        $this->get(self::URL.'/'.$company->id)->assertSee('Unduh dokumen')->assertDontSee('company_documents/synthetic.pdf');
        foreach (['../outside.pdf', 'company_documents/../outside.pdf', '/absolute.pdf', 'company_documents/nested/file.pdf', 'company_documents/file.php'] as $reference) {
            $company->update(['company_document_path' => $reference]);
            $this->get($url)->assertNotFound();
        }
    }

    public function test_post_requires_csrf_token_when_framework_test_bypass_is_removed(): void
    {
        $this->admin();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            fn ($app) => new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
                protected function runningUnitTests()
                {
                    return false;
                }
            });
        $company = CompanyProfile::factory()->create();
        $url = self::URL.'/'.$company->id.'/hasil';
        $this->post($url, $this->payload($company))->assertStatus(419);
        $this->assertSame(0, CompanyInspection::count());
        $this->withSession(['_token' => 'synthetic-csrf-token'])->post($url,
            array_merge($this->payload($company), ['_token' => 'synthetic-csrf-token']))->assertRedirect(self::URL.'/'.$company->id);
        $this->assertSame(1, CompanyInspection::count());
    }

    public function test_accessible_form_labels_descriptions_and_responsive_structure(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create();
        $response = $this->get(self::URL.'/'.$company->id)->assertOk();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent(), LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        foreach ($xpath->query('//label[@for]') as $label) {
            $this->assertNotNull($document->getElementById($label->getAttribute('for')));
        }
        foreach ($xpath->query('//*[@aria-describedby]') as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('aria-describedby'))) as $id) {
                $this->assertNotNull($document->getElementById($id), 'Missing accessible description: '.$id);
            }
        }
        $this->assertSame(1, $xpath->query('//main[@id="main-content"]')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(5, $xpath->query('//form[@id="inspection-form"]//*[@required]')->length);
        $response->assertSee('col-lg-6')->assertSee('width=device-width');
        $this->get(self::URL)->assertOk()->assertSee('table-responsive')->assertSee('scope="col"', false)->assertSee('<caption', false);
    }
}
