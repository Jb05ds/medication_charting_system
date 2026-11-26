<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_for' => ['required', 'date'],
            'status' => ['required', Rule::in(['given', 'held', 'refused'])],
            'reason' => ['required_if:status,held,refused', 'nullable', 'string', 'max:255'],
            'charted_by' => ['required', 'string', 'max:100'],
        ];
    }
}
