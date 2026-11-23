<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobMonitoringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Monitoring list only: no applicant, document, account or internal decision data.
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->company === null ? null : [
                'id' => $this->company->id,
                'company_name' => $this->company->company_name,
            ],
            'category' => $this->category,
            'location' => $this->location,
            'status' => $this->status,
            'deadline' => $this->deadline?->format('Y-m-d'),
        ];
    }
}
