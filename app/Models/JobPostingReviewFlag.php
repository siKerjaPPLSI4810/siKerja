<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPostingReviewFlag extends Model
{
    public $timestamps = false;

    protected $fillable = ['job_posting_id', 'reason', 'flagged_by', 'flagged_at', 'cleared_by', 'cleared_at'];

    // Government HTML maps these explicitly; generic serialization must not reveal notes or actors.
    protected $hidden = ['reason', 'flagged_by', 'flagged_at', 'cleared_by', 'cleared_at', 'flagger', 'clearer', 'jobPosting'];

    protected function casts(): array
    {
        return ['flagged_at' => 'datetime', 'cleared_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->cleared_at === null;
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function flagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
