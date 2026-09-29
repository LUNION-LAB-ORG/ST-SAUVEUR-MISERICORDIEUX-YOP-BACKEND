<?php

namespace App\Http\Requests\ScheduleException;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'              => 'required|date_format:Y-m-d',
            'start_time'        => 'nullable|date_format:H:i',
            'label'             => 'nullable|required_without:time_slot_id|string|max:150',
            'location'          => 'nullable|string|max:150',
            'is_cancelled'      => 'sometimes|boolean',
            'time_slot_id'      => 'nullable|integer|exists:time_slots,id',
        ];
    }
}
