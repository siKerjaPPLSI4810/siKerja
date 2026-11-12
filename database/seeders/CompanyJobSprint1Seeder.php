<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanyJobSprint1Seeder extends Seeder
{
    /**
     * Seed data contoh Perusahaan & Lowongan Kerja 
     */
    public function run(): void
    {
        $companyUser = User::updateOrCreate(
            ['email' => 'hr@solusitekno.co.id'],
            [
                'name' => 'Rifqi Ahmad Fauzy (HR Perusahaan)',
                'phone' => '081234567891',
                'password' => Hash::make('password123'),
                'role' => 'perusahaan',
                'is_active' => true,
            ]
        );

        $company = CompanyProfile::updateOrCreate(
            ['user_id' => $companyUser->id],
            [
                'company_name' => 'PT Solusi Teknologi Nusantara',
                'npwp' => '01.234.567.8-012.000',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Selatan',
                'address' => 'Gedung Cyber 2 Lt. 15, Jl. H.R. Rasuna Said',
                'website' => 'https://solusitekno.co.id',
                'verification_status' => 'verified',
                'verification_notes' => 'Pemeriksaan berhasil: Identitas dan NPWP perusahaan valid serta terverifikasi.',
            ]
        );

        if ($company->jobPostings()->count() === 0) {
            JobPosting::create([
                'company_profile_id' => $company->id,
                'title' => 'Senior Fullstack Web Developer',
                'category' => 'Teknologi Informasi',
                'job_type' => 'Penuh Waktu',
                'location' => 'Jakarta Selatan',
                'education_requirement' => 'D4/S1',
                'description' => 'Merancang, membangun, dan memelihara aplikasi web skala nasional berkinerja tinggi.',
                'requirements' => 'Pengalaman minimal 3 tahun menggunakan PHP Laravel dan basis data relasional MySQL.',
                'quota' => 2,
                'salary_around' => 12500000,
                'status' => 'published',
                'deadline' => now()->addDays(20)->format('Y-m-d'),
            ]);

            JobPosting::create([
                'company_profile_id' => $company->id,
                'title' => 'Backend Security Engineer',
                'category' => 'Teknologi Informasi',
                'job_type' => 'Kontrak',
                'location' => 'Jakarta Selatan',
                'education_requirement' => 'D4/S1',
                'description' => 'Bertanggung jawab atas audit keamanan transmisi data dan perlindungan aplikasi.',
                'requirements' => 'Memahami standar keamanan aplikasi web dan pengalaman minimal 2 tahun.',
                'quota' => 1,
                'salary_around' => 14000000,
                'status' => 'pending_approval',
                'deadline' => now()->addDays(30)->format('Y-m-d'),
            ]);

            JobPosting::create([
                'company_profile_id' => $company->id,
                'title' => 'Staf Administrasi Gudang',
                'category' => 'Logistik & Transportasi',
                'job_type' => 'Penuh Waktu',
                'location' => 'Jakarta Selatan',
                'education_requirement' => 'SMA/SMK',
                'description' => 'Melakukan pencatatan arus keluar masuk barang gudang dan pelaporan logistik harian.',
                'requirements' => 'Teliti, jujur, disiplin, dan menguasai pengolahan data spreadsheet dasar.',
                'quota' => 2,
                'salary_around' => 3500000,
                'status' => 'rejected',
                'rejection_reason' => 'Estimasi gaji di bawah standar Upah Minimum Provinsi DKI Jakarta. Harap sesuaikan gaji.',
                'deadline' => now()->addDays(14)->format('Y-m-d'),
            ]);

            JobPosting::create([
                'company_profile_id' => $company->id,
                'title' => 'UI UX Designer Intern',
                'category' => 'Teknologi Informasi',
                'job_type' => 'Magang',
                'location' => 'Kota Bandung',
                'education_requirement' => 'D3',
                'description' => 'Membantu perancangan prototipe antarmuka pengguna aplikasi layanan ketenagakerjaan.',
                'requirements' => 'Menguasai dasar penggunaan Figma dan prinsip desain responsif.',
                'quota' => 1,
                'salary_around' => 4500000,
                'status' => 'draft',
                'deadline' => now()->addDays(15)->format('Y-m-d'),
            ]);
        }
    }
}