<?php

namespace App\Http\Requests\Listen;

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
            'type'          => 'nullable|string|max:100',
            'fullname'      => 'required|string|max:255',
            'phone'         => 'nullable|string|max:60',
            'message'       => 'required|string',
            'availability'  => 'nullable|string|max:100',
            'time_slot_id'  => 'nullable|integer|exists:time_slots,id',
            'listen_at'     => 'nullable|date',
            'request_status' => 'nullable|string|in:pending,accepted,canceled',
        ];
    }
}
