<?php

namespace App\Services;

use App\Models\CompanyInspection;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CompanyInspectionService
{
    public function snapshot(CompanyProfile $company): array
    {
        return $company->only(['company_name', 'npwp', 'address', 'province', 'city', 'website', 'company_document_path']);
    }

    public function dataVersion(CompanyProfile $company): string
    {
        // Keep the version tied to the exact record displayed by the detail page.
        $inspection = $company->relationLoaded('inspection') ? $company->inspection : $company->inspection()->first();

        return hash_hmac('sha256', json_encode([
            $company->id, $this->snapshot($company), $company->verification_status,
            $company->getRawOriginal('updated_at'), $inspection?->getAttributes(),
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function ossPortalUrl(): ?string
    {
        $url = config('company_inspections.oss_portal_url');

        return $url === 'https://oss.go.id/id' ? $url : null;
    }

    public function record(CompanyProfile $company, User $reviewer, array $input): CompanyInspection
    {
        Gate::forUser($reviewer)->authorize('access-government');

        return DB::transaction(function () use ($company, $reviewer, $input) {
            $locked = CompanyProfile::query()->lockForUpdate()->findOrFail($company->id);
            $inspection = $locked->inspection()->lockForUpdate()->first();

            if (($inspection && in_array($inspection->decision, ['verified', 'rejected'], true))
                || ! hash_equals($this->dataVersion($locked), $input['data_version'])) {
                throw new ConflictHttpException('Data berubah atau keputusan final sudah tersimpan. Muat ulang dan periksa kembali.');
            }

            $inspection = $locked->inspection()->updateOrCreate([], [
                'reviewed_by' => $reviewer->id,
                'method' => 'manual_oss_rba',
                'oss_check_result' => $input['oss_check_result'],
                'administrative_result' => $input['administrative_result'],
                'decision' => $input['decision'],
                'notes' => $input['notes'],
                'checked_on' => $input['checked_on'],
                'company_snapshot' => $this->snapshot($locked),
            ]);
            $locked->update([
                'verification_status' => $input['decision'],
                'verification_notes' => CompanyProfile::verificationMessageForStatus($input['decision']),
                'verified_by' => $reviewer->id,
            ]);

            return $inspection;
        }, 3);
    }
}
