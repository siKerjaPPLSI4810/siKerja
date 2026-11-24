<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GovernmentJobInspectionTestCase;

class GovernmentJobInspectionShowTest extends GovernmentJobInspectionTestCase
{
    private const URL = '/pemerintah/pemeriksaan-lowongan/';

    public function test_pending_detail_displays_all_fields_and_does_not_change_data(): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['company_name' => 'Perusahaan Detail']);
        $job = $this->listing([
            'title' => 'Pengembang Aplikasi', 'category' => 'Teknologi', 'job_type' => 'Kontrak',
            'location' => 'Bandung', 'description' => "Membangun aplikasi\nMerawat layanan",
            'education_requirement' => 'D4/S1', 'requirements' => "Menguasai Laravel\nBekerja dalam tim",
            'salary_around' => 8000000, 'quota' => 3, 'deadline' => '2027-01-31',
        ], $company);
        $beforeJob = $job->fresh()->getRawOriginal();
        $beforeCompany = $company->fresh()->getRawOriginal();
        $route = Route::getRoutes()->getByName('government.job-inspections.show');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('can:access-government', $route->gatherMiddleware());
        $response = $this->get(route('government.job-inspections.show', ['jobPosting' => $job->id]))
            ->assertOk()->assertViewIs('government.job-inspections.show')
            ->assertViewHas('job', fn ($item) => $item->id === $job->id && $item->relationLoaded('company'))
            ->assertSee('Pengembang Aplikasi')->assertSee('Perusahaan Detail')->assertSee('Menunggu Tinjauan')
            ->assertSee('Teknologi')->assertSee('Kontrak')->assertSee('Bandung')
            ->assertSee("Membangun aplikasi\nMerawat layanan")->assertSee('D4/S1')
            ->assertSee("Menguasai Laravel\nBekerja dalam tim")->assertSee('Rp 8.000.000')
            ->assertSee('3 orang')->assertSee('31 Jan 2027')->assertSee('datetime="2027-01-31"', false)
            ->assertSee('white-space: pre-line', false)->assertSee('Kembali ke daftar pengajuan')
            ->assertSee(route('government.job-inspections.index'), false)
            ->assertSee(route('government.company-verification-statuses.index'), false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Pemeriksaan lowongan<\/a>/', $response->getContent());
        $this->assertSame($beforeJob, $job->fresh()->getRawOriginal());
        $this->assertSame($beforeCompany, $company->fresh()->getRawOriginal());
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_detail_requires_active_government(?string $role, bool $active): void
    {
        $job = $this->listing(['description' => 'Deskripsi pengajuan terbatas']);
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
        }
        $response = $this->get(self::URL.$job->id);
        $role === null ? $response->assertRedirect(route('login')) : $response->assertForbidden();
        $response->assertDontSee('Deskripsi pengajuan terbatas');
    }

    public static function unauthorizedUsers(): array
    {
        return [[null, true], ['pemerintah', false], ['perusahaan', true], ['pencari_kerja', true]];
    }

    #[DataProvider('otherStatuses')]
    public function test_other_statuses_are_not_available_even_to_government(string $status): void
    {
        $this->admin();
        $job = $this->listing(['status' => $status, 'title' => 'Lowongan di luar antrean']);
        $this->get(self::URL.$job->id)->assertNotFound()->assertDontSee('Lowongan di luar antrean');
        $this->assertSame($status, $job->fresh()->status);
    }

    public static function otherStatuses(): array
    {
        return [['draft'], ['published'], ['rejected'], ['closed'], ['unknown']];
    }

    #[DataProvider('missingIdentifiers')]
    public function test_missing_or_invalid_identifiers_return_404(string $identifier): void
    {
        $this->admin();
        $this->get(self::URL.$identifier)->assertNotFound();
    }

    public static function missingIdentifiers(): array
    {
        return [['99999'], ['0'], ['abc'], ['-1'], ['1.5'], [str_repeat('9', 40)]];
    }

    public function test_dynamic_html_is_escaped_and_internal_company_fields_are_not_exposed(): void
    {
        $this->admin();
        $html = '<script>alert(1)</script>';
        $company = CompanyProfile::factory()->create([
            'company_name' => $html, 'verification_notes' => 'Catatan internal jangan tampil',
            'company_document_path' => 'company_documents/private-file.pdf',
        ]);
        $job = $this->listing(array_fill_keys([
            'title', 'category', 'job_type', 'location', 'description', 'education_requirement', 'requirements',
        ], $html), $company);
        $this->get(self::URL.$job->id)->assertOk()->assertSee($html)->assertDontSee($html, false)
            ->assertDontSee('Catatan internal jangan tampil')->assertDontSee('private-file.pdf');
    }

    public function test_missing_optional_values_have_fallback_without_fabricated_dates_or_salary(): void
    {
        $this->admin();
        $job = $this->listing(['salary_around' => null, 'deadline' => null, 'created_at' => '2026-10-01 12:00:00']);
        $response = $this->get(self::URL.$job->id)->assertOk()->assertDontSee('Rp 0')
            ->assertDontSee('01 Oct 2026')->assertDontSee('2026-10-01');
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'Belum tersedia'));
    }

    #[DataProvider('companyStatuses')]
    public function test_pending_job_is_readable_regardless_of_company_status_or_past_deadline(string $status): void
    {
        $this->admin();
        $company = CompanyProfile::factory()->create(['verification_status' => $status]);
        $job = $this->listing(['deadline' => '2020-01-01'], $company);
        $this->get(self::URL.$job->id)->assertOk()->assertSee('01 Jan 2020');
        $this->assertSame('pending_approval', $job->fresh()->status);
        $this->assertSame($status, $company->fresh()->verification_status);
    }

    public static function companyStatuses(): array
    {
        return [['pending'], ['verified'], ['rejected']];
    }

    public function test_detail_rechecks_status_after_link_was_opened_from_queue(): void
    {
        $this->admin();
        $job = $this->listing();
        $this->get(route('government.job-inspections.index'))->assertSee(route('government.job-inspections.show', $job), false);
        $job->update(['status' => 'closed']);
        $this->get(self::URL.$job->id)->assertNotFound();
        $this->assertSame('closed', $job->fresh()->status);
    }
}
