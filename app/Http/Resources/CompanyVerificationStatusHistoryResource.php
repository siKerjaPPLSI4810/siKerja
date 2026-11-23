<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyVerificationStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'sequence' => $this->sequence, 'event_type' => $this->event_type,
            'old_status' => $this->old_status, 'new_status' => $this->new_status,
            'actor_label' => $this->actor_name_snapshot,
            'reason_code' => $this->reason_code, 'reason' => $this->reason,
            'changed_at' => $this->changed_at->utc()->toIso8601String(),
        ];
    }
}
