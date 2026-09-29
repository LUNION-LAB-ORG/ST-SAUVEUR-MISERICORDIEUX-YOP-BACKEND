<?php

namespace App\Http\Requests\Announcement;

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
            'category'          => 'nullable|string|max:100',
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'contact'           => 'nullable|string|max:255',
            'is_featured'       => 'sometimes|boolean',
            'visible_from'      => 'nullable|date_format:Y-m-d',
            'visible_until'     => 'nullable|date_format:Y-m-d',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
