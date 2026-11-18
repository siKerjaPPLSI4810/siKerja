<?php

namespace Database\Factories;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\CompanyVerificationStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanyVerificationStatusHistory> */
class CompanyVerificationStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_inspection_id' => CompanyInspection::factory()->verified()->state([
                'company_profile_id' => CompanyProfile::factory()->state(['verification_status' => 'verified']),
            ]),
            'company_profile_id' => fn (array $attributes) => CompanyInspection::findOrFail($attributes['company_inspection_id'])->company_profile_id,
            'sequence' => 1,
            'event_type' => 'status_changed',
            'old_status' => 'pending',
            'new_status' => 'verified',
            'actor_user_id' => fn (array $attributes) => CompanyInspection::findOrFail($attributes['company_inspection_id'])->reviewed_by,
            'actor_name_snapshot' => fn (array $attributes) => $attributes['actor_user_id']
                ? User::findOrFail($attributes['actor_user_id'])->name : 'Petugas sintetis tidak tersedia',
            'reason_code' => 'manual_inspection_decision',
            'reason' => 'Data sintetis: OSS sesuai; administrasi lengkap; keputusan verified.',
            'changed_at' => now(),
        ];
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'company_inspection_id' => CompanyInspection::factory()->rejected()->state([
                'company_profile_id' => CompanyProfile::factory()->state(['verification_status' => 'rejected']),
            ]),
            'new_status' => 'rejected',
            'reason' => 'Data sintetis: OSS tidak sesuai; administrasi tidak lengkap; keputusan rejected.',
        ]);
    }

    public function withoutActor(): static
    {
        return $this->state(fn (array $attributes): array => ['actor_user_id' => null, 'actor_name_snapshot' => 'Petugas sintetis yang telah dihapus']);
    }
}
