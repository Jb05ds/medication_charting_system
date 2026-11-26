<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministrationResource extends JsonResource
{
    /**
     * @return array{id: int, medication_order_id: int, scheduled_for: string, status: string, reason: ?string, charted_by: string, charted_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_order_id' => $this->medication_order_id,
            'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'status' => $this->status,
            'reason' => $this->reason,
            'charted_by' => $this->charted_by,
            'charted_at' => $this->charted_at->toIso8601String(),
        ];
    }
}
