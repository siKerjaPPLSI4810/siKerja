<?php 

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_lowongan_published_yang_ditampilkan(): void
    {
        // Membuat akun perusahaan untuk data testing
        $user = User::factory()->create();

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $user->id,
            'company_name' => 'PT Testing siKERJA',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lowongan yang sudah dipublikasikan
        DB::table('job_postings')->insert([
            'company_profile_id' => $companyId,
            'title' => 'Software Developer Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta Selatan',
            'education_requirement' => 'S1',
            'description' => 'Pengembangan aplikasi',
            'requirements' => 'Menguasai Laravel',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lowongan yang belum disetujui
        DB::table('job_postings')->insert([
            'company_profile_id' => $companyId,
            'title' => 'Lowongan Belum Disetujui Testing',
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Bandung',
            'education_requirement' => 'S1',
            'description' => 'Lowongan belum aktif',
            'requirements' => 'Menguasai PHP',
            'salary_around' => 5000000,
            'status' => 'pending_approval',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mengakses halaman pencarian lowongan
        $response = $this->get('/lowongan');

        // Memastikan hasil sesuai
        $response->assertStatus(200);
        $response->assertSee('Software Developer Testing');
        $response->assertDontSee('Lowongan Belum Disetujui Testing');
    }
    
    public function test_pencarian_lowongan_berdasarkan_kata_kunci(): void
    {
        $user = User::factory()->create();

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $user->id,
            'company_name' => 'PT Testing Pencarian',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = [
            'company_profile_id' => $companyId,
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'requirements' => 'Pengalaman kerja',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('job_postings')->insert([
            array_merge($data, [
                'title' => 'Backend Laravel Developer',
                'description' => 'Mengembangkan aplikasi Laravel',
            ]),
            array_merge($data, [
                'title' => 'Desainer Grafis',
                'description' => 'Membuat desain visual',
            ]),
        ]);

        $response = $this->get('/lowongan?q=Laravel');

        $response->assertStatus(200);
        $response->assertSee('Backend Laravel Developer');
        $response->assertDontSee('Desainer Grafis');
    }
    
    public function test_filter_lowongan_berdasarkan_kategori(): void
    {
        $user = User::factory()->create();

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $user->id,
            'company_name' => 'PT Testing Kategori',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = [
            'company_profile_id' => $companyId,
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'description' => 'Lowongan pekerjaan',
            'requirements' => 'Pengalaman kerja',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('job_postings')->insert([
            array_merge($data, [
                'title' => 'Software Engineer Testing',
                'category' => 'Teknologi Informasi',
            ]),
            array_merge($data, [
                'title' => 'Staff Keuangan Testing',
                'category' => 'Keuangan',
            ]),
        ]);

        $response = $this->get('/lowongan?category=Teknologi%20Informasi');

        $response->assertStatus(200);
        $response->assertSee('Software Engineer Testing');
        $response->assertDontSee('Staff Keuangan Testing');
    }
    
    public function test_filter_lowongan_berdasarkan_lokasi(): void
    {
        $user = User::factory()->create();

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $user->id,
            'company_name' => 'PT Testing Lokasi',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = [
            'company_profile_id' => $companyId,
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'education_requirement' => 'S1',
            'description' => 'Lowongan pekerjaan',
            'requirements' => 'Pengalaman kerja',
            'salary_around' => 8000000,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('job_postings')->insert([
            array_merge($data, [
                'title' => 'Backend Developer Jakarta Testing',
                'location' => 'Jakarta Selatan',
            ]),
            array_merge($data, [
                'title' => 'Frontend Developer Bandung Testing',
                'location' => 'Kota Bandung',
            ]),
        ]);

        // Mencari lokasi menggunakan sebagian nama wilayah
        $response = $this->get('/lowongan?location=Jak');

        $response->assertStatus(200);
        $response->assertSee('Backend Developer Jakarta Testing');
        $response->assertDontSee('Frontend Developer Bandung Testing');
    }
    
    public function test_filter_lowongan_berdasarkan_rentang_gaji(): void
    {
        $user = User::factory()->create();

        $companyId = DB::table('company_profiles')->insertGetId([
            'user_id' => $user->id,
            'company_name' => 'PT Testing Gaji',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = [
            'company_profile_id' => $companyId,
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta Selatan',
            'education_requirement' => 'S1',
            'description' => 'Lowongan pekerjaan',
            'requirements' => 'Pengalaman kerja',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('job_postings')->insert([
            array_merge($data, [
                'title' => 'Junior Developer Testing',
                'salary_around' => 4500000,
            ]),
            array_merge($data, [
                'title' => 'Frontend Developer Testing',
                'salary_around' => 8500000,
            ]),
            array_merge($data, [
                'title' => 'Senior Developer Testing',
                'salary_around' => 12500000,
            ]),
        ]);

        // Filter gaji Rp5.000.000 sampai Rp10.000.000
        $response = $this->get('/lowongan?min_salary=5000000&max_salary=10000000');

        $response->assertStatus(200);
        $response->assertSee('Frontend Developer Testing');
        $response->assertDontSee('Junior Developer Testing');
        $response->assertDontSee('Senior Developer Testing');
    }
    
    public function test_validasi_rentang_gaji_tidak_valid(): void
    {
        // Gaji minimum lebih besar daripada maksimum
        $response = $this->get(
            '/lowongan?min_salary=12000000&max_salary=5000000'
        );

        // Harus diarahkan ke halaman pencarian tanpa parameter
        $response->assertRedirect(route('jobs.index'));

        // Harus menampilkan error validasi gaji maksimum
        $response->assertSessionHasErrors('max_salary');

        // Pilihan sebelumnya harus tetap tersimpan
        $response->assertSessionHasInput('min_salary', '12000000');
        $response->assertSessionHasInput('max_salary', '5000000');
    }

}
