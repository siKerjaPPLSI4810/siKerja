<?php

namespace App\Models;

use Database\Factories\CompanyVerificationStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyVerificationStatusHistory extends Model
{
    /** @use HasFactory<CompanyVerificationStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    // Admin resources must explicitly select authorized fields, even with loaded relations.
    protected $visible = ['id'];

    protected $fillable = [
        'company_profile_id', 'company_inspection_id', 'sequence', 'event_type',
        'old_status', 'new_status', 'actor_user_id', 'actor_name_snapshot',
        'reason_code', 'reason', 'changed_at',
    ];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'changed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function companyInspection(): BelongsTo
    {
        return $this->belongsTo(CompanyInspection::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
