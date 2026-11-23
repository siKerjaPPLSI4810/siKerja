<?php

namespace App\Http\Requests\Government;

class UpdateCompanyVerificationStatusRequest extends StoreCompanyInspectionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'actor_user_id' => ['prohibited'], 'actor_name_snapshot' => ['prohibited'],
            'changed_at' => ['prohibited'], 'sequence' => ['prohibited'],
            'reason_code' => ['prohibited'], 'reason' => ['prohibited'], 'event_type' => ['prohibited'],
        ]);
    }
}
