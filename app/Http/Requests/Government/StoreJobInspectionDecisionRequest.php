<?php

namespace App\Http\Requests\Government;

use App\Validation\JobInspectionDecisionValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobInspectionDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-government') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(JobInspectionDecisionValidation::normalize($this->all()));
    }

    public function rules(): array
    {
        return JobInspectionDecisionValidation::rules($this->all());
    }
}
