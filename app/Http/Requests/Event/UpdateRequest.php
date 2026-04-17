<?php

namespace App\Http\Requests\Event;

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
            'title'                  => 'sometimes|string|max:255',
            'date_at'                => 'sometimes|date',
            'time_at'                => 'sometimes',
            'location_at'            => 'sometimes|string|max:150',
            'description'            => 'nullable|string',
            'image'                  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_paid'                 => 'nullable|boolean',
            'price'                   => 'nullable|numeric|min:0',
            'pricing_tiers'           => 'nullable|array',
            'pricing_tiers.*.label'   => 'required_with:pricing_tiers|string|max:100',
            'pricing_tiers.*.amount'  => 'required_with:pricing_tiers|numeric|min:0',
            'pricing_tiers.*.description' => 'nullable|string|max:255',
            'pricing_tiers.*.max_participants' => 'nullable|integer|min:1',
            'max_participants'        => 'nullable|integer|min:1',
            'registration_deadline'   => 'nullable|date',
        ];
    }
}
