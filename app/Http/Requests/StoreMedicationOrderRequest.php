<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicationOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drug_name' => ['required', 'string', 'max:100'],
            'dose' => ['required', 'string', 'max:50'],
            'route' => [
                'required',
                Rule::in(['Oral', 'Topical', 'Inhaled', 'Eye drops', 'Injection']),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'days_of_week' => ['required', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['required', 'integer', 'between:0,6', 'distinct'],
            'times' => ['required', 'array', 'min:1', 'max:4'],
            'times.*' => ['required', 'date_format:H:i', 'distinct'],
            'added_by' => ['required', 'string', 'max:100'],
        ];
    }
}
