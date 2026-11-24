<?php

namespace App\Http\Requests\Government;

use Illuminate\Foundation\Http\FormRequest;

class ListJobInspectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-government') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'category', 'location'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
