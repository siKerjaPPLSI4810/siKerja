<?php

namespace App\Services;

use App\Models\JobPosting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JobInspectionQuery
{
    public function filters(array $input): array
    {
        return [
            'q' => $input['q'] ?? '',
            'category' => $input['category'] ?? '',
            'location' => $input['location'] ?? '',
            'per_page' => (int) ($input['per_page'] ?? 15),
        ];
    }

    private function pending(): Builder
    {
        return JobPosting::query()->where('status', 'pending_approval')->with('company:id,company_name');
    }

    public function paginate(array $input): LengthAwarePaginator
    {
        $filters = $this->filters($input);
        $query = $this->pending();
        $pattern = fn (string $value): string => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
        if ($filters['q'] !== '') {
            $search = $pattern($filters['q']);
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw("title LIKE ? ESCAPE '!'", [$search])
                    ->orWhereHas('company', function (Builder $company) use ($search): void {
                        $company->whereRaw("company_name LIKE ? ESCAPE '!'", [$search]);
                    });
            });
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['location'] !== '') {
            $query->whereRaw("location LIKE ? ESCAPE '!'", [$pattern($filters['location'])]);
        }

        // Stable ID ordering, not a submission timestamp. Input is validated by the Form Request.
        return $query->orderBy('id')->paginate($filters['per_page'], ['*'], 'page', (int) ($input['page'] ?? 1))
            ->appends($filters);
    }

    public function categories(): Collection
    {
        return JobPosting::query()->where('status', 'pending_approval')
            ->distinct()->orderBy('category')->pluck('category');
    }

    public function findPending(string $identifier): JobPosting
    {
        return $this->pending()->findOrFail($identifier);
    }
}
