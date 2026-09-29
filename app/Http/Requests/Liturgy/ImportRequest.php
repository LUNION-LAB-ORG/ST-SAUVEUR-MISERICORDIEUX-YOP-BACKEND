<?php

namespace App\Http\Requests\Liturgy;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date_format:Y-m-d',
            'days' => 'nullable|integer|min:1|max:31',
        ];
    }
}
