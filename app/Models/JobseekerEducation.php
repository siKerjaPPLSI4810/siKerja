<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobseekerEducation extends Model
{
    use HasFactory;

    protected $table = 'jobseeker_educations';

    protected $fillable = [
        'jobseeker_profile_id',
        'institution_name',
        'degree',
        'major',
        'start_year',
        'end_year',
    ];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(JobseekerProfile::class, 'jobseeker_profile_id');
    }
}

