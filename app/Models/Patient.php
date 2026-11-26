<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'name',
        'room',
        'allergies',
    ];

    public function medicationOrders(): HasMany
    {
        return $this->hasMany(MedicationOrder::class);
    }

    public function careTasks(): HasMany
    {
        return $this->hasMany(CareTask::class);
    }
}
