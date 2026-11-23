<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListJobMonitoringRequest;
use App\Services\JobMonitoringQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class JobMonitoringController extends Controller
{
    public function index(ListJobMonitoringRequest $request, JobMonitoringQuery $query): Response|RedirectResponse
    {
        $input = $request->validated();
        $filters = $query->filters($input);
        $jobs = $query->paginate($input);
        if ($jobs->currentPage() > $jobs->lastPage()) {
            return redirect()->route('government.job-monitoring.index', array_merge($filters, ['page' => $jobs->lastPage()]))
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->view('government.job-monitoring.index', [
            'jobs' => $jobs, 'filters' => $filters,
            'companies' => $query->companies(), 'categories' => $query->categories(),
            'statusOptions' => ['published' => 'Disetujui / Tayang'],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(string $jobPosting, JobMonitoringQuery $query): Response
    {
        return response()->view('government.job-monitoring.show', ['job' => $query->findPublished($jobPosting)])
            ->header('Cache-Control', 'no-store, private');
    }
}