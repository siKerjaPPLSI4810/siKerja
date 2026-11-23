<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyVerificationMonitoringQuery
{
    public function historyQuery(CompanyProfile $company): HasMany
    {
        return $company->verificationStatusHistories()->orderByDesc('sequence');
    }

    public function paginate(array $input): LengthAwarePaginator
    {
        $query = CompanyProfile::query()->with('inspection');
        if (filled($input['status'] ?? null)) {
            $query->where('verification_status', $input['status']);
        }
        $search = trim($input['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function ($query) use ($pattern) {
                $query->whereRaw("company_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("npwp LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query->orderBy('id')->paginate((int) ($input['per_page'] ?? 15), ['*'], 'page', (int) ($input['page'] ?? 1))
            ->appends(['status' => $input['status'] ?? '', 'q' => $search, 'per_page' => $input['per_page'] ?? 15]);
    }

    public static function provenance(CompanyProfile $company): string
    {
        if (! in_array($company->verification_status, ['pending', 'verified', 'rejected'], true)) {
            return 'unknown';
        }
        $inspection = $company->inspection;
        if ($inspection) {
            return $inspection->decision === $company->verification_status ? 'official' : 'unknown';
        }

        return $company->verification_status === 'pending' ? 'uninspected' : 'legacy';
    }
}
