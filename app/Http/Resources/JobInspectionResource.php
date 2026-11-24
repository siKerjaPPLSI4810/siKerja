<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobInspectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Explicit allowlist: never serialize the company model, internal notes or document references.
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'company' => $this->company === null ? null : [
                'id' => $this->company->id,
                'company_name' => $this->company->company_name,
            ],
            'category' => $this->category,
            'job_type' => $this->job_type,
            'location' => $this->location,
            'description' => $this->description,
            'education_requirement' => $this->education_requirement,
            'requirements' => $this->requirements,
            'salary_around' => $this->salary_around,
            'quota' => (int) $this->quota,
            'deadline' => $this->deadline?->format('Y-m-d'),
        ];
    }
}
