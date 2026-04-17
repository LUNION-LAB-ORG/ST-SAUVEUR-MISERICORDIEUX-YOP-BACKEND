<?php

namespace App\Http\Requests\Listen;

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
            'type'           => 'sometimes|nullable|string|max:100',
            'fullname'       => 'sometimes|string|max:255',
            'phone'          => 'sometimes|nullable|string|max:60',
            'message'        => 'sometimes|string',
            'availability'   => 'sometimes|nullable|string|max:100',
            'time_slot_id'   => 'sometimes|nullable|integer|exists:time_slots,id',
            'listen_at'      => 'sometimes|nullable|date',
            'request_status' => 'sometimes|string|in:pending,accepted,canceled',
        ];
    }
}
