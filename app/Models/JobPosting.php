<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class JobPosting extends Model
{
    use HasFactory;

    // Kolom untuk pembuatan & pengubahan lowongan 
    protected $fillable = [
        'company_profile_id',
        'approved_by',
        'title',
        'category',
        'job_type',
        'location',
        'education_requirement',
        'description',
        'requirements',
        'quota',
        'salary_around',
        'status',
        'rejection_reason',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'salary_around' => 'decimal:2',
        ];
    }


    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_profile_id');
    }

    // ini relasi ke Admin Pemerintah yang menyetujui lowongan 
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ini relasi ke daftar lamaran pencari kerja yang masuk 
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_posting_id');
    }

    public function applicationDeadlinePassed(): bool
    {
        return $this->deadline?->lt(today()) ?? false;
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', 'published');
    }
}