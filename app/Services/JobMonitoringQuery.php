<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\CompanyProfile;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class JobMonitoringQuery
{
    public function filters(array $input): array
    {
        return [
            'q' => $input['q'] ?? '',
            'company' => isset($input['company']) ? (int) $input['company'] : null,
            'category' => $input['category'] ?? '',
            'location' => $input['location'] ?? '',
            'status' => 'published',
            'per_page' => (int) ($input['per_page'] ?? 15),
        ];
    }

    public function paginate(array $input): LengthAwarePaginator
    {
        $filters = $this->filters($input);
        // Status is authoritative; expired/null deadlines and null approvers remain visible.
        $query = JobPosting::query()->published()->with(['company:id,company_name', 'reviewFlag:id,job_posting_id,cleared_at'])
            ->select(['id', 'company_profile_id', 'title', 'category', 'location', 'status', 'deadline']);
        $pattern = fn (string $value): string => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        if ($filters['q'] !== '') {
            $search = $pattern($filters['q']);
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw("title LIKE ? ESCAPE '!'", [$search])
                    ->orWhereHas('company', fn (Builder $company) => $company->whereRaw("company_name LIKE ? ESCAPE '!'", [$search]));
            });
        }
        if ($filters['company'] !== null) {
            $query->where('company_profile_id', $filters['company']);
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['location'] !== '') {
            $query->whereRaw("location LIKE ? ESCAPE '!'", [$pattern($filters['location'])]);
        }

        return $query->orderBy('id')->paginate($filters['per_page'], ['*'], 'page', (int) ($input['page'] ?? 1))
            ->appends($filters);
    }

    public function companies(): Collection
    {
        return CompanyProfile::query()->whereHas('jobPostings', fn (Builder $jobs) => $jobs->published())
            ->select(['id', 'company_name'])->orderBy('company_name')->orderBy('id')->get();
    }

    public function categories(): Collection
    {
        return JobPosting::query()->published()->distinct()->orderBy('category')->pluck('category');
    }

    public function findPublished(string $identifier): JobPosting
    {
        return JobPosting::query()->published()->with(['company:id,company_name', 'reviewFlag.flagger:id,name', 'reviewFlag.clearer:id,name'])->findOrFail($identifier);
    }
}
