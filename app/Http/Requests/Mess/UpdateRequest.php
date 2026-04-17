<?php

namespace App\Http\Requests\Mess;

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
            'type'            => 'sometimes|string|max:255',
            'fullname'        => 'sometimes|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'sometimes|string|max:60',
            'message'         => 'nullable|string',
            'request_status'  => 'sometimes|in:pending,accepted,canceled',
            'payment_status'  => 'sometimes|nullable|in:pending,succeeded,failed',
            'amount'          => 'sometimes|numeric|min:0',
            'date_at'         => 'sometimes|date',
            'time_at'         => 'sometimes',
        ];
    }
}
