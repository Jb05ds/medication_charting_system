<?php

namespace App\Http\Resources;

use App\Models\MedicationOrderTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationOrderResource extends JsonResource
{
    /**
     * @return array{id: int, drug_name: string, dose: string, route: string, start_date: string, end_date: ?string, days_of_week: array, times: array}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'drug_name' => $this->drug_name,
            'dose' => $this->dose,
            'route' => $this->route,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_of_week' => $this->days_of_week,
            'times' => $this->times
                ->map(fn (MedicationOrderTime $time): string => substr($time->time, 0, 5))
                ->values(),
        ];
    }
}
