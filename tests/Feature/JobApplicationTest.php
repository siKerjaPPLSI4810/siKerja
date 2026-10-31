<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_belum_login_tidak_bisa_melamar(): void
    {
        // Mencoba mengirim lamaran tanpa login
        $response = $this->post(
            route('jobseeker.apply', ['id' => 1])
        );

        // Pengguna seharusnya diarahkan ke halaman login
        $response->assertRedirect(route('login'));

        // Tidak ada lamaran yang tersimpan
        $this->assertDatabaseCount('job_applications', 0);
    }
    
    public function test_pencari_kerja_berhasil_mengirim_lamaran(): void
    {
        // Membuat akun pencari kerja
       $jobseeker = User::factory()->create([
            'role' => 'pencari_kerja',
            'is_active' => true,
        ]);

        // Membuat akun perusahaan
        $companyUser = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,    
        ]);

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $companyUser->id,
            'company_name' => 'PT Testing Lamaran',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Membuat lowongan aktif
        $jobId = DB::table('job_postings')->insertGetId([
            'company_profile_id' => $companyId,
            'title' => 'Laravel Developer Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta Selatan',
            'education_requirement' => 'S1',
            'description' => 'Pengembangan aplikasi Laravel',
            'requirements' => 'Menguasai PHP dan Laravel',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Login sebagai pencari kerja dan mengirim lamaran
        $response = $this->actingAs($jobseeker)->post(
            route('jobseeker.apply', ['id' => $jobId]),
            [
                'applicant_notes' => 'Saya tertarik melamar posisi ini.',
            ]
        );

        // Memastikan pengguna diarahkan ke halaman lamaran
        $response->assertRedirect(route('jobseeker.applications'));

        // Memastikan lamaran tersimpan dengan status terkirim
        $this->assertDatabaseHas('job_applications', [
            'job_posting_id' => $jobId,
            'status' => 'terkirim',
            'applicant_notes' => 'Saya tertarik melamar posisi ini.',
        ]);

        // Memastikan hanya satu lamaran tersimpan
        $this->assertDatabaseCount('job_applications', 1);
    }
    
    public function test_pencari_kerja_tidak_bisa_melamar_dua_kali(): void
    {
        $jobseeker = User::factory()->create([
            'role' => 'pencari_kerja',
            'is_active' => true,
        ]);

        $companyUser = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,
        ]);

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $companyUser->id,
            'company_name' => 'PT Testing Duplikasi',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('job_postings')->insertGetId([
            'company_profile_id' => $companyId,
            'title' => 'Backend Developer Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'description' => 'Mengembangkan aplikasi',
            'requirements' => 'Menguasai Laravel',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lamaran pertama
        $this->actingAs($jobseeker)->post(
            route('jobseeker.apply', ['id' => $jobId])
        )->assertRedirect(route('jobseeker.applications'));

        // Mencoba mengirim lamaran yang sama
        $response = $this->from('/lowongan/' . $jobId)->post(
            route('jobseeker.apply', ['id' => $jobId])
        );

        // Sistem harus menolak lamaran kedua
        $response->assertRedirect('/lowongan/' . $jobId);
        $response->assertSessionHas('error');

        // Database tetap hanya menyimpan satu lamaran
        $this->assertDatabaseCount('job_applications', 1);
    }
    
    public function test_tidak_bisa_melamar_lowongan_belum_dipublikasikan(): void
    {
        $jobseeker = User::factory()->create([
            'role' => 'pencari_kerja',
            'is_active' => true,
        ]);

        $companyUser = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,
        ]);

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $companyUser->id,
            'company_name' => 'PT Testing Pending',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Membuat lowongan yang belum disetujui
        $jobId = DB::table('job_postings')->insertGetId([
            'company_profile_id' => $companyId,
            'title' => 'Developer Belum Disetujui',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Bandung',
            'education_requirement' => 'S1',
            'description' => 'Lowongan masih menunggu persetujuan',
            'requirements' => 'Menguasai PHP',
            'salary_around' => 7000000,
            'status' => 'pending_approval',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mencoba melamar lowongan yang belum dipublikasikan
        $response = $this->actingAs($jobseeker)->post(
            route('jobseeker.apply', ['id' => $jobId])
        );

        // Backend harus menolak dengan HTTP 404
        $response->assertNotFound();

        // Tidak boleh ada lamaran yang tersimpan
        $this->assertDatabaseCount('job_applications', 0);
    }
    
    public function test_catatan_lamaran_tidak_boleh_melebihi_2000_karakter(): void
    {
        $jobseeker = User::factory()->create([
            'role' => 'pencari_kerja',
            'is_active' => true,
        ]);

        $companyUser = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,
        ]);

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $companyUser->id,
            'company_name' => 'PT Testing Validasi',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('job_postings')->insertGetId([
            'company_profile_id' => $companyId,
            'title' => 'Software Developer Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'description' => 'Mengembangkan aplikasi',
            'requirements' => 'Menguasai Laravel',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mengirim catatan lebih dari 2000 karakter
        $response = $this->actingAs($jobseeker)->post(
            route('jobseeker.apply', ['id' => $jobId]),
            [
                'applicant_notes' => str_repeat('A', 2001),
            ]
        );

        // Sistem harus menolak input yang tidak valid
        $response->assertSessionHasErrors('applicant_notes');

        // Tidak boleh ada lamaran yang tersimpan
        $this->assertDatabaseCount('job_applications', 0);
    }
    
    public function test_akun_perusahaan_tidak_bisa_melamar(): void
    {
        // Membuat akun perusahaan yang aktif
        $company = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,
        ]);

        // Mencoba mengirim lamaran sebagai perusahaan
        $response = $this->actingAs($company)->post(
            route('jobseeker.apply', ['id' => 1])
        );

        // Middleware harus menolak akses
        $response->assertRedirect(
            route('company.dashboard')
        );

        // Tidak boleh ada lamaran yang tersimpan
        $this->assertDatabaseCount('job_applications', 0);
    }
    
    public function test_tidak_bisa_melamar_lowongan_yang_sudah_deadline(): void
    {
        $jobseeker = User::factory()->create([
            'role' => 'pencari_kerja',
            'is_active' => true,
        ]);

        $companyUser = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,
        ]);

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $companyUser->id,
            'company_name' => 'PT Testing Deadline',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('job_postings')->insertGetId([
            'company_profile_id' => $companyId,
            'title' => 'Developer Deadline Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'description' => 'Lowongan testing',
            'requirements' => 'Menguasai Laravel',
            'salary_around' => 8000000,
            'status' => 'published',
            'deadline' => today()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mencoba melamar lowongan yang deadline-nya kemarin
        $response = $this->actingAs($jobseeker)
            ->from('/lowongan/' . $jobId)
            ->post(route('jobseeker.apply', ['id' => $jobId]));

        // Sistem harus menolak lamaran
        $response->assertRedirect('/lowongan/' . $jobId);
        $response->assertSessionHas(
            'error',
            'Batas waktu pendaftaran lowongan ini sudah berakhir.'
        );

        // Tidak ada lamaran yang tersimpan
        $this->assertDatabaseCount('job_applications', 0);
    }
    
    public function test_bisa_melamar_pada_hari_deadline(): void
    {
        $jobseeker = User::factory()->create([
            'role' => 'pencari_kerja',
            'is_active' => true,
        ]);

        $companyUser = User::factory()->create([
            'role' => 'perusahaan',
            'is_active' => true,
        ]);

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $companyUser->id,
            'company_name' => 'PT Testing Hari Deadline',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('job_postings')->insertGetId([
            'company_profile_id' => $companyId,
            'title' => 'Developer Hari Deadline Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'description' => 'Lowongan testing',
            'requirements' => 'Menguasai Laravel',
            'salary_around' => 8000000,
            'status' => 'published',
            'deadline' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Melamar pada hari terakhir pendaftaran
        $response = $this->actingAs($jobseeker)->post(
            route('jobseeker.apply', ['id' => $jobId])
        );

        // Lamaran harus berhasil dikirim
        $response->assertRedirect(route('jobseeker.applications'));

        $this->assertDatabaseHas('job_applications', [
            'job_posting_id' => $jobId,
            'status' => 'terkirim',
        ]);

        $this->assertDatabaseCount('job_applications', 1);
    }
}
