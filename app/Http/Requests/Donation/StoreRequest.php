<?php

namespace App\Http\Requests\Donation;

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
            'donator'         => 'required|string|max:100',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:30',
            'donation_type'   => 'nullable|string|in:monetaire,nature',
            // amount: autoriser 0 pour don en nature
            'amount'          => 'required|numeric|min:0',
            'project'         => 'required|string|max:100',
            // paymethod + paytransaction optionnels (absents pour dons en nature)
            'paymethod'       => 'nullable|string|max:50',
            'paytransaction'  => 'nullable|string|max:100',
            'payment_status'  => 'nullable|string|in:pending,succeeded,failed',
            'description'     => 'nullable|string',
            'donation_at'     => 'required|date',
        ];
    }
}
