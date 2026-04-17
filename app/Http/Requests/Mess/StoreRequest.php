<?php

namespace App\Http\Requests\Mess;

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
            'type'            => 'required|string|max:255',
            'fullname'        => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'required|string|max:60',
            'message'         => 'nullable|string',
            'request_status'  => 'required|in:pending,accepted,canceled',
            'payment_status'  => 'sometimes|nullable|in:pending,succeeded,failed',
            'amount'          => 'required|numeric|min:0',
            'date_at'         => 'required|date',
            'time_at'         => 'required',
        ];
    }
}
