<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobseekerSkill extends Model
{
    use HasFactory;

    protected $table = 'jobseeker_skills';

    protected $fillable = [
        'jobseeker_profile_id',
        'skill_name',
        'proficiency_level',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(JobseekerProfile::class, 'jobseeker_profile_id');
    }
}

