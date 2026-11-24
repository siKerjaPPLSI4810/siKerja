<?php

namespace App\Validation;

use Illuminate\Validation\Rule;

class JobInspectionDecisionValidation
{
    public static function normalize(array $input): array
    {
        if (isset($input['rejection_reason']) && is_string($input['rejection_reason'])) {
            $input['rejection_reason'] = trim($input['rejection_reason']);
        }

        return $input;
    }

    public static function rules(array $input): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject', 'request_changes'])],
            'rejection_reason' => [
                'required_if:action,reject,request_changes', 'nullable', 'string',
                Rule::when(($input['action'] ?? null) === 'reject', ['max:1000']),
            ],
            'status' => ['prohibited'], 'approved_by' => ['prohibited'],
            'company_profile_id' => ['prohibited'], 'job_posting_id' => ['prohibited'],
        ];
    }
}
