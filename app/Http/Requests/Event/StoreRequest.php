<?php

namespace App\Http\Requests\Event;

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
            'title'                  => 'required|string|max:255',
            'date_at'                => 'required|date',
            'time_at'                => 'required',
            'location_at'            => 'required|string|max:150',
            'description'            => 'nullable|string',
            'image'                  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_paid'                => 'nullable|boolean',
            'price'                  => 'nullable|numeric|min:0',
            'max_participants'       => 'nullable|integer|min:1',
            'registration_deadline'  => 'nullable|date',
        ];
    }
}
