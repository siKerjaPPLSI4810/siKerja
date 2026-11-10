<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\JobBoardTestCase;

class JobSearchFilterTest extends JobBoardTestCase
{
    public function test_hanya_lowongan_published_yang_ditampilkan(): void
    {
        JobPosting::factory()->published()->create(['title' => 'Software Developer Testing']);
        JobPosting::factory()->create(['title' => 'Lowongan Belum Disetujui Testing']);

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Software Developer Testing')
            ->assertDontSee('Lowongan Belum Disetujui Testing');
    }

    public function test_detail_lowongan_hanya_tersedia_setelah_dipublikasikan(): void
    {
        $published = JobPosting::factory()->published()->create();
        $pending = JobPosting::factory()->create();

        $this->get(route('jobs.show', $published))->assertOk()->assertSee($published->title);
        $this->get(route('jobs.show', $pending))->assertNotFound();
    }

    #[TestWith(['title'])]
    #[TestWith(['description'])]
    #[TestWith(['requirements'])]
    public function test_pencarian_lowongan_berdasarkan_kata_kunci(string $field): void
    {
        $matching = JobPosting::factory()->published()->create([$field => 'Laravel Testing']);
        $other = JobPosting::factory()->published()->create(['title' => 'Desainer Grafis']);

        $this->get(route('jobs.index', ['q' => 'Laravel']))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($other->title);
    }

    public function test_pencarian_mencakup_nama_perusahaan(): void
    {
        $company = CompanyProfile::factory()->create(['company_name' => 'PT Karya Nusantara']);
        $matching = JobPosting::factory()->for($company, 'company')->published()->create(['title' => 'Backend Developer']);
        JobPosting::factory()->published()->create(['title' => 'Desainer Grafis']);

        $this->get(route('jobs.index', ['q' => 'Nusantara']))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee('Desainer Grafis');
    }

    #[TestWith(['category', 'Keuangan', 'Keuangan', 'Pemasaran'])]
    #[TestWith(['location', 'Jak', 'Jakarta Selatan', 'Bandung'])]
    #[TestWith(['job_type', 'Kontrak', 'Kontrak', 'Magang'])]
    public function test_filter_lowongan_berdasarkan_atribut(string $field, string $filter, string $match, string $other): void
    {
        JobPosting::factory()->published()->create(['title' => 'Lowongan Pilihan', $field => $match]);
        JobPosting::factory()->published()->create(['title' => 'Lowongan Lain', $field => $other]);

        $this->get(route('jobs.index', [$field => $filter]))
            ->assertOk()
            ->assertSee('Lowongan Pilihan')
            ->assertDontSee('Lowongan Lain');
    }

    public function test_filter_lowongan_berdasarkan_rentang_gaji(): void
    {
        JobPosting::factory()->published()->create(['title' => 'Junior Developer', 'salary_around' => 4500000]);
        JobPosting::factory()->published()->create(['title' => 'Frontend Developer', 'salary_around' => 8500000]);
        JobPosting::factory()->published()->create(['title' => 'Senior Developer', 'salary_around' => 12500000]);

        $this->get(route('jobs.index', ['min_salary' => 5000000, 'max_salary' => 10000000]))
            ->assertOk()
            ->assertSee('Frontend Developer')
            ->assertDontSee('Junior Developer')
            ->assertDontSee('Senior Developer');

        $this->get(route('jobs.index', ['max_salary' => 5000000]))
            ->assertOk()
            ->assertSee('Junior Developer')
            ->assertDontSee('Frontend Developer');
    }

    public function test_validasi_rentang_gaji_tidak_valid(): void
    {
        $this->get(route('jobs.index', ['min_salary' => 12000000, 'max_salary' => 5000000]))
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHasErrors(['max_salary' => 'Gaji maksimum tidak boleh lebih kecil dari gaji minimum.'])
            ->assertSessionHasInput('min_salary', '12000000')
            ->assertSessionHasInput('max_salary', '5000000');
    }

    public function test_validasi_menolak_filter_berbentuk_array(): void
    {
        $this->get(route('jobs.index', ['q' => ['Laravel']]))
            ->assertRedirect(route('jobs.index'))
            ->assertSessionHasErrors('q');
    }
}
