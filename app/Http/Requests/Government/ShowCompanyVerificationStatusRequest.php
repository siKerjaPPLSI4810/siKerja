<?php

namespace App\Http\Requests\Government;

class ShowCompanyVerificationStatusRequest extends ListCompanyVerificationStatusesRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'history_page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'history_per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
    }
}
