<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'verified_by',
        'company_name',
        'npwp',
        'address',
        'province',
        'city',
        'website',
        'company_document_path',
        'verification_status', // pending, verified, rejected
        'verification_notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function getFormattedStatusAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'TERVERIFIKASI (VALID)',
            'rejected' => 'DITOLAK (TIDAK VALID)',
            default => 'BELUM DAPAT DIPASTIKAN (MENUNGGU PENINJAUAN)',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-warning text-dark',
        };
    }

    public function getStatusAlertClassAttribute(): string
    {
        return match ($this->verification_status) {
            'verified' => 'alert-success',
            'rejected' => 'alert-danger',
            default => 'alert-warning',
        };
    }
}
