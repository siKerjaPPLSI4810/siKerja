<?php

namespace App\Services;

use App\Models\CompanyProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyInspectionQuery
{
    public function paginate(array $input): LengthAwarePaginator
    {
        $search = trim($input['q'] ?? '');
        $query = CompanyProfile::query()->with('inspection')->where('verification_status', 'pending');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function ($query) use ($pattern) {
                $query->whereRaw("company_name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("npwp LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $companies = $query->orderBy('id')->paginate(
            (int) ($input['per_page'] ?? 15), ['*'], 'page', (int) ($input['page'] ?? 1),
        );

        return $companies->appends(['q' => $search, 'per_page' => $companies->perPage()]);
    }
}
