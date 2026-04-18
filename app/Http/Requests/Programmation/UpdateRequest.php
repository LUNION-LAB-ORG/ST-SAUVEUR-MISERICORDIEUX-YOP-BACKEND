<?php

namespace App\Http\Requests\Programmation;

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
            'name'         => 'sometimes|string|max:255',
            'image'        => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'category'     => 'sometimes|nullable|string|max:100',
            'date_at'      => 'sometimes|date',
            'started_at'   => 'sometimes|string',
            'ended_at'     => 'sometimes|nullable|string',
            'description'  => 'sometimes|string',
            'location'     => 'sometimes|nullable|string|max:200',
            'is_published' => 'sometimes|boolean',
        ];
    }
}
