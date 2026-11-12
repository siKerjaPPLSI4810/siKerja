<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySprint1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * PBI 2.1.1 - Perusahaan dapat mendaftar akun dan mengirim identitas perusahaan (Skenario Valid).
     */
    public function test_pbi_2_1_1_company_can_register_with_valid_identity_and_see_verified_result(): void
    {
        Storage::fake('public');

        $document = UploadedFile::fake()->create('legalitas_pt_maju.pdf', 500, 'application/pdf');

        $response = $this->post(route('company.register.submit'), [
            'company_name' => 'PT Maju Bersama Teknologi',
            'name' => 'Abyan Sapta Ramadhan',
            'email' => 'hr@majubersama.co.id',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'npwp' => '012345678901000',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'address' => 'Jl. Asia Afrika No. 10',
            'website' => 'https://majubersama.co.id',
            'company_document' => $document,
            'scenario_override' => 'valid',
        ]);

        $response->assertRedirect(route('company.profile.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'hr@majubersama.co.id',
            'role' => 'perusahaan',
            'name' => 'Abyan Sapta Ramadhan',
        ]);

        $this->assertDatabaseHas('company_profiles', [
            'company_name' => 'PT Maju Bersama Teknologi',
            'verification_status' => 'verified',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
        ]);

        $profilePage = $this->get(route('company.profile.index'));
        $profilePage->assertStatus(200);
        $profilePage->assertSee('PT Maju Bersama Teknologi');
        $profilePage->assertSee('TERVERIFIKASI (VALID)');
        $profilePage->assertSee('SKENARIO VALID (TERVERIFIKASI)');
    }

    /**
     * PBI 2.1.1 - Perusahaan mendaftar dengan identitas yang menghasilkan status Belum Dapat Dipastikan.
     */
    public function test_pbi_2_1_1_company_can_register_and_see_unconfirmed_pending_result(): void
    {
        Storage::fake('public');

        $document = UploadedFile::fake()->create('nib_akta.pdf', 300, 'application/pdf');

        $response = $this->post(route('company.register.submit'), [
            'company_name' => 'CV Sejahtera Mandiri',
            'name' => 'Budi Pratama',
            'email' => 'budi@sejahtera.co.id',
            'phone' => '081987654321',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'npwp' => '3273011505010001',
            'company_document' => $document,
            'scenario_override' => 'unconfirmed',
        ]);

        $response->assertRedirect(route('company.profile.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('company_profiles', [
            'company_name' => 'CV Sejahtera Mandiri',
            'verification_status' => 'pending',
        ]);

        $profilePage = $this->get(route('company.profile.index'));
        $profilePage->assertStatus(200);
        $profilePage->assertSee('CV Sejahtera Mandiri');
        $profilePage->assertSee('BELUM DAPAT DIPASTIKAN (MENUNGGU PENINJAUAN)');
        $profilePage->assertSee('SKENARIO BELUM DAPAT DIPASTIKAN');
    }

    /**
     * PBI 2.1.1 - NPWP tidak valid (format angka salah atau dummy) ditolak dengan pesan kesalahan (Skenario Tidak Valid).
     */
    public function test_pbi_2_1_1_invalid_npwp_is_rejected_with_error_message(): void
    {
        $response = $this->from(route('company.register'))->post(route('company.register.submit'), [
            'company_name' => 'PT Cacat Format',
            'name' => 'Seseorang',
            'email' => 'seseorang@perusahaan.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'npwp' => '12345', // Kurang dari 15 digit
        ]);

        $response->assertRedirect(route('company.register'));
        $response->assertSessionHasErrors('npwp');
        $this->assertGuest();

        // NPWP angka sama (dummy)
        $responseDummy = $this->from(route('company.register'))->post(route('company.register.submit'), [
            'company_name' => 'PT Dummy',
            'name' => 'Dummy Person',
            'email' => 'dummy@perusahaan.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'npwp' => '000000000000000',
        ]);

        $responseDummy->assertRedirect(route('company.register'));
        $responseDummy->assertSessionHasErrors('npwp');
        $this->assertGuest();
    }

    /**
     * PBI 2.1.1 - NPWP ganda (sudah digunakan oleh perusahaan lain) ditolak (Skenario Tidak Valid).
     */
    public function test_pbi_2_1_1_duplicate_npwp_is_rejected(): void
    {
        $existingCompanyUser = User::factory()->create(['role' => 'perusahaan']);
        CompanyProfile::create([
            'user_id' => $existingCompanyUser->id,
            'company_name' => 'PT Perdana Mandiri',
            'npwp' => '01.234.567.8-901.000',
            'verification_status' => 'verified',
        ]);

        $response = $this->from(route('company.register'))->post(route('company.register.submit'), [
            'company_name' => 'PT Duplikat Makmur',
            'name' => 'Pelamar Baru',
            'email' => 'duplikat@makmur.co.id',
            'phone' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'npwp' => '012345678901000', // Nomor sama tanpa tanda baca
        ]);

        $response->assertRedirect(route('company.register'));
        $response->assertSessionHasErrors('npwp');
        $this->assertGuest();
    }

    /**
     * PBI 2.1.1 - Endpoint AJAX cek identitas menampilkan hasil pemeriksaan untuk ketiga skenario.
     */
    public function test_pbi_2_1_1_identity_check_endpoint_returns_results_for_all_scenarios(): void
    {
        // Skenario 1: Valid
        $responseValid = $this->postJson(route('company.identity.check'), [
            'company_name' => 'PT Inovasi Bangsa',
            'npwp' => '012345678901000',
            'scenario_override' => 'valid',
        ]);
        $responseValid->assertStatus(200);
        $responseValid->assertJson([
            'status' => 'verified',
            'status_label' => 'TERVERIFIKASI (VALID)',
            'valid' => true,
        ]);

        // Skenario 2: Tidak Valid (NPWP salah)
        $responseInvalid = $this->postJson(route('company.identity.check'), [
            'company_name' => 'PT Salah Format',
            'npwp' => '999', // salah
        ]);
        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJson([
            'status' => 'rejected',
            'status_label' => 'DITOLAK (TIDAK VALID)',
            'valid' => false,
        ]);

        // Skenario 3: Belum Dapat Dipastikan
        $responseUnconfirmed = $this->postJson(route('company.identity.check'), [
            'company_name' => 'PT Menunggu Verifikasi',
            'npwp' => '012345678901000',
            'scenario_override' => 'unconfirmed',
        ]);
        $responseUnconfirmed->assertStatus(200);
        $responseUnconfirmed->assertJson([
            'status' => 'pending',
            'status_label' => 'BELUM DAPAT DIPASTIKAN (MENUNGGU PENINJAUAN)',
        ]);
    }

    /**
     * PBI 2.1.2 - Profil perusahaan dapat dilihat oleh akun perusahaan.
     */
    public function test_pbi_2_1_2_company_can_view_its_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'perusahaan',
            'name' => 'Siti Aminah',
            'email' => 'siti@inovasi.co.id',
            'phone' => '081299998888',
        ]);

        CompanyProfile::create([
            'user_id' => $user->id,
            'company_name' => 'PT Inovasi Terdepan',
            'npwp' => '01.234.567.8-901.000',
            'address' => 'Gedung Cyber 2 Lantai 5',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'website' => 'https://inovasiterdepan.co.id',
            'verification_status' => 'verified',
            'verification_notes' => 'Telah terverifikasi resmi oleh Dinas Ketenagakerjaan.',
        ]);

        $response = $this->actingAs($user)->get(route('company.profile.index'));

        $response->assertStatus(200);
        $response->assertSee('PT Inovasi Terdepan');
        $response->assertSee('Siti Aminah');
        $response->assertSee('siti@inovasi.co.id');
        $response->assertSee('081299998888');
        $response->assertSee('01.234.567.8-901.000');
        $response->assertSee('DKI Jakarta');
        $response->assertSee('Jakarta Selatan');
        $response->assertSee('https://inovasiterdepan.co.id');
        $response->assertSee('TERVERIFIKASI (VALID)');
    }

    /**
     * PBI 2.1.2 - Pengguna bukan perusahaan tidak dapat mengakses profil perusahaan.
     */
    public function test_pbi_2_1_2_non_company_user_cannot_access_company_profile(): void
    {
        // Tamu (guest) dialihkan ke login
        $guestResponse = $this->get(route('company.profile.index'));
        $guestResponse->assertRedirect(route('company.login'));

        // Akun pencari kerja mendapatkan 403 Forbidden
        $jobseeker = User::factory()->create(['role' => 'pencari_kerja']);
        $forbiddenResponse = $this->actingAs($jobseeker)->get(route('company.profile.index'));
        $forbiddenResponse->assertStatus(403);
    }

    /**
     * PBI 2.1.2 - Validasi data wajib dan format berjalan saat mengubah profil.
     */
    public function test_pbi_2_1_2_validation_of_required_and_formatted_fields_runs(): void
    {
        $user = User::factory()->create(['role' => 'perusahaan']);
        CompanyProfile::create([
            'user_id' => $user->id,
            'company_name' => 'PT Validasi Uji',
        ]);

        // Form wajib kosong
        $responseEmpty = $this->actingAs($user)->from(route('company.profile.edit'))->put(route('company.profile.update'), [
            'company_name' => '',
            'name' => '',
            'email' => '',
            'phone' => '',
        ]);

        $responseEmpty->assertRedirect(route('company.profile.edit'));
        $responseEmpty->assertSessionHasErrors(['company_name', 'name', 'email', 'phone']);

        // Format format salah: email bukan email, format website bukan url, phone kurang dari 9
        $responseInvalidFormat = $this->actingAs($user)->from(route('company.profile.edit'))->put(route('company.profile.update'), [
            'company_name' => 'PT Format Salah',
            'name' => 'PIC Name',
            'email' => 'bukan-email-valid',
            'phone' => '12345', // kurang dari 9 digit
            'website' => 'http:// bukan url valid!',
        ]);

        $responseInvalidFormat->assertRedirect(route('company.profile.edit'));
        $responseInvalidFormat->assertSessionHasErrors(['email', 'phone', 'website']);
    }

    /**
     * PBI 2.1.2 - Perubahan profil perusahaan berhasil disimpan.
     */
    public function test_pbi_2_1_2_company_profile_update_is_saved_successfully(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role' => 'perusahaan',
            'name' => 'Nama Lama',
            'email' => 'lama@perusahaan.co.id',
            'phone' => '081111111111',
        ]);

        $profile = CompanyProfile::create([
            'user_id' => $user->id,
            'company_name' => 'PT Nama Lama',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'address' => 'Alamat Lama No. 1',
            'website' => 'https://namalama.co.id',
        ]);

        $newDoc = UploadedFile::fake()->create('dokumen_baru.pdf', 800, 'application/pdf');

        $response = $this->actingAs($user)->from(route('company.profile.edit'))->put(route('company.profile.update'), [
            'company_name' => 'PT Solusi Digital Terkini',
            'name' => 'Abyan Sapta Ramadhan',
            'email' => 'abyan@solusidigital.co.id',
            'phone' => '081298765432',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Pusat',
            'address' => 'Jl. M.H. Thamrin No. 28',
            'website' => 'https://solusidigital.co.id',
            'npwp' => '012345678901000',
            'company_document' => $newDoc,
        ]);

        $response->assertRedirect(route('company.profile.index'));
        $response->assertSessionHas('status', 'Profil perusahaan berhasil diperbarui dan perubahan tersimpan.');

        // Pastikan perubahan data tersimpan di tabel users
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Abyan Sapta Ramadhan',
            'email' => 'abyan@solusidigital.co.id',
            'phone' => '081298765432',
        ]);

        // Pastikan perubahan data tersimpan di tabel company_profiles
        $this->assertDatabaseHas('company_profiles', [
            'id' => $profile->id,
            'company_name' => 'PT Solusi Digital Terkini',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Pusat',
            'address' => 'Jl. M.H. Thamrin No. 28',
            'website' => 'https://solusidigital.co.id',
        ]);

        // Unduh dokumen
        $downloadResponse = $this->actingAs($user)->get(route('company.profile.document.download'));
        $downloadResponse->assertStatus(200);
    }
}
