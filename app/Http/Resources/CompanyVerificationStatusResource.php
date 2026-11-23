<?php

namespace App\Http\Resources;

use App\Services\CompanyInspectionService;
use App\Services\CompanyVerificationMonitoringQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyVerificationStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'npwp' => $this->npwp,
            'verification_status' => $this->verification_status,
            'provenance' => CompanyVerificationMonitoringQuery::provenance($this->resource),
            'data_version' => $this->when($request->routeIs('government.company-verification-statuses.api.show'),
                fn () => app(CompanyInspectionService::class)->dataVersion($this->resource)),
        ];
    }
}
