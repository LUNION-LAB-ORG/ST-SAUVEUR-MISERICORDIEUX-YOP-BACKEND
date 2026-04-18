<?php

namespace App\Http\Requests\Donation;

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
            'donator'         => 'sometimes|string|max:100',
            'email'           => 'sometimes|nullable|email|max:255',
            'phone'           => 'sometimes|nullable|string|max:30',
            'donation_type'   => 'sometimes|string|in:monetaire,nature',
            'amount'          => 'sometimes|numeric|min:0',
            'project'         => 'sometimes|string|max:100',
            'paymethod'       => 'sometimes|nullable|string|max:50',
            'paytransaction'  => 'sometimes|nullable|string|max:100',
            'payment_status'  => 'sometimes|string|in:pending,succeeded,failed',
            'description'     => 'nullable|string',
            'donation_at'     => 'sometimes|date',
        ];
    }
}
