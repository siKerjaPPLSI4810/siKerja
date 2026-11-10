<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobseekerProfile;
use App\Models\User;
use Tests\JobBoardTestCase;

class JobApplicationTest extends JobBoardTestCase
{
    public function test_tamu_harus_masuk_sebelum_melamar_atau_melihat_riwayat(): void
    {
        $job = JobPosting::factory()->published()->create();

        $this->get(route('jobs.show', $job))->assertOk()->assertSee('Masuk sebagai pencari kerja');
        $this->post(route('jobseeker.apply', $job))->assertRedirect(route('login'));
        $this->get(route('jobseeker.applications'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_akun_perusahaan_tidak_bisa_melamar_atau_melihat_riwayat(): void
    {
        $company = User::factory()->create(['role' => 'perusahaan']);
        $job = JobPosting::factory()->published()->create();

        $this->actingAs($company)->get(route('jobs.show', $job))->assertOk()->assertDontSee('Kirim lamaran');
        $this->post(route('jobseeker.apply', $job))->assertForbidden();
        $this->get(route('jobseeker.applications'))->assertForbidden();
        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_lamaran_dengan_catatan_muncul_di_riwayat_pencari_kerja(): void
    {
        $profile = JobseekerProfile::factory()->create(['nik_verified' => true]);
        $job = JobPosting::factory()->published()->create(['title' => 'Pengembang Backend']);

        $this->actingAs($profile->user)->get(route('jobs.show', $job))->assertOk()->assertSee('Kirim lamaran');
        $this->post(route('jobseeker.apply', $job), ['applicant_notes' => 'Pengalaman Laravel'])
            ->assertRedirect(route('jobseeker.applications'))
            ->assertSessionHas('status', 'Lamaran Anda berhasil dikirim.');

        $this->assertDatabaseHas('job_applications', [
            'job_posting_id' => $job->id,
            'jobseeker_profile_id' => $profile->id,
            'applicant_notes' => 'Pengalaman Laravel',
            'status' => 'terkirim',
        ]);
        $this->assertTrue($profile->refresh()->nik_verified);

        $this->get(route('jobseeker.applications'))->assertOk()->assertSee('Pengembang Backend')->assertSee('Terkirim');
        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Anda sudah melamar lowongan ini')
            ->assertDontSee('Kirim lamaran');
    }

    public function test_pencari_kerja_tidak_bisa_melamar_lowongan_yang_sama_dua_kali(): void
    {
        $application = JobApplication::factory()->create(['applicant_notes' => 'Catatan asli']);

        $this->actingAs($application->jobseeker->user)
            ->post(route('jobseeker.apply', $application->jobPosting), ['applicant_notes' => 'Catatan baru'])
            ->assertRedirect(route('jobs.show', $application->jobPosting))
            ->assertSessionHas('error', 'Anda telah mengirimkan lamaran untuk lowongan ini sebelumnya.');

        $this->assertDatabaseCount('job_applications', 1);
        $this->assertSame('Catatan asli', $application->refresh()->applicant_notes);
    }

    public function test_lowongan_yang_belum_dipublikasikan_tidak_bisa_dilamar(): void
    {
        $user = User::factory()->create(['role' => 'pencari_kerja']);
        $job = JobPosting::factory()->create();

        $this->actingAs($user)->post(route('jobseeker.apply', $job))->assertNotFound();
        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_catatan_lebih_dari_2000_karakter_ditolak(): void
    {
        $user = User::factory()->create(['role' => 'pencari_kerja']);
        $job = JobPosting::factory()->published()->create();

        $this->actingAs($user)->from(route('jobs.show', $job))
            ->post(route('jobseeker.apply', $job), ['applicant_notes' => str_repeat('a', 2001)])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHasErrors('applicant_notes');

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertDatabaseMissing('jobseeker_profiles', ['user_id' => $user->id]);
    }

    public function test_catatan_berbentuk_array_ditolak_dan_form_tetap_bisa_dibuka(): void
    {
        $user = User::factory()->create(['role' => 'pencari_kerja']);
        $job = JobPosting::factory()->published()->create();
        $detail = route('jobs.show', $job);

        $this->actingAs($user)->from($detail)
            ->post(route('jobseeker.apply', $job), ['applicant_notes' => ['Catatan tidak valid']])
            ->assertRedirect($detail)
            ->assertSessionHasErrors('applicant_notes');

        $this->get($detail)->assertOk()->assertSee('Kirim lamaran');
        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_lowongan_yang_melewati_tenggat_tidak_bisa_dilamar(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'pencari_kerja']);
        $job = JobPosting::factory()->published()->create(['deadline' => today()->subDay()]);

        $this->actingAs($user)->post(route('jobseeker.apply', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('error', 'Batas waktu pendaftaran lowongan ini sudah berakhir.');

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_lamaran_tanpa_catatan_bisa_dikirim_pada_hari_tenggat(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'pencari_kerja']);
        $job = JobPosting::factory()->published()->create(['deadline' => today()]);

        $this->actingAs($user)->post(route('jobseeker.apply', $job))
            ->assertRedirect(route('jobseeker.applications'));

        $this->assertDatabaseHas('job_applications', [
            'job_posting_id' => $job->id,
            'jobseeker_profile_id' => $user->jobseekerProfile()->firstOrFail()->id,
            'applicant_notes' => 'Melamar menggunakan profil terintegrasi siKERJA.',
            'status' => 'terkirim',
        ]);
    }

    public function test_riwayat_hanya_menampilkan_lamaran_milik_pengguna(): void
    {
        $owner = JobseekerProfile::factory()->create();
        $ownJob = JobPosting::factory()->published()->create(['title' => 'Lowongan Milik Andi']);
        $otherJob = JobPosting::factory()->published()->create(['title' => 'Lowongan Milik Budi']);

        JobApplication::factory()->for($owner, 'jobseeker')->for($ownJob)->create();
        JobApplication::factory()->for($otherJob)->create();

        $this->actingAs($owner->user)->get(route('jobseeker.applications'))
            ->assertOk()
            ->assertSee('Lowongan Milik Andi')
            ->assertDontSee('Lowongan Milik Budi');
    }

    public function test_riwayat_menampilkan_status_dan_penawaran_gaji_terbaru(): void
    {
        $application = JobApplication::factory()->create();

        $this->actingAs($application->jobseeker->user)->get(route('jobseeker.applications'))
            ->assertOk()
            ->assertSee('Terkirim');

        $application->forceFill(['status' => 'offering', 'offered_salary' => 9000000])->save();

        $this->get(route('jobseeker.applications'))
            ->assertOk()
            ->assertSee('Penawaran')
            ->assertSee('Rp 9.000.000')
            ->assertDontSee('Terkirim');
    }

    public function test_riwayat_diurutkan_terbaru_dengan_sepuluh_lamaran_per_halaman(): void
    {
        $this->freezeTime();
        $profile = JobseekerProfile::factory()->create();
        $jobFactory = JobPosting::factory()->published();
        $applicationFactory = JobApplication::factory()->for($profile, 'jobseeker');

        for ($index = 1; $index <= 11; $index++) {
            $job = $jobFactory->create(['title' => sprintf('Posisi-%02d', $index)]);
            $applicationFactory->for($job)->create(['applied_at' => now()->subDays(11 - $index)]);
        }

        $this->actingAs($profile->user)->get(route('jobseeker.applications'))
            ->assertOk()
            ->assertSeeInOrder(['Posisi-11', 'Posisi-10', 'Posisi-02'])
            ->assertDontSee('Posisi-01');

        $this->get(route('jobseeker.applications', ['page' => 2]))
            ->assertOk()
            ->assertSee('Posisi-01')
            ->assertDontSee('Posisi-02');
    }
}
