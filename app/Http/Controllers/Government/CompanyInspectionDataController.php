<?php

namespace App\Http\Controllers\Government;

use App\Http\Controllers\Controller;
use App\Http\Requests\Government\ListCompanyInspectionsRequest;
use App\Http\Resources\CompanyInspectionResource;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use App\Services\CompanyInspectionQuery;

class CompanyInspectionDataController extends Controller
{
    public function index(ListCompanyInspectionsRequest $request, CompanyInspectionQuery $query): JsonResponse
    {
        $companies = $query->paginate($request->validated());

        return CompanyInspectionResource::collection($companies)->response()
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(CompanyProfile $companyProfile): JsonResponse
    {
        return (new CompanyInspectionResource($companyProfile))->response()
            ->header('Cache-Control', 'no-store, private');
    }
}
