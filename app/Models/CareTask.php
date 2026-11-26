<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareTask extends Model
{
    protected $fillable = [
        'patient_id',
        'title',
        'scheduled_for',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
