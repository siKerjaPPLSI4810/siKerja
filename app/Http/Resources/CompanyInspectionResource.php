<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyInspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'npwp' => $this->npwp,
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'website' => $this->website,
            'verification_status' => $this->verification_status,
            // Presence of a reference does not guarantee the file exists or is valid.
            'document_reference_present' => filled($this->company_document_path),
            'data_version' => $this->when($request->routeIs('government.company-inspections.api.show'),
                fn () => app(\App\Services\CompanyInspectionService::class)->dataVersion($this->resource)),
        ];
    }
}
