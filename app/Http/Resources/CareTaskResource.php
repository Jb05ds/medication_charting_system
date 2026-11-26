<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareTaskResource extends JsonResource
{
    /**
     * @return array{id: int, patient_id: int, patient_name: string, room: string, title: string, scheduled_for: string, status: string, completed_by: ?string, completed_at: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->name,
            'room' => $this->patient->room,
            'title' => $this->title,
            'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'status' => $this->computed_status,
            'completed_by' => $this->completed_by,
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
