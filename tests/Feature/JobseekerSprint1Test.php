<?php

namespace Tests\Feature;

use App\Models\JobseekerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobseekerSprint1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * PBI 1.1.1 - Mendaftar akun dan memvalidasi identitas dengan NIK 16 digit yang valid.
     */
    public function test_pbi_1_1_1_jobseeker_can_register_with_valid_16_digit_nik_and_see_inspection_result(): void
    {
        $response = $this->post(route('jobseeker.register.submit'), [
            'name' => 'Bagaskoro Novian Widayanto',
            'email' => 'bagaskoro@example.com',
            'phone' => '081234567890',
            'nik' => '3273011505010001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('jobseeker.profile.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'bagaskoro@example.com',
            'role' => 'pencari_kerja',
        ]);

        $this->assertDatabaseHas('jobseeker_profiles', [
            'nik' => '3273011505010001',
            'nik_verified' => true,
            'province' => 'Jawa Barat',
            'gender' => 'Laki-laki',
        ]);

        $profilePage = $this->get(route('jobseeker.profile.index'));
        $profilePage->assertStatus(200);
        $profilePage->assertSee('Identitas Terverifikasi (NIK: 3273011505010001)');
        $profilePage->assertSee('TERVERIFIKASI (VALID)');
    }

    /**
     * PBI 1.1.1 - NIK kurang dari 16 digit, bukan angka, atau format tidak valid ditolak dengan pesan.
     */
    public function test_pbi_1_1_1_invalid_nik_is_rejected_with_clear_message(): void
    {
        // Kurang dari 16 digit
        $responseShort = $this->from(route('jobseeker.register'))->post(route('jobseeker.register.submit'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'nik' => '3273011505',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $responseShort->assertRedirect(route('jobseeker.register'));
        $responseShort->assertSessionHasErrors('nik');
        $this->assertGuest();

        // Format struktur tidak valid (semua nol)
        $responseZero = $this->from(route('jobseeker.register'))->post(route('jobseeker.register.submit'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'nik' => '0000000000000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $responseZero->assertRedirect(route('jobseeker.register'));
        $responseZero->assertSessionHasErrors('nik');
        $responseZero->assertSessionHas('nik_inspection', function (array $inspection) {
            return $inspection['valid'] === false
                && $inspection['status_label'] === 'DITOLAK (TIDAK VALID)';
        });
    }

    /**
     * PBI 1.1.1 - NIK ganda (sudah terdaftar) ditolak dengan pesan.
     */
    public function test_pbi_1_1_1_duplicate_nik_is_rejected_with_message(): void
    {
        $existingUser = User::factory()->create(['role' => 'pencari_kerja']);
        JobseekerProfile::create([
            'user_id' => $existingUser->id,
            'nik' => '3273011505010001',
            'nik_verified' => true,
        ]);

        $response = $this->from(route('jobseeker.register'))->post(route('jobseeker.register.submit'), [
            'name' => 'Pencari Kerja Baru',
            'email' => 'baru@example.com',
            'phone' => '081298765432',
            'nik' => '3273011505010001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('jobseeker.register'));
        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
    }

    /**
     * PBI 1.1.2 - Mengelola Profil Pencari Kerja (data diri, pendidikan, kompetensi, pengalaman, penghargaan)
     * dan memastikan indikator kelengkapan profil berubah sesuai bagian yang diisi.
     */
    public function test_pbi_1_1_2_profile_management_and_completeness_indicator_updates_dynamically(): void
    {
        $user = User::factory()->create([
            'name' => 'Bagaskoro Novian Widayanto',
            'phone' => '081234567890',
            'role' => 'pencari_kerja',
        ]);

        $profile = JobseekerProfile::create([
            'user_id' => $user->id,
            'nik' => '3273011505010001',
            'nik_verified' => true,
        ]);

        $this->actingAs($user);

        // Awalnya belum ada bagian yang lengkap -> 0%
        $this->assertSame(0, $profile->fresh()->calculateCompleteness()['percentage']);

        // 1. Lengkapi Data Diri -> 20%
        $this->put(route('jobseeker.profile.personal.update'), [
            'name' => 'Bagaskoro Novian Widayanto',
            'phone' => '081234567890',
            'birth_date' => '2001-05-15',
            'gender' => 'Laki-laki',
            'province' => 'Jawa Barat',
            'city' => 'Kota Bandung',
            'address' => 'Jl. Telekomunikasi No. 1, Bandung',
            'bio' => 'Lulusan Sistem Informasi yang berfokus pada pengembangan aplikasi web Laravel.',
            'expected_salary' => 6500000,
        ])->assertRedirect();

        $this->assertSame(20, $profile->fresh()->calculateCompleteness()['percentage']);

        // 2. Tambah Pendidikan -> 40%
        $this->post(route('jobseeker.profile.education.store'), [
            'institution_name' => 'Telkom University',
            'degree' => 'D4/S1',
            'major' => 'S1 Sistem Informasi',
            'start_year' => 2022,
            'end_year' => 2026,
        ])->assertRedirect();

        $this->assertSame(40, $profile->fresh()->calculateCompleteness()['percentage']);

        // 3. Tambah Kompetensi -> 60%
        $this->post(route('jobseeker.profile.skill.store'), [
            'skill_name' => 'Laravel & PHP',
            'proficiency_level' => 'Mahir',
        ])->assertRedirect();

        $this->assertSame(60, $profile->fresh()->calculateCompleteness()['percentage']);

        // 4. Tambah Pengalaman Kerja -> 80%
        $this->post(route('jobseeker.profile.experience.store'), [
            'company_name' => 'PT Digital Nusantara',
            'position' => 'Fullstack Web Developer Intern',
            'start_date' => '2025-01-10',
            'end_date' => '2025-06-30',
            'is_current' => false,
            'description' => 'Mengembangkan modul manajemen pencari kerja berbasis Laravel Blade.',
        ])->assertRedirect();

        $this->assertSame(80, $profile->fresh()->calculateCompleteness()['percentage']);

        // 5. Tambah Penghargaan -> 100%
        $this->post(route('jobseeker.profile.award.store'), [
            'title' => 'Juara 1 Web Development Competition',
            'issuer' => 'Kementerian Komunikasi dan Digital',
            'year' => 2025,
            'description' => 'Penghargaan aplikasi publik terbaik tingkat nasional.',
        ])->assertRedirect();

        $this->assertSame(100, $profile->fresh()->calculateCompleteness()['percentage']);

        // Pastikan seluruh data tampil kembali di halaman profil
        $profileResponse = $this->get(route('jobseeker.profile.index'));
        $profileResponse->assertStatus(200);
        $profileResponse->assertSee('100%');
        $profileResponse->assertSee('Telkom University');
        $profileResponse->assertSee('Laravel & PHP');
        $profileResponse->assertSee('PT Digital Nusantara');
        $profileResponse->assertSee('Juara 1 Web Development Competition');

        // Jika penghargaan dihapus, indikator kembali menjadi 80%
        $award = $profile->awards()->first();
        $this->delete(route('jobseeker.profile.award.destroy', $award))->assertRedirect();
        $this->assertSame(80, $profile->fresh()->calculateCompleteness()['percentage']);
    }

    /**
     * PBI 1.1.3 - Membuat CV Otomatis dari Profil Pencari Kerja:
     * CV tampil sesuai data profil, CV dapat diunduh, dan perubahan profil tercermin pada CV.
     */
    public function test_pbi_1_1_3_automatic_cv_displays_profile_data_can_be_downloaded_and_reflects_updates(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'name' => 'Bagaskoro Novian Widayanto',
            'email' => 'bagaskoro@example.com',
            'phone' => '081234567890',
            'role' => 'pencari_kerja',
        ]);

        $profile = JobseekerProfile::create([
            'user_id' => $user->id,
            'nik' => '3273011505010001',
            'nik_verified' => true,
            'birth_date' => '2001-05-15',
            'gender' => 'Laki-laki',
            'province' => 'Jawa Barat',
            'city' => 'Kota Bandung',
            'address' => 'Jl. Telekomunikasi No. 1',
            'bio' => 'Software Engineer fokus pada sistem ketenagakerjaan.',
        ]);

        $profile->educations()->create([
            'institution_name' => 'Telkom University',
            'degree' => 'S1',
            'major' => 'Sistem Informasi',
            'start_year' => 2022,
            'end_year' => 2026,
        ]);

        $profile->skills()->create([
            'skill_name' => 'Laravel Framework',
            'proficiency_level' => 'Mahir',
        ]);

        $profile->experiences()->create([
            'company_name' => 'PT Sikerja Indonesia',
            'position' => 'Backend Engineer',
            'start_date' => '2025-02-01',
            'is_current' => true,
            'description' => 'Membangun layanan verifikasi identitas dan CV otomatis.',
        ]);

        $profile->awards()->create([
            'title' => 'Best Capstone Project',
            'issuer' => 'Fakultas Rekayasa Industri',
            'year' => 2026,
            'description' => 'Proyek terbaik bidang Sistem Informasi.',
        ]);

        $this->actingAs($user);

        // 1. CV tampil sesuai data profil
        $cvPage = $this->get(route('jobseeker.cv.show'));
        $cvPage->assertStatus(200);
        $cvPage->assertSee('Bagaskoro Novian Widayanto');
        $cvPage->assertSee('3273011505010001');
        $cvPage->assertSee('Telkom University');
        $cvPage->assertSee('Laravel Framework');
        $cvPage->assertSee('PT Sikerja Indonesia');
        $cvPage->assertSee('Best Capstone Project');

        // 2. CV dapat diunduh sebagai file PDF
        $downloadResponse = $this->get(route('jobseeker.cv.download'));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $downloadResponse->getContent());
        $this->assertStringContainsString('Telkom University', $downloadResponse->getContent());

        // 3. Perubahan profil langsung tercermin pada CV dan hasil unduhan
        $this->put(route('jobseeker.profile.personal.update'), [
            'name' => 'Bagaskoro Novian Widayanto, S.Kom.',
            'phone' => '081234567890',
            'birth_date' => '2001-05-15',
            'gender' => 'Laki-laki',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'address' => 'Jl. Sudirman Kav. 52',
            'bio' => 'Lead System Analyst & Laravel Architect.',
            'expected_salary' => 12000000,
        ])->assertRedirect();

        $updatedCvPage = $this->get(route('jobseeker.cv.show'));
        $updatedCvPage->assertSee('Bagaskoro Novian Widayanto, S.Kom.');
        $updatedCvPage->assertSee('Jakarta Selatan, DKI Jakarta');
        $updatedCvPage->assertSee('Lead System Analyst & Laravel Architect.');

        $updatedDownload = $this->get(route('jobseeker.cv.download'));
        $this->assertStringContainsString('Lead System Analyst & Laravel Architect.', $updatedDownload->getContent());
    }
}

