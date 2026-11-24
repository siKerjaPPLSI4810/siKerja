<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListJobInspectionsRequest;
use App\Http\Resources\JobInspectionResource;
use App\Http\Requests\Government\StoreJobInspectionDecisionRequest;
use App\Services\JobInspectionDecisionService;
use App\Services\JobInspectionQuery;
use Illuminate\Http\JsonResponse;

class JobInspectionDataController extends Controller
{
    public function index(ListJobInspectionsRequest $request, JobInspectionQuery $query): JsonResponse
    {
        $input = $request->validated();

        return JobInspectionResource::collection($query->paginate($input))->additional([
            'filters' => $query->filters($input),
            'categories' => $query->categories()->all(),
        ])->response()->header('Cache-Control', 'no-store, private');
    }

    public function show(string $jobPosting, JobInspectionQuery $query): JsonResponse
    {
        return (new JobInspectionResource($query->findPending($jobPosting)))
            ->response()->header('Cache-Control', 'no-store, private');
    }

    public function store(StoreJobInspectionDecisionRequest $request, string $jobPosting, JobInspectionDecisionService $service): JsonResponse
    {
        $job = $service->record($jobPosting, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Keputusan pemeriksaan berhasil disimpan.',
            'data' => $job->only(['id', 'status', 'approved_by', 'rejection_reason']),
        ])->header('Cache-Control', 'no-store, private');
    }
}
