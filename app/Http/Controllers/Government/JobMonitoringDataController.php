<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListJobMonitoringRequest;
use App\Http\Resources\JobMonitoringResource;
use App\Services\JobMonitoringQuery;
use Illuminate\Http\JsonResponse;

class JobMonitoringDataController extends Controller
{
    public function index(ListJobMonitoringRequest $request, JobMonitoringQuery $query): JsonResponse
    {
        $input = $request->validated();

        return JobMonitoringResource::collection($query->paginate($input))->additional([
            'filters' => $query->filters($input),
        ])->response()->header('Cache-Control', 'no-store, private');
    }
}
