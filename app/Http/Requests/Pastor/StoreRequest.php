<?php

namespace App\Http\Requests\Pastor;

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
            'fullname'    => 'required|string|max:150',
            'photo'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'started_at'  => 'required|date',
            'ended_at'    => 'nullable|date',
            'description' => 'nullable|string|max:2000',
        ];
    }
}
