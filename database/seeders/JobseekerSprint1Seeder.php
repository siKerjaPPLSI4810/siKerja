<?php

namespace Database\Seeders;

use App\Models\JobseekerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JobseekerSprint1Seeder extends Seeder
{
    /**
     * Seed data contoh Pencari Kerja untuk Sprint 1 (PBI 1.1.1, 1.1.2, 1.1.3).
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'bagaskoro@sikerja.id'],
            [
                'name' => 'Bagaskoro Novian Widayanto',
                'phone' => '081234567890',
                'password' => Hash::make('password123'),
                'role' => 'pencari_kerja',
                'is_active' => true,
            ]
        );

        $profile = JobseekerProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nik' => '3273011505020001',
                'nik_verified' => true,
                'birth_date' => '2002-05-15',
                'gender' => 'Laki-laki',
                'province' => 'Jawa Barat',
                'city' => 'Kota Bandung',
                'address' => 'Jl. Telekomunikasi No. 1, Terusan Buahbatu, Bandung',
                'bio' => 'Lulusan S1 Sistem Informasi yang memiliki keahlian dalam analisis proses bisnis, pengembangan aplikasi web berbasis Laravel, dan manajemen basis data relasional.',
                'expected_salary' => 7500000,
            ]
        );

        if ($profile->educations()->count() === 0) {
            $profile->educations()->create([
                'institution_name' => 'Telkom University',
                'degree' => 'D4/S1',
                'major' => 'S1 Sistem Informasi',
                'start_year' => 2022,
                'end_year' => 2026,
            ]);
        }

        if ($profile->skills()->count() === 0) {
            $profile->skills()->createMany([
                [
                    'skill_name' => 'Laravel & PHP',
                    'proficiency_level' => 'Mahir',
                ],
                [
                    'skill_name' => 'System Analysis & Design',
                    'proficiency_level' => 'Mahir',
                ],
                [
                    'skill_name' => 'MySQL & Database Design',
                    'proficiency_level' => 'Menengah',
                ],
            ]);
        }

        if ($profile->experiences()->count() === 0) {
            $profile->experiences()->create([
                'company_name' => 'PT Digital Ketenagakerjaan Nusantara',
                'position' => 'Fullstack Web Developer Intern',
                'start_date' => '2025-07-01',
                'end_date' => '2025-12-31',
                'is_current' => false,
                'description' => 'Merancang dan mengimplementasikan modul pendaftaran, verifikasi identitas NIK, pengelolaan profil pencari kerja, serta pembuatan CV otomatis.',
            ]);
        }

        if ($profile->awards()->count() === 0) {
            $profile->awards()->create([
                'title' => 'Juara 1 Kompetisi Pengembangan Aplikasi Layanan Publik',
                'issuer' => 'Kementerian Ketenagakerjaan RI',
                'year' => 2025,
                'description' => 'Penghargaan atas inovasi sistem verifikasi identitas dan penyusunan CV otomatis pencari kerja.',
            ]);
        }
    }
}

