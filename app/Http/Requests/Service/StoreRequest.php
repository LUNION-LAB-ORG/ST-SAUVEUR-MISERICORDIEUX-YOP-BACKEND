<?php

namespace App\Http\Requests\Service;

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
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'content'     => 'nullable|string',
            'leader'      => 'nullable|string|max:150',
            'schedule'    => 'nullable|string|max:255',
        ];
    }
}
