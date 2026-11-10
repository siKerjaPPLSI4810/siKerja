<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobseekerAward extends Model
{
    use HasFactory;

    protected $table = 'jobseeker_awards';

    protected $fillable = [
        'jobseeker_profile_id',
        'title',
        'issuer',
        'year',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(JobseekerProfile::class, 'jobseeker_profile_id');
    }
}

