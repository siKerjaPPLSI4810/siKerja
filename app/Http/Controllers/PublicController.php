<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobPosting;
use App\Models\CompanyProfile;
use App\Models\JobseekerProfile;
use App\Models\JobApplication;
use App\Models\RegionalMinimumWage;

class PublicController extends Controller
{
    public function index()
    {
        $stats = [
            'total_jobs' => JobPosting::where('status', 'published')->count(),
            'total_companies' => CompanyProfile::where('verification_status', 'verified')->count(),
            'total_jobseekers' => JobseekerProfile::count(),
            'total_applications' => JobApplication::count(),
        ];

        $featuredJobs = JobPosting::with('company')
            ->where('status', 'published')
            ->latest()
            ->take(6)
            ->get();

        $categories = JobPosting::where('status', 'published')
            ->select('category')
            ->selectRaw('count(*) as count')
            ->groupBy('category')
            ->get();

        $provinces = RegionalMinimumWage::select('province')
            ->distinct()
            ->pluck('province');

        return view('public.index', compact('stats', 'featuredJobs', 'categories', 'provinces'));
    }

    public function jobs(Request $request)
    {
       $validator = validator($request->query(), [
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
        ], [
            'max_salary.gte' => 'Gaji maksimum tidak boleh lebih kecil dari gaji minimum.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jobs.index')
                ->withErrors($validator)
                ->withInput($request->query());
        }
        $query = JobPosting::with('company')->where('status', 'published');

        if ($request->filled('q')) {
            $keyword = '%' . $request->q . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', $keyword)
                  ->orWhere('description', 'like', $keyword)
                  ->orWhere('requirements', 'like', $keyword)
                  ->orWhereHas('company', function ($c) use ($keyword) {
                      $c->where('company_name', 'like', $keyword);
                  });
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('job_type')) {
            $query->where('job_type', $request->job_type);
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->filled('min_salary')) {
            $query->where('salary_around', '>=', (float)$request->min_salary);
        }
        
        if ($request->filled('max_salary')) {
            $query->where('salary_around', '<=', (float)$request->max_salary);
        }

        $jobs = $query->latest()->paginate(9)->withQueryString();

        $categories = JobPosting::where('status', 'published')
            ->distinct()
            ->pluck('category');

        $locations = JobPosting::where('status', 'published')
            ->distinct()
            ->pluck('location');

        return view('public.jobs', compact('jobs', 'categories', 'locations'));
    }

    public function jobDetail($id)
    {
        $job = JobPosting::with('company')->findOrFail($id);

        $hasApplied = false;
        $myApplication = null;

        if (Auth::check() && Auth::user()->isPencariKerja()) {
            $profile = Auth::user()->jobseekerProfile;
            if ($profile) {
                $myApplication = JobApplication::where('job_posting_id', $job->id)
                    ->where('jobseeker_profile_id', $profile->id)
                    ->first();
                $hasApplied = !is_null($myApplication);
            }
        }

        $relatedJobs = JobPosting::with('company')
            ->where('status', 'published')
            ->where('id', '!=', $job->id)
            ->where('category', $job->category)
            ->take(3)
            ->get();

        return view('public.job_detail', compact('job', 'hasApplied', 'myApplication', 'relatedJobs'));
    }
}
