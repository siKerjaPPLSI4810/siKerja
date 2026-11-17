<?php

namespace Database\Factories;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyInspection> */
class CompanyInspectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_profile_id' => CompanyProfile::factory(),
            'reviewed_by' => User::factory()->state(['role' => 'pemerintah', 'is_active' => true]),
            'method' => 'manual_oss_rba',
            'oss_check_result' => 'unconfirmed',
            'administrative_result' => 'unconfirmed',
            'decision' => 'pending',
            'notes' => 'Data sintetis pengujian: hasil pemeriksaan belum dapat dipastikan.',
            'checked_on' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'company_snapshot' => function (array $attributes): array {
                $company = CompanyProfile::query()->findOrFail($attributes['company_profile_id']);

                return $company->only([
                    'company_name', 'npwp', 'address', 'province', 'city',
                    'website', 'company_document_path',
                ]);
            },
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'oss_check_result' => 'matched',
            'administrative_result' => 'complete',
            'decision' => 'verified',
            'notes' => 'Data sintetis pengujian: OSS sesuai dan administrasi lengkap.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'oss_check_result' => 'mismatched',
            'administrative_result' => 'incomplete',
            'decision' => 'rejected',
            'notes' => 'Data sintetis pengujian: identitas tidak sesuai dan administrasi tidak lengkap.',
        ]);
    }

    public function withoutReviewer(): static
    {
        return $this->state(fn (array $attributes): array => ['reviewed_by' => null]);
    }
}
