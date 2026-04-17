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
            'donation_type'   => 'sometimes|string|in:monetaire,nature',
            'amount'          => 'sometimes|numeric|min:0',
            'project'         => 'sometimes|string|max:100',
            'paymethod'       => 'sometimes|nullable|string|max:50',
            'paytransaction'  => 'sometimes|nullable|string|max:100',
            'description'     => 'nullable|string',
            'donation_at'     => 'sometimes|date',
        ];
    }
}
