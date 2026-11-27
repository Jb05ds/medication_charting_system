<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDailyChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['sometimes', 'integer', Rule::exists('patients', 'id')],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => [
                'sometimes',
                Rule::in(['due', 'overdue', 'given', 'held', 'refused']),
            ],
        ];
    }
}
