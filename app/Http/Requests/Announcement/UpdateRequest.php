<?php

namespace App\Http\Requests\Announcement;

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
            'category'          => 'sometimes|nullable|string|max:100',
            'title'             => 'sometimes|string|max:255',
            'content'           => 'sometimes|string',
            'contact'           => 'sometimes|nullable|string|max:255',
            'is_featured'       => 'sometimes|boolean',
            'visible_from'      => 'sometimes|nullable|date_format:Y-m-d',
            'visible_until'     => 'sometimes|nullable|date_format:Y-m-d',
            'status'            => 'sometimes|string|in:draft,published,hidden',
            'sort_order'        => 'sometimes|integer',
        ];
    }
}
