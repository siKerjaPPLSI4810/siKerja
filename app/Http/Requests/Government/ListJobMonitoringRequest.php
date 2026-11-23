<?php

namespace App\Http\Requests\Government;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListJobMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-government') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'category', 'location', 'status'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'category' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            // Multi-status monitoring is not agreed; never widen the published scope.
            'status' => ['nullable', 'string', Rule::in(['published'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
