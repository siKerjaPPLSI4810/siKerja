<?php

namespace App\Models;

use Database\Factories\CompanyInspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInspection extends Model
{
    /** @use HasFactory<CompanyInspectionFactory> */
    use HasFactory;

    // Government views explicitly map authorized fields; generic JSON stays private.
    protected $hidden = ['notes', 'company_snapshot', 'reviewed_by', 'reviewer'];

    protected $fillable = [
        'company_profile_id',
        'reviewed_by',
        'method',
        'oss_check_result',
        'administrative_result',
        'decision',
        'notes',
        'checked_on',
        'company_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'company_snapshot' => 'array',
        ];
    }

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
