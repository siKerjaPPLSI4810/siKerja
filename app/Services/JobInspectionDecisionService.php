<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Validation\JobInspectionDecisionValidation;
use App\Validation\JobPostingValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JobInspectionDecisionService
{
    public function record(string $identifier, User $reviewer, array $input): JobPosting
    {
        Gate::forUser($reviewer)->authorize('access-government');
        $input = JobInspectionDecisionValidation::normalize($input);
        $data = Validator::make($input, JobInspectionDecisionValidation::rules($input))->validate();

        return DB::transaction(function () use ($identifier, $reviewer, $data): JobPosting {
            // Re-read under lock: a later decision must not overwrite the first final result.
            $job = JobPosting::query()->lockForUpdate()->findOrFail($identifier);
            if ($job->status !== 'pending_approval') {
                throw new ConflictHttpException('Status pengajuan sudah berubah. Muat ulang sebelum memeriksa kembali.');
            }

            if ($data['action'] === 'approve') {
                $company = CompanyProfile::query()->lockForUpdate()->find($job->company_profile_id);
                if ($company?->verification_status !== 'verified') {
                    throw ValidationException::withMessages(['company' => 'Perusahaan harus terverifikasi sebelum lowongan disetujui.']);
                }
                $rules = JobPostingValidation::rules();
                unset($rules['is_draft']);
                // Validate the current stored record, not request-supplied job fields.
                Validator::make($job->getAttributes(), $rules, JobPostingValidation::messages())->validate();
                $changes = ['status' => 'published', 'approved_by' => $reviewer->id, 'rejection_reason' => null];
            } else {
                // Reject and request_changes share the existing status/column; action is not persisted separately.
                $changes = ['status' => 'rejected', 'approved_by' => null, 'rejection_reason' => $data['rejection_reason']];
            }
            $job->update($changes);

            return $job;
        }, 3);
    }
}
