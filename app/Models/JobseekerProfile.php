<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobseekerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nik',
        'nik_verified',
        'birth_date',
        'gender',
        'address',
        'province',
        'city',
        'bio',
        'expected_salary',
        'cv_file_path',
    ];

    protected function casts(): array
    {
        return [
            'nik_verified' => 'boolean',
            'birth_date' => 'date',
            'expected_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(JobseekerEducation::class)->orderByDesc('start_year');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(JobseekerExperience::class)->orderByDesc('start_date');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(JobseekerSkill::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(JobseekerAward::class)->orderByDesc('year');
    }

    /**
     * Hitung indikator kelengkapan profil pencari kerja (PBI 1.1.2).
     * Terdiri dari 5 bagian: Data Diri, Pendidikan, Kompetensi, Pengalaman, Penghargaan.
     *
     * @return array{
     *     percentage: int,
     *     completed_count: int,
     *     total_count: int,
     *     sections: array<string, array{label: string, completed: bool, weight: int, description: string}>
     * }
     */
    public function calculateCompleteness(): array
    {
        $user = $this->user;

        $personalCompleted = ! empty($user?->name)
            && ! empty($user?->phone)
            && ! empty($this->nik)
            && $this->nik_verified
            && ! empty($this->birth_date)
            && ! empty($this->gender)
            && ! empty($this->address)
            && ! empty($this->province)
            && ! empty($this->city)
            && ! empty($this->bio);

        $educationCompleted = $this->educations()->count() > 0;
        $skillCompleted = $this->skills()->count() > 0;
        $experienceCompleted = $this->experiences()->count() > 0;
        $awardCompleted = $this->awards()->count() > 0;

        $sections = [
            'data_diri' => [
                'label' => 'Data Diri',
                'completed' => $personalCompleted,
                'weight' => 20,
                'description' => 'Nama, telepon, NIK terverifikasi, tgl lahir, jenis kelamin, alamat, provinsi, kota, & ringkasan diri',
            ],
            'pendidikan' => [
                'label' => 'Pendidikan',
                'completed' => $educationCompleted,
                'weight' => 20,
                'description' => 'Minimal 1 riwayat pendidikan formal',
            ],
            'kompetensi' => [
                'label' => 'Kompetensi',
                'completed' => $skillCompleted,
                'weight' => 20,
                'description' => 'Minimal 1 keahlian / kompetensi beserta tingkat kemahiran',
            ],
            'pengalaman' => [
                'label' => 'Pengalaman Kerja',
                'completed' => $experienceCompleted,
                'weight' => 20,
                'description' => 'Minimal 1 riwayat pengalaman kerja / magang / organisasi',
            ],
            'penghargaan' => [
                'label' => 'Penghargaan',
                'completed' => $awardCompleted,
                'weight' => 20,
                'description' => 'Minimal 1 penghargaan, prestasi, atau sertifikasi',
            ],
        ];

        $completedCount = 0;
        $percentage = 0;

        foreach ($sections as $section) {
            if ($section['completed']) {
                $completedCount++;
                $percentage += $section['weight'];
            }
        }

        return [
            'percentage' => $percentage,
            'completed_count' => $completedCount,
            'total_count' => count($sections),
            'sections' => $sections,
        ];
    }
}

