<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicationOrder extends Model
{
    protected $fillable = [
        'patient_id',
        'drug_name',
        'dose',
        'route',
        'start_date',
        'end_date',
        'days_of_week',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_of_week' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function times(): HasMany
    {
        return $this->hasMany(MedicationOrderTime::class)->orderBy('time');
    }
}
