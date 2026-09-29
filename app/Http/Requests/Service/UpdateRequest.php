<?php

namespace App\Http\Requests\Service;

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
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'image'       => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'content'     => 'sometimes|nullable|string',
            'leader'      => 'sometimes|nullable|string|max:150',
            'schedule'    => 'sometimes|nullable|string|max:255',
            'category'    => 'sometimes|nullable|string|max:100',
            'audience'    => 'sometimes|nullable|string|max:255',
            'location'    => 'sometimes|nullable|string|max:255',
            'whatsapp'    => 'sometimes|nullable|string|max:30',
            'status'      => 'sometimes|string|in:draft,published,hidden',
            'sort_order'  => 'sometimes|integer',
        ];
    }
}
