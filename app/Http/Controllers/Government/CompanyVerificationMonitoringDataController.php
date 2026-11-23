<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListCompanyVerificationStatusesRequest;
use App\Http\Requests\Government\UpdateCompanyVerificationStatusRequest;
use App\Http\Resources\CompanyVerificationStatusResource;
use App\Http\Resources\CompanyVerificationStatusHistoryResource;
use App\Models\CompanyProfile;
use App\Services\CompanyInspectionService;
use App\Services\CompanyVerificationMonitoringQuery;
use Illuminate\Http\JsonResponse;

class CompanyVerificationMonitoringDataController extends Controller
{
    public function index(ListCompanyVerificationStatusesRequest $request, CompanyVerificationMonitoringQuery $query): JsonResponse
    {
        return CompanyVerificationStatusResource::collection($query->paginate($request->validated()))
            ->response()->header('Cache-Control', 'no-store, private');
    }

    public function show(CompanyProfile $companyProfile): JsonResponse
    {
        $companyProfile->load('inspection');

        return (new CompanyVerificationStatusResource($companyProfile))->response()->header('Cache-Control', 'no-store, private');
    }

    public function history(ListCompanyVerificationStatusesRequest $request, CompanyProfile $companyProfile, CompanyVerificationMonitoringQuery $query): JsonResponse
    {
        $input = $request->validated();
        $events = $query->historyQuery($companyProfile)
            ->paginate((int) ($input['per_page'] ?? 15), ['*'], 'page', (int) ($input['page'] ?? 1));
        $events->appends(['per_page' => $events->perPage()]);

        return CompanyVerificationStatusHistoryResource::collection($events)->response()->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateCompanyVerificationStatusRequest $request, CompanyProfile $companyProfile, CompanyInspectionService $service): JsonResponse
    {
        $companyProfile->load('inspection');
        abort_if(in_array(CompanyVerificationMonitoringQuery::provenance($companyProfile), ['legacy', 'unknown'], true), 409);
        $inspection = $service->record($companyProfile, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Hasil pemeriksaan berhasil disimpan.',
            'data' => ['company_id' => $companyProfile->id, 'inspection_id' => $inspection->id, 'verification_status' => $inspection->decision],
        ])->header('Cache-Control', 'no-store, private');
    }
}
