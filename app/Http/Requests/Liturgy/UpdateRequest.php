<?php

namespace App\Http\Requests\Liturgy;

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
            'feast_override' => 'sometimes|nullable|string|max:255',
            'color_override' => 'sometimes|nullable|string|max:50',
        ];
    }
}
