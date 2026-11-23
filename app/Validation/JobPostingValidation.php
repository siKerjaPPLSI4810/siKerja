<?php

namespace App\Validation;

class JobPostingValidation
{
    // Extracted unchanged from Feature 2.2; shared by company forms and approval checks.
    public static function rules(): array
    {
        return [
            'title' => ['required','string','min:3','max:255','regex:/^[a-zA-Z\s]+$/', ],
            'category' => ['required', 'string', 'max:100'],
            'job_type' => ['required', 'string', 'max:50'],
            'location' => ['required','string','min:3','max:150','regex:/^[a-zA-Z\s\.\-]+$/',],
            'education_requirement' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'min:20'],
            'requirements' => ['required', 'string', 'min:20'],
            'quota' => ['required', 'integer', 'min:1'],
            'salary_around' => ['required', 'numeric', 'min:1000000'],
            'deadline' => ['required', 'date', 'after:today'],
            'is_draft' => ['nullable', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            //kumpulan pesan error untuk pengisian form loker
            'title.required' => 'Judul posisi lowongan wajib diisi.',
            'title.min' => 'Judul posisi lowongan minimal 3 huruf.',
            'title.regex' => 'Judul posisi lowongan hanya boleh berisi huruf dan spasi, tidak boleh mengandung angka atau simbol.',
            'category.required' => 'Silakan pilih bidang / kategori pekerjaan dari daftar yang tersedia.',
            'job_type.required' => 'Silakan pilih tipe pekerjaan dari daftar yang tersedia.',
            'location.required' => 'Lokasi penempatan kerja wajib diisi.',
            'location.min' => 'Nama lokasi penempatan minimal 3 karakter.',
            'location.regex' => 'Lokasi penempatan hanya boleh berisi huruf nama kota/wilayah, tidak boleh mengandung angka.',
            'education_requirement.required' => 'Silakan tentukan syarat pendidikan minimum.',
            'description.required' => 'Deskripsi tugas & tanggung jawab pekerjaan wajib diisi.',
            'description.min' => 'Deskripsi pekerjaan minimal 20 karakter agar informatif bagi calon pelamar.',
            'requirements.required' => 'Kualifikasi & persyaratan khusus pelamar wajib diisi.',
            'requirements.min' => 'Kualifikasi pekerjaan minimal 20 karakter agar persyaratan seleksi jelas.',
            'quota.required' => 'Kuota penerimaan wajib diisi.',
            'quota.min' => 'Kuota penerimaan minimal 1 orang.',
            'salary_around.required' => 'Estimasi gaji bulanan wajib diisi.',
            'salary_around.min' => 'Estimasi gaji minimal Rp 1.000.000.',
            'deadline.required' => 'Batas akhir penerimaan (deadline) wajib ditentukan.',
            'deadline.date' => 'Format tanggal batas akhir penerimaan tidak valid.',
            'deadline.after' => 'Batas akhir penerimaan lowongan harus berupa tanggal setelah hari ini.',
        ];
    }
}
