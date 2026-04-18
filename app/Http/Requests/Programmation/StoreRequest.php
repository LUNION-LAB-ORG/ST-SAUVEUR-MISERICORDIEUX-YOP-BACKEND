<?php

namespace App\Http\Requests\Programmation;

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
            'name'         => 'required|string|max:255',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'category'     => 'nullable|string|max:100',
            'date_at'      => 'required|date',
            'started_at'   => 'required|string',
            'ended_at'     => 'nullable|string',
            'description'  => 'required|string',
            'location'     => 'nullable|string|max:200',
            'is_published' => 'sometimes|boolean',
        ];
    }
}
