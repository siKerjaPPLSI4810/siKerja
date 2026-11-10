<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function jobs(Request $request): View|RedirectResponse
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'job_type' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', Rule::when($request->filled('min_salary'), 'gte:min_salary')],
        ];

        $validator = validator($request->query(), $rules, [
            'max_salary.gte' => 'Gaji maksimum tidak boleh lebih kecil dari gaji minimum.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('jobs.index')
                ->withErrors($validator)
                ->withInput(array_filter($request->only(array_keys($rules)), 'is_scalar'));
        }

        $filters = $validator->validated();
        $query = JobPosting::query()->published()->with('company');

        if (isset($filters['q'])) {
            $keyword = '%'.$filters['q'].'%';

            $query->where(function (Builder $query) use ($keyword): void {
                $query->whereLike('title', $keyword)
                    ->orWhereLike('description', $keyword)
                    ->orWhereLike('requirements', $keyword)
                    ->orWhereHas('company', fn (Builder $company) => $company->whereLike('company_name', $keyword));
            });
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        if (isset($filters['location'])) {
            $query->whereLike('location', '%'.$filters['location'].'%');
        }

        if (isset($filters['min_salary'])) {
            $query->where('salary_around', '>=', $filters['min_salary']);
        }

        if (isset($filters['max_salary'])) {
            $query->where('salary_around', '<=', $filters['max_salary']);
        }

        $jobs = $query->latest()->orderByDesc('id')->paginate(9)->withQueryString();
        $categories = JobPosting::query()->published()->distinct()->orderBy('category')->pluck('category');
        $jobTypes = JobPosting::query()->published()->distinct()->orderBy('job_type')->pluck('job_type');
        $locations = JobPosting::query()->published()->distinct()->orderBy('location')->pluck('location');

        return view('public.jobs', compact('jobs', 'categories', 'jobTypes', 'locations'));
    }

    public function jobDetail(Request $request, int $id): View
    {
        $job = JobPosting::query()->published()->with('company')->findOrFail($id);

        $hasApplied = Gate::allows('access-job-applications') && JobApplication::query()
            ->forUser($request->user())
            ->where('job_posting_id', $job->id)
            ->exists();

        return view('public.job_detail', compact('job', 'hasApplied'));
    }
}
