<?php

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobseekerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobApplication> */
class JobApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory()->published(),
            'jobseeker_profile_id' => JobseekerProfile::factory(),
            'status' => 'terkirim',
            'applied_at' => now(),
        ];
    }
}
