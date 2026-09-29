<?php

namespace App\Http\Requests\Priest;

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
            'fullname'          => 'required|string|max:150',
            'function'          => 'required|string|max:100',
            'missions'          => 'nullable|string|max:500',
            'biography'         => 'nullable|string',
            'ordination_year'   => 'nullable|integer|min:1900|max:2100',
            'photo'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
