<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'jobseeker_profile_id',
        'status',
        'applicant_notes',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'offered_salary' => 'decimal:2',
        ];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function jobseeker(): BelongsTo
    {
        return $this->belongsTo(JobseekerProfile::class, 'jobseeker_profile_id');
    }

    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->whereHas('jobseeker', fn (Builder $profile) => $profile->where('user_id', $user->id));
    }
}
