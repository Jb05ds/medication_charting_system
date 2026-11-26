<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Administration extends Model
{
    protected $fillable = [
        'medication_order_id',
        'scheduled_for',
        'status',
        'reason',
        'charted_by',
        'charted_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'charted_at' => 'datetime',
        ];
    }

    public function medicationOrder(): BelongsTo
    {
        return $this->belongsTo(MedicationOrder::class);
    }
}
