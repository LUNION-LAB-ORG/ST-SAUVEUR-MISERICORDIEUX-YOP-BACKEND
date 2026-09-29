<?php

namespace App\Http\Requests\Priest;

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
            'fullname'          => 'sometimes|string|max:150',
            'function'          => 'sometimes|string|max:100',
            'missions'          => 'sometimes|nullable|string|max:500',
            'biography'         => 'sometimes|nullable|string',
            'ordination_year'   => 'sometimes|nullable|integer|min:1900|max:2100',
            'since_year'        => 'sometimes|nullable|integer|min:1900|max:2100',
            'congregation'      => 'sometimes|nullable|string|max:255',
            'photo'             => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
