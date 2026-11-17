<?php

namespace App\Http\Requests\Government;

use Illuminate\Foundation\Http\FormRequest;

class ListCompanyInspectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-government') ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
