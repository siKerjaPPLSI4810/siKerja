<?php

namespace Database\Factories;

use App\Models\JobseekerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobseekerProfile> */
class JobseekerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'pencari_kerja']),
            'nik_verified' => false,
        ];
    }
}
