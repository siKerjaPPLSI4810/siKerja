<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListJobInspectionsRequest;
use App\Services\JobInspectionQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class JobInspectionController extends Controller
{
    public function index(ListJobInspectionsRequest $request, JobInspectionQuery $query): Response|RedirectResponse
    {
        $input = $request->validated();
        $filters = $query->filters($input);
        $jobs = $query->paginate($input);
        if ($jobs->currentPage() > $jobs->lastPage()) {
            return redirect()->route('government.job-inspections.index', array_merge($filters, ['page' => $jobs->lastPage()]));
        }

        return response()->view('government.job-inspections.index', [
            'jobs' => $jobs, 'filters' => $filters, 'categories' => $query->categories(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(string $jobPosting, JobInspectionQuery $query): Response
    {
        return response()->view('government.job-inspections.show', ['job' => $query->findPending($jobPosting)])
            ->header('Cache-Control', 'no-store, private');
    }
}
