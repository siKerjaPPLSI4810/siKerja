<?php

namespace Database\Factories;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobPosting> */
class JobPostingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'title' => fake()->jobTitle(),
            'category' => 'Teknologi Informasi',
            'job_type' => 'Penuh Waktu',
            'location' => 'Jakarta',
            'education_requirement' => 'S1',
            'description' => 'Pengembangan aplikasi',
            'requirements' => 'Pengalaman kerja',
            'salary_around' => 8000000,
            'status' => 'pending_approval',
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }
}
