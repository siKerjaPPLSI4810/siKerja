<?php

namespace App\Http\Requests\Government;

use Illuminate\Validation\Rule;

class ListCompanyVerificationStatusesRequest extends ListCompanyInspectionsRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['nullable', 'string', Rule::in(['pending', 'verified', 'rejected'])],
        ]);
    }
}
