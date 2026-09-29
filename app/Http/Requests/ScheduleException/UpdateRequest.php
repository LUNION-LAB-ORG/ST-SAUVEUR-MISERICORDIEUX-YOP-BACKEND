<?php

namespace App\Http\Requests\ScheduleException;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'              => 'sometimes|date_format:Y-m-d',
            'start_time'        => 'sometimes|nullable|date_format:H:i',
            'label'             => 'sometimes|nullable|string|max:150',
            'location'          => 'sometimes|nullable|string|max:150',
            'is_cancelled'      => 'sometimes|boolean',
            'time_slot_id'      => 'sometimes|nullable|integer|exists:time_slots,id',
        ];
    }
}
