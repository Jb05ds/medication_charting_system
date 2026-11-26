<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'name',
        'room',
        'allergies',
    ];

    public function medicationOrders()
    {
        return $this->hasMany(MedicationOrder::class);
    }

    public function careTasks()
    {
        return $this->hasMany(CareTask::class);
    }
}
