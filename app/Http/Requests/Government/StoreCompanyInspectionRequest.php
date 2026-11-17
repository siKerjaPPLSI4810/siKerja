<?php

namespace App\Http\Requests\Government;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-government') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('notes'))) {
            $this->merge(['notes' => trim($this->input('notes'))]);
        }
    }

    public function rules(): array
    {
        return [
            'oss_check_result' => ['required', Rule::in(['matched', 'mismatched', 'unconfirmed'])],
            'administrative_result' => ['required', Rule::in(['complete', 'incomplete', 'unconfirmed'])],
            'checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'decision' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'notes' => ['required', 'string', 'max:5000'],
            'data_version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'reviewed_by' => ['prohibited'],
            'method' => ['prohibited'],
            'company_snapshot' => ['prohibited'],
            'verified_by' => ['prohibited'],
            'verification_status' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('decision') === 'verified'
                && ($this->input('oss_check_result') !== 'matched' || $this->input('administrative_result') !== 'complete')) {
                $validator->errors()->add('decision', 'Terverifikasi mensyaratkan OSS sesuai dan administrasi lengkap.');
            }
            if (in_array('unconfirmed', [$this->input('oss_check_result'), $this->input('administrative_result')], true)
                && $this->input('decision') !== 'pending') {
                $validator->errors()->add('decision', 'Hasil belum dapat dipastikan harus tetap pending.');
            }
        }];
    }
}
